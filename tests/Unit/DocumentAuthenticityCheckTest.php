<?php

declare(strict_types=1);

use App\Domain\Acf\AcfAuthenticityStatus;
use App\Domain\Acf\AcfStatus;
use App\Domain\Approval\ApprovalContext;
use App\Domain\Approval\Checks\AcfValidityCheck;
use App\Domain\Approval\Checks\DocumentAuthenticityCheck;
use App\Domain\Approval\VerificationStage;
use App\Domain\Niu\FiscalisStatus;
use App\Domain\Niu\NiuNumber;

/**
 * The authenticity check (1) and the validity check (4) read the SAME
 * certificate on two axes (Harmony2 verdict vs dates). Their commentary must be
 * specific about why, and must never contradict — in particular a present ACF
 * must never be described by check 1 as "absent" while check 4 reports it valid.
 */
function authCtx(array $o = []): ApprovalContext
{
    return new ApprovalContext(
        stage: $o['stage'] ?? VerificationStage::Settlement,
        niu: NiuNumber::fromString('M058712345678X'),
        foundInFile: true,
        fiscalisStatus: FiscalisStatus::Actif,
        onActiveFile: true,
        acfStatus: array_key_exists('acfStatus', $o) ? $o['acfStatus'] : AcfStatus::Valid,
        acfAuthenticity: array_key_exists('acfAuthenticity', $o) ? $o['acfAuthenticity'] : AcfAuthenticityStatus::Unverified,
    );
}

it('reports a present-but-unconfirmed ACF specifically — never as "absent", never contradicting a valid check 4', function () {
    // The screenshot case: ACF present and in date, Harmony2 could not confirm.
    $ctx = authCtx(['acfStatus' => AcfStatus::Valid, 'acfAuthenticity' => AcfAuthenticityStatus::Unverified]);

    $auth = (new DocumentAuthenticityCheck)->evaluate($ctx);
    $validity = (new AcfValidityCheck)->evaluate($ctx);

    expect($auth->passed)->toBeFalse()
        ->and($auth->detail)->toBe((string) __('app.check.msg.auth_unconfirmed'))
        // Must acknowledge the certificate is present, and must NOT claim absence.
        ->and($auth->detail)->not->toBe((string) __('app.check.msg.validity_none'))
        ->and($auth->detail)->not->toBe((string) __('app.check.msg.validity_absent_settlement'))
        // Consistent with check 4 passing "valid".
        ->and($validity->passed)->toBeTrue();
});

it('speaks the SAME "absent" wording as the validity check when no ACF exists', function (VerificationStage $stage) {
    $ctx = authCtx(['stage' => $stage, 'acfStatus' => null, 'acfAuthenticity' => null]);

    $auth = (new DocumentAuthenticityCheck)->evaluate($ctx);
    $validity = (new AcfValidityCheck)->evaluate($ctx);

    // Identical detail on both checks, and the same "not performed vs fail" shape.
    expect($auth->detail)->toBe($validity->detail)
        ->and($auth->passed)->toBe($validity->passed);
})->with([
    'settlement' => VerificationStage::Settlement,
    'recording' => VerificationStage::Recording,
]);

it('still fails a genuinely non-authentic document with the not-authentic message', function () {
    $auth = (new DocumentAuthenticityCheck)->evaluate(
        authCtx(['acfStatus' => AcfStatus::NotAuthentic, 'acfAuthenticity' => AcfAuthenticityStatus::NotAuthentic]),
    );

    expect($auth->passed)->toBeFalse()
        ->and($auth->detail)->toBe((string) __('app.check.msg.auth_not_authentic'));
});

it('passes an authentic document', function () {
    $auth = (new DocumentAuthenticityCheck)->evaluate(
        authCtx(['acfStatus' => AcfStatus::Valid, 'acfAuthenticity' => AcfAuthenticityStatus::Authentic]),
    );

    expect($auth->passed)->toBeTrue()
        ->and($auth->detail)->toBe((string) __('app.check.msg.auth_authentic'));
});
