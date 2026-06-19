<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Carbon;

/**
 * A support question raised by tenant staff from the home-page chat box. Lives on
 * the central database (CentralModel) so the SaaS owner can triage every
 * workspace's questions from one admin inbox; the optional response is shown back
 * to the submitter inside their own workspace.
 *
 * @property ?string $tenant_id
 * @property ?string $tenant_name
 * @property ?string $user_name
 * @property ?string $user_email
 * @property string $message
 * @property ?string $response
 * @property string $status
 * @property ?Carbon $answered_at
 */
class SupportQuery extends CentralModel
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    public function isAnswered(): bool
    {
        return $this->status === 'answered';
    }
}
