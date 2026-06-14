<?php

declare(strict_types=1);

namespace App\Domain\Approval;

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfStatus;
use App\Domain\Niu\FiscalisStatus;
use App\Domain\Niu\NiuNumber;

/**
 * Everything the four checks need, decoupled from Eloquent so the evaluator is
 * unit-testable. The service maps a registry record + an AcfCertificate into this.
 * A null acfStatus/acfAuthenticity means no ACF was supplied for this check.
 */
final readonly class ApprovalContext
{
    public function __construct(
        public VerificationStage $stage,
        public ?NiuNumber $niu,
        public bool $foundInFile,
        public ?FiscalisStatus $fiscalisStatus,
        public bool $onActiveFile,
        public ?AcfStatus $acfStatus,
        public ?AcfAuthenticityStatus $acfAuthenticity,
    ) {}
}
