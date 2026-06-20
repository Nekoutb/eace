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

    /** The seal recorded for a stored evidence reference, if any. */
    public static function forPath(?string $ref): ?self
    {
        if (! filled($ref)) {
            return null;
        }

        // The ref (e.g. "wasabi::t/eace/…") is unique on its own — it encodes the
        // disk — so match on it alone rather than filtering by the disk column.
        return static::query()->where('path', $ref)->first();
    }
}
