<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfStatus;
use App\Domain\Approval\ApprovalContext;
use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\CheckKey;
use App\Domain\Approval\FourCheckEvaluator;
use App\Domain\Approval\VerificationStage;
use App\Domain\Niu\FiscalisStatus;
use App\Domain\Niu\NiuNumber;

/** An all-passing context; override one field per test to make a single check fail. */
function approvalCtx(array $o = []): ApprovalContext
{
    return new ApprovalContext(
        stage: $o['stage'] ?? VerificationStage::Recording,
        niu: array_key_exists('niu', $o) ? $o['niu'] : NiuNumber::fromString('M058712345678X'),
        foundInFile: $o['foundInFile'] ?? true,
        fiscalisStatus: array_key_exists('fiscalisStatus', $o) ? $o['fiscalisStatus'] : FiscalisStatus::Actif,
        onActiveFile: $o['onActiveFile'] ?? true,
        acfStatus: array_key_exists('acfStatus', $o) ? $o['acfStatus'] : AcfStatus::Valid,
        acfAuthenticity: array_key_exists('acfAuthenticity', $o) ? $o['acfAuthenticity'] : AcfAuthenticityStatus::Authentic,
    );
}

beforeEach(function () {
    $this->evaluator = FourCheckEvaluator::default();
});

it('accepts only when all four checks pass', function () {
    $result = $this->evaluator->evaluate(approvalCtx());

    expect($result->decision)->toBe(ApprovalDecision::Accepted)
        ->and($result->accepted())->toBeTrue()
        ->and($result->checks)->toHaveCount(4)
        ->and($result->failedChecks())->toBeEmpty();
});

it('rejects when authenticity is unverified', function () {
    $result = $this->evaluator->evaluate(approvalCtx(['acfAuthenticity' => AcfAuthenticityStatus::Unverified]));

    expect($result->decision)->toBe(ApprovalDecision::Rejected)
        ->and(collect($result->failedChecks())->pluck('key'))->toContain(CheckKey::DocumentAuthenticity);
});

it('rejects when the document is not authentic', function () {
    expect($this->evaluator->evaluate(approvalCtx(['acfAuthenticity' => AcfAuthenticityStatus::NotAuthentic]))->decision)
        ->toBe(ApprovalDecision::Rejected);
});

it('rejects a suspended or struck-off NIU', function (FiscalisStatus $status) {
    expect($this->evaluator->evaluate(approvalCtx(['fiscalisStatus' => $status]))->decision)
        ->toBe(ApprovalDecision::Rejected);
})->with([FiscalisStatus::Suspendu, FiscalisStatus::Radie]);

it('rejects a NIU absent from the file', function () {
    $result = $this->evaluator->evaluate(approvalCtx(['foundInFile' => false, 'fiscalisStatus' => null]));

    expect($result->decision)->toBe(ApprovalDecision::Rejected)
        ->and(collect($result->failedChecks())->pluck('key'))->toContain(CheckKey::NiuExistence);
});

it('blocks an expiring-soon ACF at settlement but accepts it at recording', function () {
    $settlement = $this->evaluator->evaluate(approvalCtx([
        'stage' => VerificationStage::Settlement,
        'acfStatus' => AcfStatus::ExpiringSoon,
    ]));
    $recording = $this->evaluator->evaluate(approvalCtx([
        'stage' => VerificationStage::Recording,
        'acfStatus' => AcfStatus::ExpiringSoon,
    ]));

    expect($settlement->decision)->toBe(ApprovalDecision::Rejected)
        ->and(collect($settlement->failedChecks())->pluck('key'))->toContain(CheckKey::AcfValidity)
        ->and($recording->decision)->toBe(ApprovalDecision::Accepted);
});

it('rejects a NIU that is active but off the fichier des contribuables actifs', function () {
    $result = $this->evaluator->evaluate(approvalCtx(['onActiveFile' => false]));

    expect($result->decision)->toBe(ApprovalDecision::Rejected)
        ->and(collect($result->failedChecks())->pluck('key'))->toContain(CheckKey::NiuStatus);
});

it('rejects an expired ACF', function () {
    expect($this->evaluator->evaluate(approvalCtx(['acfStatus' => AcfStatus::Expired]))->decision)
        ->toBe(ApprovalDecision::Rejected);
});

it('rejects when no ACF is attached', function () {
    expect($this->evaluator->evaluate(approvalCtx(['acfStatus' => null, 'acfAuthenticity' => null]))->decision)
        ->toBe(ApprovalDecision::Rejected);
});

it('serialises the four checks with key, article, passed and detail', function () {
    $checks = $this->evaluator->evaluate(approvalCtx())->checksToArray();

    expect($checks)->toHaveCount(4)
        ->and($checks[0])->toHaveKeys(['key', 'label', 'article', 'passed', 'detail']);
});

it('marks both ACF checks "not performed" (null) when no certificate exists at settlement', function () {
    $result = $this->evaluator->evaluate(approvalCtx([
        'stage' => VerificationStage::Settlement,
        'acfStatus' => null,
        'acfAuthenticity' => null,
    ]));

    $byKey = fn (CheckKey $key) => collect($result->checks)->first(fn ($c) => $c->key === $key);

    // The checks could not run — but that never amounts to an acceptance.
    expect($result->decision)->toBe(ApprovalDecision::Rejected)
        ->and($byKey(CheckKey::AcfValidity)->passed)->toBeNull()
        ->and($byKey(CheckKey::DocumentAuthenticity)->passed)->toBeNull();
});

it('keeps the plain red failure for a missing certificate at recording', function () {
    $result = $this->evaluator->evaluate(approvalCtx([
        'stage' => VerificationStage::Recording,
        'acfStatus' => null,
        'acfAuthenticity' => null,
    ]));

    $validity = collect($result->checks)->first(fn ($c) => $c->key === CheckKey::AcfValidity);

    expect($result->decision)->toBe(ApprovalDecision::Rejected)
        ->and($validity->passed)->toBeFalse();
});

it('does not treat an unverified EXISTING certificate as "not performed" at settlement', function () {
    $result = $this->evaluator->evaluate(approvalCtx([
        'stage' => VerificationStage::Settlement,
        'acfStatus' => AcfStatus::Valid,
        'acfAuthenticity' => AcfAuthenticityStatus::Unverified,
    ]));

    $authenticity = collect($result->checks)->first(fn ($c) => $c->key === CheckKey::DocumentAuthenticity);

    expect($authenticity->passed)->toBeFalse();
});
