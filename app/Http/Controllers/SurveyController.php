<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SurveyResponse;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

/**
 * The public, anonymous customer-experience survey. The link carries an encrypted
 * token identifying only the WORKSPACE (never a user), so a response is tagged by
 * client for segmentation but is anonymous to the individual. No authentication —
 * anyone with the link can answer once.
 */
class SurveyController
{
    public function show(Request $request): View
    {
        $token = (string) $request->query('k', '');
        $data = $this->decode($token);

        return view('survey.form', [
            'token' => $token,
            'tenantName' => is_string($data['n'] ?? null) ? $data['n'] : null,
        ]);
    }

    public function submit(Request $request): View
    {
        $data = $this->decode((string) $request->input('k', ''));

        $validated = $request->validate([
            'service_quality' => ['nullable', 'integer', 'between:1,5'],
            'ease_of_use' => ['nullable', 'integer', 'between:1,5'],
            'responsiveness' => ['nullable', 'integer', 'between:1,5'],
            'recommend' => ['nullable', 'integer', 'between:0,10'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        SurveyResponse::query()->create([
            'tenant_id' => $data['t'] ?? null,
            'tenant_name' => $data['n'] ?? null,
            'service_quality' => $validated['service_quality'] ?? null,
            'ease_of_use' => $validated['ease_of_use'] ?? null,
            'responsiveness' => $validated['responsiveness'] ?? null,
            'recommend' => $validated['recommend'] ?? null,
            'comment' => filled($validated['comment'] ?? null) ? trim((string) $validated['comment']) : null,
            'submitted_at' => now(),
        ]);

        return view('survey.done', ['tenantName' => is_string($data['n'] ?? null) ? $data['n'] : null]);
    }

    /**
     * Decode the workspace token, or 404. The payload only ever carries the
     * workspace id/name — there is no user identity in the survey at all.
     *
     * @return array<string, mixed>
     */
    private function decode(string $token): array
    {
        if ($token === '') {
            abort(404);
        }

        try {
            $data = Crypt::decrypt($token);
        } catch (DecryptException) {
            abort(404);
        }

        return is_array($data) ? $data : abort(404);
    }

    /** Build the anonymous survey link for a workspace (used by survey:send). */
    public static function linkFor(string $tenantId, ?string $tenantName): string
    {
        return url('/survey').'?k='.urlencode(Crypt::encrypt(['t' => $tenantId, 'n' => $tenantName]));
    }
}
