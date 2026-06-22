<?php

declare(strict_types=1);

namespace App\Services\DataProtection;

use App\Models\DataRequest;
use App\Models\User;
use App\Models\Verification;

/**
 * Serves a signed-in customer's own data-protection actions (Loi 2024/017
 * art. 39/43): a machine-readable copy of their personal data, and lodging a
 * data-subject request. Runs in the tenant context (the app panel).
 */
final class DataSubjectService
{
    /**
     * A portable copy of everything tied to this user's account.
     *
     * @return array<string, mixed>
     */
    public function exportFor(User $user): array
    {
        $activity = Verification::query()
            ->where('checked_by', $user->id)
            ->orderByDesc('id')
            ->limit(5000)
            ->get(['id', 'niu', 'verdict', 'created_at'])
            ->map(fn (Verification $v): array => [
                'id' => $v->id,
                'niu' => $v->niu,
                'verdict' => $v->verdict,
                'at' => $v->created_at, // serialised by json_encode
            ])->all();

        return [
            'exported_at' => now()->toIso8601String(),
            'workspace' => tenant()?->getTenantKey(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
                'email_verified_at' => $user->email_verified_at, // serialised by json_encode
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'two_factor_enabled' => filled($user->app_authentication_secret),
            ],
            'activity' => $activity,
        ];
    }

    /** Lodge a data-subject request for the signed-in user. */
    public function lodgeRequest(User $user, string $type, ?string $details): DataRequest
    {
        return DataRequest::query()->create([
            'tenant_id' => tenant()?->getTenantKey(),
            'tenant_name' => tenant()->name ?? tenant()?->getTenantKey(),
            'requester_email' => $user->email,
            'requester_name' => $user->name,
            'type' => $type,
            'details' => $details,
            'status' => 'new',
            'due_at' => now()->addDays((int) config('niu.data_protection.dsar_reply_days', 30)),
        ]);
    }
}
