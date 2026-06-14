<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

use App\Domain\Niu\NiuNumber;

/**
 * Checks an invoice against CGI art. 150 — both the conditions de forme (the
 * mandatory mentions are present) and the conditions de fond (the figures are
 * coherent and the VAT is valid). Pure and unit-testable; the extraction that
 * builds the InvoiceData lives elsewhere.
 */
final class InvoiceConformityChecker
{
    public const VAT_RATE = 0.1925;

    private const AMOUNT_TOLERANCE = 2; // FCFA, absorbs rounding

    public function check(
        InvoiceData $invoice,
        ?NiuNumber $expectedSupplierNiu = null,
        ?NiuNumber $expectedClientNiu = null,
        ?string $expectedClientName = null,
        ?string $expectedClientRccm = null,
        ?string $expectedClientAddress = null,
    ): InvoiceConformityResult {
        $findings = [
            // Conditions de forme — every mandatory mention of art. 150 present.
            $this->forme('supplier_niu', $invoice->supplierNiu !== null),
            $this->forme('client_niu', $invoice->clientNiu !== null),
            $this->forme('date', $invoice->date !== null),
            $this->forme('supplier_name', $this->filled($invoice->supplierName)),
            $this->forme('supplier_address', $this->filled($invoice->supplierAddress)),
            $this->forme('supplier_rccm', $this->filled($invoice->supplierRccm)),
            $this->forme('client_identity', $this->filled($invoice->clientIdentity)),
            $this->forme('description', $this->filled($invoice->description)),
            $this->forme('ht', $invoice->htAmount !== null),
            $this->forme('vat', $invoice->isExempt() || ($invoice->vatAmount !== null && $invoice->vatRate !== null)),
            $this->forme('ttc', $invoice->ttcAmount !== null),

            // Conditions de fond — figures coherent, VAT valid.
            $this->fond('vat_calc', $this->vatCalcOk($invoice), $this->vatCalcDetail($invoice)),
            $this->fond('ttc_calc', $this->ttcCalcOk($invoice), $this->ttcCalcDetail($invoice)),
            $this->fond('vat_rate', $this->vatRateOk($invoice), $this->vatRateDetail($invoice)),
        ];

        if ($expectedSupplierNiu !== null) {
            $findings[] = $this->fond(
                'niu_match',
                $invoice->supplierNiu !== null && $invoice->supplierNiu->equals($expectedSupplierNiu),
                $this->matchDetail($invoice->supplierNiu !== null ? (string) $invoice->supplierNiu : null, (string) $expectedSupplierNiu),
            );
        }

        // The buyer the invoice names must be us (the organisation profile).
        if ($expectedClientNiu !== null) {
            $findings[] = $this->fond(
                'client_niu_match',
                $invoice->clientNiu !== null && $invoice->clientNiu->equals($expectedClientNiu),
                $this->matchDetail($invoice->clientNiu !== null ? (string) $invoice->clientNiu : null, (string) $expectedClientNiu),
            );
        }

        if ($this->filled($expectedClientName)) {
            $findings[] = $this->fond(
                'client_identity_match',
                $this->nameMatches($invoice->clientIdentity, $expectedClientName),
                $this->matchDetail($invoice->clientIdentity, $expectedClientName),
            );
        }

        if ($this->filled($expectedClientRccm)) {
            $findings[] = $this->fond(
                'client_rccm_match',
                $this->nameMatches($invoice->clientRccm, $expectedClientRccm),
                $this->matchDetail($invoice->clientRccm, $expectedClientRccm),
            );
        }

        if ($this->filled($expectedClientAddress)) {
            $findings[] = $this->fond(
                'client_address_match',
                $this->nameMatches($invoice->clientAddress, $expectedClientAddress),
                $this->matchDetail($invoice->clientAddress, $expectedClientAddress),
            );
        }

        $conform = array_reduce($findings, fn (bool $c, ConformityFinding $f): bool => $c && $f->passed, true);

        return new InvoiceConformityResult($conform, $findings);
    }

    /** HT × taux = montant de la TVA (or no VAT when exempt). */
    private function vatCalcOk(InvoiceData $invoice): bool
    {
        if ($invoice->isExempt()) {
            return ($invoice->vatAmount ?? 0) === 0;
        }

        if ($invoice->htAmount === null || $invoice->vatRate === null || $invoice->vatAmount === null) {
            return false;
        }

        return abs((int) round($invoice->htAmount * $invoice->vatRate) - $invoice->vatAmount) <= self::AMOUNT_TOLERANCE;
    }

