<?php

declare(strict_types=1);

use App\Domain\Invoice\ExemptionMention;
use App\Services\Invoice\AzureInvoiceReader;

/**
 * The reader's HTTP plumbing is thin; the mapping (mapResult) is where the risk
 * is. These tests pin the mapping against a representative Azure prebuilt-invoice
 * `analyzeResult` so a schema or field-name drift fails loudly.
 */
function azureResult(array $fields = [], string $content = ''): array
{
    return [
        'content' => $content,
        'documents' => [['fields' => $fields]],
    ];
}

function reader(): AzureInvoiceReader
{
    return new AzureInvoiceReader('https://example.cognitiveservices.azure.com', 'test-key');
}

it('maps a complete Azure invoice into InvoiceData', function () {
    $content = "FACTURE N° 42\nACME SARL\nNIU M058712345678X\nRCCM: RC/YAO/2019/B/1234\nDoit: BETA SA";

    $data = reader()->mapResult(azureResult([
        'VendorTaxId' => ['valueString' => 'M058712345678X'],
        'CustomerTaxId' => ['valueString' => 'P112233445566Q'],
        'InvoiceDate' => ['valueString' => '2026-03-15'],
        'VendorName' => ['valueString' => 'ACME SARL'],
        'VendorAddress' => ['content' => 'BP 1234 Yaoundé'],
        'CustomerName' => ['valueString' => 'BETA SA'],
        'Items' => ['content' => 'Prestation de services'],
        'SubTotal' => ['valueCurrency' => ['amount' => 1_000_000]],
        'TotalTax' => ['valueCurrency' => ['amount' => 192_500]],
        'InvoiceTotal' => ['valueCurrency' => ['amount' => 1_192_500]],
        'TaxDetails' => ['valueArray' => [['valueObject' => ['Rate' => ['valueNumber' => 19.25]]]]],
    ], $content));

    expect($data->supplierNiu?->value)->toBe('M058712345678X')
        ->and($data->clientNiu?->value)->toBe('P112233445566Q')
        ->and($data->date?->format('Y-m-d'))->toBe('2026-03-15')
        ->and($data->supplierName)->toBe('ACME SARL')
        ->and($data->supplierRccm)->toBe('RC/YAO/2019/B/1234')
        ->and($data->htAmount)->toBe(1_000_000)
        ->and($data->vatAmount)->toBe(192_500)
        ->and($data->ttcAmount)->toBe(1_192_500)
        ->and($data->vatRate)->toBe(0.1925)
        ->and($data->exemption)->toBe(ExemptionMention::None);
});

it('accepts a rate already expressed as a fraction', function () {
    $data = reader()->mapResult(azureResult([
        'SubTotal' => ['valueCurrency' => ['amount' => 1_000_000]],
        'TotalTax' => ['valueCurrency' => ['amount' => 192_500]],
        'TaxDetails' => ['valueArray' => [['valueObject' => ['Rate' => ['valueNumber' => 0.1925]]]]],
    ]));

    expect($data->vatRate)->toBe(0.1925);
});

it('derives the VAT rate from amounts when TaxDetails is absent', function () {
    $data = reader()->mapResult(azureResult([
        'SubTotal' => ['valueCurrency' => ['amount' => 1_000_000]],
        'TotalTax' => ['valueCurrency' => ['amount' => 192_500]],
    ]));

    expect($data->vatRate)->toBe(0.1925);
});

it('falls back to a NIU found in the content when VendorTaxId is missing', function () {
    $data = reader()->mapResult(azureResult([], 'Fournisseur ACME — NIU M058712345678X'));

    expect($data->supplierNiu?->value)->toBe('M058712345678X');
});

it('detects the "exonérée" mention from the content', function () {
    $data = reader()->mapResult(azureResult([], 'Opération exonérée de TVA'));

    expect($data->exemption)->toBe(ExemptionMention::Exonere);
});

it('detects the "prise en charge État" mention from the content', function () {
    $data = reader()->mapResult(azureResult([], 'TVA prise en charge par l’État'));

    expect($data->exemption)->toBe(ExemptionMention::PriseEnChargeEtat);
});

it('leaves fields null when Azure returns nothing usable', function () {
    $data = reader()->mapResult(azureResult());

    expect($data->supplierNiu)->toBeNull()
        ->and($data->clientNiu)->toBeNull()
        ->and($data->date)->toBeNull()
        ->and($data->supplierName)->toBeNull()
        ->and($data->htAmount)->toBeNull()
        ->and($data->vatRate)->toBeNull()
        ->and($data->exemption)->toBe(ExemptionMention::None);
});
