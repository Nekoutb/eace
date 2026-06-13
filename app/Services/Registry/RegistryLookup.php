<?php

declare(strict_types=1);

namespace App\Services\Registry;

use App\Domain\Risk\RegistryRecord;

/**
 * The outcome of looking a NIU up in the central registry: the resolved record
 * (null when not in the file) and the id of the immutable snapshot that
 * captured it (null when nothing was captured).
 */
final readonly class RegistryLookup
{
    public function __construct(
        public ?RegistryRecord $record,
        public ?string $snapshotId,
    ) {}

    public function found(): bool
    {
        return $this->record !== null;
    }
}
