<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;

/**
 * Immutable, append-only snapshot of a Fiscalis response (central DB). Carries
 * the evidence: parsed payload, stored raw HTML/screenshot, and a SHA-256
 * content hash chained to the previous snapshot of the same NIU.
 *
 * @property string $niu
 * @property array<string, mixed> $payload
 * @property string $content_hash
 * @property ?string $previous_hash
 * @property Carbon $fetched_at
 */
class NiuRegistrySnapshot extends CentralModel
{
    use HasUuids;

    public const UPDATED_AT = null; // immutable: only created_at is managed

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * Canonical SHA-256 of a payload, used for both content_hash and chaining.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function hashPayload(array $payload): string
    {
        ksort($payload);

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
