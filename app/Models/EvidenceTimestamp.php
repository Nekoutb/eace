<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Evidence\TimestampStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-owned. The RFC-3161 trusted-timestamp seal for one evidence capture,
 * keyed by the stored image (disk + path). Holds the sealed SHA-256, the signed
 * token's path, and the authority-asserted instant.
 *
 * @property string $disk
 * @property string $path
 * @property string $sha256
 * @property string $algo
 * @property TimestampStatus $status
 * @property ?string $tsa_url
 * @property ?string $token_path
 * @property ?CarbonImmutable $timestamped_at
 * @property int $attempts
 * @property ?string $last_error
 */
class EvidenceTimestamp extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TimestampStatus::class,
            'timestamped_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }

    public function isSigned(): bool
    {
        return $this->status === TimestampStatus::Signed;
    }

    /** The seal recorded for a stored evidence image, if any. */
    public static function forPath(?string $path, string $disk = 'local'): ?self
    {
        if (! filled($path)) {
            return null;
        }

        return static::query()->where('disk', $disk)->where('path', $path)->first();
    }
}
