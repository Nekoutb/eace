<?php

declare(strict_types=1);

namespace App\Domain\Acf;

use App\Domain\Niu\NiuNumber;
use Carbon\CarbonImmutable;

/**
 * The fields read off an ACF document. Any field may be null when the document
 * is partial or unreadable; the ingestion service decides what is mandatory.
 */
final readonly class AcfExtract
{
    public function __construct(
        public ?string $reference,
        public ?NiuNumber $niu,
        public ?string $taxpayerName,
        public ?CarbonImmutable $issuedAt,
        public ?int $validityMonths,
        public bool $hasMoratorium,
        public ?string $regime,
        public ?string $centre,
        public ?string $authenticityUrl,
    ) {}

    /** Enough to monitor a certificate: a reference and a readable issue date. */
    public function isUsable(): bool
    {
        return $this->reference !== null && $this->issuedAt !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'niu' => $this->niu?->value,
            'taxpayer_name' => $this->taxpayerName,
            'issued_at' => $this->issuedAt?->toDateString(),
            'validity_months' => $this->validityMonths,
            'has_moratorium' => $this->hasMoratorium,
            'regime' => $this->regime,
            'centre' => $this->centre,
            'authenticity_url' => $this->authenticityUrl,
        ];
    }
}