    /** HT + TVA = montant total TTC. */
    private function ttcCalcOk(InvoiceData $invoice): bool
    {
        if ($invoice->htAmount === null || $invoice->ttcAmount === null) {
            return false;
        }

        $vat = $invoice->isExempt() ? 0 : $invoice->vatAmount;

        if ($vat === null) {
            return false;
        }

        return abs(($invoice->htAmount + $vat) - $invoice->ttcAmount) <= self::AMOUNT_TOLERANCE;
    }

    /** The legal common rate (19.25 %), or no rate when exempt. */
    private function vatRateOk(InvoiceData $invoice): bool
    {
        if ($invoice->isExempt()) {
            return $invoice->vatRate === null || abs($invoice->vatRate) < 0.0001;
        }

        return $invoice->vatRate !== null && abs($invoice->vatRate - self::VAT_RATE) < 0.0001;
    }

    private function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    /**
     * Tolerant comparison used for the client name, RCCM and address: lower-cased,
     * punctuation stripped, whitespace collapsed, and a match if either string
     * contains the other (the invoice often adds the legal form or extra detail).
     */
    private function nameMatches(?string $invoiceValue, ?string $expected): bool
    {
        if (! $this->filled($invoiceValue) || ! $this->filled($expected)) {
            return false;
        }

        $normalise = static function (string $s): string {
            $s = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s); // drop punctuation
            $s = (string) preg_replace('/\s+/u', ' ', $s);

            return mb_strtolower(trim($s));
        };

        $a = $normalise((string) $invoiceValue);
        $b = $normalise((string) $expected);

        return $a !== '' && $b !== '' && (str_contains($a, $b) || str_contains($b, $a));
    }

    /** "VAT = HT × rate = expected; invoice states actual (variance …)." */
    private function vatCalcDetail(InvoiceData $invoice): string
    {
        if ($invoice->isExempt()) {
            return (string) __('app.conformity.narr.vat_exempt');
        }

        if ($invoice->htAmount === null || $invoice->vatRate === null || $invoice->vatAmount === null) {
            return (string) __('app.conformity.narr.missing_amounts');
        }

        $expected = (int) round($invoice->htAmount * $invoice->vatRate);

        return (string) __('app.conformity.narr.vat_calc', [
            'ht' => $this->fmt($invoice->htAmount),
            'rate' => $this->pct($invoice->vatRate),
            'expected' => $this->fmt($expected),
            'actual' => $this->fmt($invoice->vatAmount),
            'variance' => $this->fmt(abs($invoice->vatAmount - $expected)),
        ]);
    }

    /** "TTC = HT + VAT = expected; invoice states actual (variance …)." */
    private function ttcCalcDetail(InvoiceData $invoice): string
    {
        if ($invoice->htAmount === null || $invoice->ttcAmount === null) {
            return (string) __('app.conformity.narr.missing_amounts');
        }

        $vat = $invoice->isExempt() ? 0 : ($invoice->vatAmount ?? 0);
        $expected = $invoice->htAmount + $vat;

        return (string) __('app.conformity.narr.ttc_calc', [
            'ht' => $this->fmt($invoice->htAmount),
            'vat' => $this->fmt($vat),
            'expected' => $this->fmt($expected),
            'actual' => $this->fmt($invoice->ttcAmount),
            'variance' => $this->fmt(abs($invoice->ttcAmount - $expected)),
        ]);
    }

    private function vatRateDetail(InvoiceData $invoice): string
    {
        if ($invoice->isExempt()) {
            return (string) __('app.conformity.narr.vat_rate_exempt');
        }

        return (string) __('app.conformity.narr.vat_rate', [
            'legal' => $this->pct(self::VAT_RATE),
            'actual' => $invoice->vatRate !== null ? $this->pct($invoice->vatRate) : '—',
        ]);
    }

    /** "Invoice: <value> · your profile / verified: <expected>." */
    private function matchDetail(?string $invoiceValue, ?string $expected): string
    {
        return (string) __('app.conformity.narr.match', [
            'invoice' => $this->filled($invoiceValue) ? (string) $invoiceValue : '—',
            'expected' => $this->filled($expected) ? (string) $expected : '—',
        ]);
    }

    private function fmt(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' FCFA';
    }

    private function pct(float $rate): string
    {
        $formatted = rtrim(rtrim(number_format($rate * 100, 2, ',', ''), '0'), ',');

        return $formatted.' %';
    }

    private function forme(string $key, bool $passed): ConformityFinding
    {
        $detail = (string) __($passed ? 'app.conformity.narr.present' : 'app.conformity.narr.absent', [
            'mention' => (string) __('app.conformity.'.$key),
        ]);

        return new ConformityFinding(ConformityType::Forme, $key, $passed, $detail);
    }

    private function fond(string $key, bool $passed, ?string $detail = null): ConformityFinding
    {
        return new ConformityFinding(ConformityType::Fond, $key, $passed, $detail);
    }
}
