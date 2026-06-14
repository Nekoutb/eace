<?php

declare(strict_types=1);

use App\Domain\Invoice\ExemptionMention;
use App\Services\Invoice\ClaudeInvoiceReader;

/**
 * The HTTP call is thin; the risk is the mapping of Claude's extract_invoice
 * tool input into InvoiceData. Pin it against a representative tool payload.
 */
function claudeReader(): ClaudeInvoiceReader
{
    return new ClaudeInvoiceReader('test-key', 'claude-sonnet-4-6');
}

it('maps a complete extract_invoice tool input into InvoiceData', function () {
    $data = claudeReader()->mapResult([
        'supplier_niu' => 'M058712345678X',
        'client_niu' => 'M020100021296Z',
        'date' => '2026-03-15',
        'supplier_name' => 'ACME SARL',
        'supplier_address' => 'BP 1234 Yaoundé',
        'supplier_rccm' => 'RC/YAO/2019/B/1234',
        'client_identity' => 'EXPRESS UNION FINANCE SA',
        'description' => 'Prestation de services',
        'ht_amount' => 1_000_000,
        'vat_rate' => 0.1925,
        'vat_amount' => 192_500,
        'ttc_amount' => 1_192_500,
        'exemption' => 'none',
    ]);

    expect($data->supplierNiu?->value)->toBe('M058712345678X')
        ->and($data->clientNiu?->value)->toBe('M020100021296Z')
        ->and($data->date?->format('Y-m-d'))->toBe('2026-03-15')
        ->and($data->supplierName)->toBe('ACME SARL')
        ->and($data->supplierRccm)->toBe('RC/YAO/2019/B/1234')
        ->and($data->htAmount)->toBe(1_000_000)
        ->and($data->vatAmount)->toBe(192_500)
        ->and($data->ttcAmount)->toBe(1_192_500)
        ->and($data->vatRate)->toBe(0.1925)
        ->and($data->exemption)->toBe(ExemptionMention::None);
});

it('maps the exemption enum and tolerates missing fields', function () {
    $data = claudeReader()->mapResult([
        'supplier_niu' => 'M058712345678X',
        'exemption' => 'prise_en_charge_etat',
    ]);

    expect($data->exemption)->toBe(ExemptionMention::PriseEnChargeEtat)
        ->and($data->clientNiu)->toBeNull()
        ->and($data->ttcAmount)->toBeNull()
        ->and($data->supplierName)->toBeNull();
});
