<?php

declare(strict_types=1);

use App\Domain\Niu\Verdict;

it('has a French label for every case', function (Verdict $verdict) {
    expect($verdict->label())->toBeString()->not->toBeEmpty();
})->with(Verdict::cases());

it('maps every case to a registered colour key', function (Verdict $verdict) {
    expect($verdict->color())->toBeIn(['success', 'warning', 'suspended', 'danger', 'gray']);
})->with(Verdict::cases());

it('maps every case to an outline heroicon', function (Verdict $verdict) {
    expect($verdict->icon())->toStartWith('heroicon-o-');
})->with(Verdict::cases());

describe('payment gating', function () {
    it('does not block a conforme NIU', function () {
        expect(Verdict::Conforme->blocksPayment())->toBeFalse();
    });

    it('does not block conforme-avec-réserves but flags it for attention', function () {
        expect(Verdict::ConformeAvecReserves->blocksPayment())->toBeFalse()
            ->and(Verdict::ConformeAvecReserves->requiresAttention())->toBeTrue();
    });

    it('blocks suspendu, radié, introuvable and format invalide', function (Verdict $verdict) {
        expect($verdict->blocksPayment())->toBeTrue();
    })->with([
        Verdict::Suspendu,
        Verdict::Radie,
        Verdict::Introuvable,
        Verdict::FormatInvalide,
    ]);

    it('treats only a clean conforme as needing no attention', function () {
        expect(Verdict::Conforme->requiresAttention())->toBeFalse();
    });
});
