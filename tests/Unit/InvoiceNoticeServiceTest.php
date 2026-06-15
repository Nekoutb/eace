<?php

declare(strict_types=1);

use App\Services\Invoice\InvoiceNoticeService;

it('drafts an acceptance email naming the vendor and buyer', function () {
    $draft = (new InvoiceNoticeService)->draft(true, 'ACME SARL', 'MA SOCIETE SARL', []);

    expect($draft['subject'])->toBe((string) __('app.notice.accept_subject'))
        ->and($draft['body'])->toContain('ACME SARL')
        ->and($draft['body'])->toContain('MA SOCIETE SARL');
});

it('drafts a rejection email listing every discrepancy and its reason', function () {
    $draft = (new InvoiceNoticeService)->draft(false, 'ACME SARL', 'MA SOCIETE SARL', [
        ['label' => 'VAT = HT × rate', 'detail' => 'expected 192 500, invoice states 200 000'],
        ['label' => 'Supplier RCCM', 'detail' => 'mention missing'],
    ]);

    expect($draft['subject'])->toBe((string) __('app.notice.reject_subject'))
        ->and($draft['body'])->toContain('VAT = HT × rate')
        ->and($draft['body'])->toContain('expected 192 500')
        ->and($draft['body'])->toContain('Supplier RCCM');
});

it('falls back to generic salutations when names are missing', function () {
    $draft = (new InvoiceNoticeService)->draft(true, null, null, []);

    expect($draft['body'])->toContain((string) __('app.notice.the_vendor'))
        ->and($draft['body'])->toContain((string) __('app.notice.our_company'));
});
