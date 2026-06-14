<?php

declare(strict_types=1);

namespace App\Domain\Risk;

use App\Domain\Niu\FiscalisStatus;
use App\Domain\Niu\NiuEntityType;

/**
 * A pure snapshot of what the Fiscalis file holds for a NIU, decoupled from
 * Eloquent so the rules engine stays unit-testable without a database. The
 * service layer maps an App\Models\NiuRegistry row into this. A null
 * RegistryRecord on the context means "not found in the file".
 */
final readonly class RegistryRecord
{
    public function __construct(
        public ?string $taxpayerName,
        public FiscalisStatus $status,
        public bool $onActiveFile,
        public ?NiuEntityType $entityType = null,
        public ?string $regime = null,
        public ?string $sigle = null,
        public ?string $centre = null,
        public ?string $activity = null,
        public ?string $source = null,
    ) {}

    /** Whether the registered régime fiscal is "réel" (a deductibility condition). */
    public function isRegimeReel(): bool
    {
        return $this->regime !== null
            && str_contains(mb_strtolower($this->regime), 'réel');
    }
}
