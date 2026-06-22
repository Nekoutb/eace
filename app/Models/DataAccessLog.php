<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Central. A read-level access-log entry (Loi 2024/017 art. 27): who exported or
 * downloaded personal data, and when. Written best-effort — logging must never
 * break the action it records.
 *
 * @property int $id
 * @property ?string $tenant_id
 * @property ?int $user_id
 * @property ?string $user_email
 * @property string $action
 * @property string $resource
 * @property ?string $context
 * @property ?string $ip
 * @property ?Carbon $created_at
 */
class DataAccessLog extends CentralModel
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** Record an export/download/view of personal data (best-effort; never throws). */
    public static function record(string $action, string $resource, ?string $context = null): void
    {
        try {
            static::query()->create([
                'tenant_id' => tenant() !== null ? (string) tenant()->getTenantKey() : null,
                'user_id' => auth()->id(),
                'user_email' => auth()->user()?->email,
                'action' => $action,
                'resource' => $resource,
                'context' => $context,
                'ip' => request()->ip(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
