<?php

declare(strict_types=1);

use App\Domain\Niu\FiscalisStatus;
use App\Domain\Niu\NiuEntityType;
use App\Domain\Niu\NiuNumber;
use App\Domain\Niu\Verdict;
use App\Domain\Risk\PaymentMethod;
use App\Domain\Risk\RegistryRecord;
use App\Domain\Risk\RiskEngine;
use App\Domain\Risk\VerificationContext;

beforeEach(function () {
    $this->engine = RiskEngine::default();
});

/** Build an "Actif, on active file, régime réel" record unless overridden. */
function record(
    FiscalisStatus $status = FiscalisStatus::Actif,
    bool $onActiveFile = true,
    string $regime = 'Réel',
    string $name = 'SARL EXEMPLE',
    ?NiuEntityType $entityType = NiuEntityType::PersonneMorale,
): RegistryRecord {
    return new RegistryRecord(
        taxpayerName: $name,
        status: $status,
        onActiveFile: $onActiveFile,
        entityType: $entityType,
        regime: $regime,
    );
}

describe('verdict derivation', function () {
    it('returns FORMAT_INVALIDE when the input is not a NIU', function () {
        $result = $this->engine->evaluate(new VerificationContext(rawInput: 'not-a-niu'));

        expect($result->verdict)->toBe(Verdict::FormatInvalide)
            ->and($result->findings[0]->code)->toBe('R1');
    });

    it('returns INTROUVABLE when the NIU is valid but absent from the file', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: null,
        ));

        expect($result->verdict)->toBe(Verdict::Introuvable)
            ->and(collect($result->findings)->pluck('code'))->toContain('R2');
    });

    it('returns RADIE when the file shows a struck-off NIU', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: record(status: FiscalisStatus::Radie),
        ));

        expect($result->verdict)->toBe(Verdict::Radie);
    });

    it('returns SUSPENDU when the file shows a suspended NIU', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: record(status: FiscalisStatus::Suspendu),
        ));

        expect($result->verdict)->toBe(Verdict::Suspendu);
    });

    it('returns CONFORME for a clean active NIU with no invoice context', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: record(),
        ));

        expect($result->verdict)->toBe(Verdict::Conforme)
            ->and($result->findings)->toBeEmpty()
            ->and($result->totalExposureXaf)->toBe(0);
    });

    it('downgrades to CONFORME_AVEC_RESERVES when an active NIU trips a rule', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: record(),
            invoiceName: 'UNE TOUTE AUTRE ENTREPRISE', // name mismatch -> R7
        ));

        expect($result->verdict)->toBe(Verdict::ConformeAvecReserves)
            ->and(collect($result->findings)->pluck('code'))->toContain('R7');
    });
});

describe('rule firing', function () {
    it('fires R4 when the NIU is active but off the active file', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: record(onActiveFile: false),
        ));

        expect(collect($result->findings)->pluck('code'))->toContain('R4')
            ->and($result->verdict)->toBe(Verdict::ConformeAvecReserves);
    });

    it('fires R5 when VAT is charged by a non-réel supplier', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: record(regime: 'Simplifié'),
            vatCharged: true,
        ));

        expect(collect($result->findings)->pluck('code'))->toContain('R5');
    });

    it('fires R6 when a company presents a personne-physique NIU', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'P058712345678X',
            niu: NiuNumber::fromString('P058712345678X'),
            registry: record(entityType: NiuEntityType::PersonnePhysique),
            expectedEntityType: NiuEntityType::PersonneMorale,
        ));

        expect(collect($result->findings)->pluck('code'))->toContain('R6');
    });

    it('fires R9 for a cash payment at or above 100 000 F', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: record(),
            invoiceAmountXaf: 250_000,
            paymentMethod: PaymentMethod::Cash,
        ));

        expect(collect($result->findings)->pluck('code'))->toContain('R9');
    });

    it('fires R10 when a public settlement lacks a valid ACF', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: record(),
            publicSettlementOrExport: true,
            hasValidAcf: false,
        ));

        expect(collect($result->findings)->pluck('code'))->toContain('R10');
    });

    it('fires R11 for a foreign supplier and estimates the 15% TSR', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: record(),
            invoiceAmountXaf: 1_000_000,
            foreignSupplier: true,
        ));

        $r11 = collect($result->findings)->firstWhere('code', 'R11');
        expect($r11)->not->toBeNull()
            ->and($r11->exposureXaf)->toBe(150_000);
    });

    it('does not fire R7 when invoice and file names match despite accents/case', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: record(name: 'Société Générale du Cameroun'),
            invoiceName: 'SOCIETE GENERALE DU CAMEROUN',
        ));

        expect(collect($result->findings)->pluck('code'))->not->toContain('R7')
            ->and($result->verdict)->toBe(Verdict::Conforme);
    });
});

describe('exposure aggregation', function () {
    it('sums exposure across findings and sorts strongest first', function () {
        $result = $this->engine->evaluate(new VerificationContext(
            rawInput: 'M058712345678X',
            niu: NiuNumber::fromString('M058712345678X'),
            registry: record(onActiveFile: false), // R4 (blocking)
            invoiceAmountXaf: 1_000_000,
            invoiceName: 'AUTRE NOM',               // R7 (warning)
            paymentMethod: PaymentMethod::Cash,      // R9 (blocking)
        ));

        expect($result->totalExposureXaf)->toBeGreaterThan(0)
            ->and($result->findings[0]->severity->value)
            ->toBeGreaterThanOrEqual($result->findings[count($result->findings) - 1]->severity->value);
    });
});
