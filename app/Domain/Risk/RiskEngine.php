<?php

declare(strict_types=1);

namespace App\Domain\Risk;

use App\Domain\Niu\FiscalisStatus;
use App\Domain\Niu\Verdict;
use App\Domain\Risk\Rules\AcfRequiredRule;
use App\Domain\Risk\Rules\CashPaymentRule;
use App\Domain\Risk\Rules\EntityTypeMismatchRule;
use App\Domain\Risk\Rules\ForeignSupplierRule;
use App\Domain\Risk\Rules\InvalidFormatRule;
use App\Domain\Risk\Rules\NameMismatchRule;
use App\Domain\Risk\Rules\NotFoundInFileRule;
use App\Domain\Risk\Rules\NotOnActiveFileRule;
use App\Domain\Risk\Rules\RegimeMismatchRule;
use App\Domain\Risk\Rules\SuspendedOrRevokedRule;

/**
 * Runs the rule set over a VerificationContext, then derives the headline
 * Verdict from the NIU's own state and downgrades a clean "Actif" to
 * "réserves" when any rule fired. Total exposure is the sum across findings —
 * an upper-bound estimate, by design.
 */
final class RiskEngine
{
    /**
     * @param  array<int, RiskRule>  $rules
     */
    public function __construct(private array $rules) {}

    public static function default(?ExposureCalculator $calc = null): self
    {
        $calc ??= new ExposureCalculator;

        return new self([
            new InvalidFormatRule($calc),
            new NotFoundInFileRule($calc),
            new SuspendedOrRevokedRule($calc),
            new NotOnActiveFileRule($calc),
            new RegimeMismatchRule($calc),
            new EntityTypeMismatchRule($calc),
            new NameMismatchRule($calc),
            new CashPaymentRule($calc),
            new AcfRequiredRule,
            new ForeignSupplierRule,
        ]);
    }

    public function evaluate(VerificationContext $context): EngineResult
    {
        $findings = [];
        foreach ($this->rules as $rule) {
            $finding = $rule->evaluate($context);
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        usort(
            $findings,
            fn (RuleFinding $a, RuleFinding $b) => [$b->severity->value, $b->exposureXaf]
                <=> [$a->severity->value, $a->exposureXaf],
        );

        $exposure = array_sum(array_map(fn (RuleFinding $f) => $f->exposureXaf, $findings));

        return new EngineResult(
            verdict: $this->deriveVerdict($context, $findings !== []),
            findings: $findings,
            totalExposureXaf: (int) $exposure,
        );
    }

    /**
     * Headline verdict precedence: format → presence in file → status → then a
     * clean Actif becomes "réserves" if any rule fired, else "conforme".
     */
    private function deriveVerdict(VerificationContext $context, bool $hasFindings): Verdict
    {
        if (! $context->hasValidNiu()) {
            return Verdict::FormatInvalide;
        }

        $registry = $context->registry;
        if ($registry === null) {
            return Verdict::Introuvable;
        }

        return match ($registry->status) {
            FiscalisStatus::Radie => Verdict::Radie,
            FiscalisStatus::Suspendu => Verdict::Suspendu,
            FiscalisStatus::Actif => $hasFindings
                ? Verdict::ConformeAvecReserves
                : Verdict::Conforme,
        };
    }
}
