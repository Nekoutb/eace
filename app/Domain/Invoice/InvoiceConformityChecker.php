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
            $this->fond('vat_calc', $this->vatCalcOk($invoice)),
            $this->fond('ttc_calc', $this->ttcCalcOk($invoice)),
            $this->fond('vat_rate', $this->vatRateOk($invoice)),
        ];

        if ($expectedSupplierNiu !== null) {
            $findings[] = $this->fond(
                'niu_match',
                $invoice->supplierNiu !== null && $invoice->supplierNiu->equals($expectedSupplierNiu),
            );
        }

        // The buyer the invoice names must be us (the organisation profile).
        if ($expectedClientNiu !== null) {
            $findings[] = $this->fond(
                'client_niu_match',
                $invoice->clientNiu !== null && $invoice->clientNiu->equals($expectedClientNiu),
            );
        }

        if ($this->filled($expectedClientName)) {
            $findings[] = $this->fond(
                'client_identity_match',
                $this->nameMatches($invoice->clientIdentity, $expectedClientName),
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
     * The invoice's client identity carries our organisation's name. Tolerant:
     * case-insensitive, whitespace-collapsed, and matches if either string
     * contains the other (the invoice often adds the legal form or an address).
     */
    private function nameMatches(?string $invoiceClient, ?string $expectedName): bool
    {
        if (! $this->filled($invoiceClient) || ! $this->filled($expectedName)) {
            return false;
        }

        $normalise = static fn (string $s): string => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $s)));
        $a = $normalise((string) $invoiceClient);
        $b = $normalise((string) $expectedName);

        return str_contains($a, $b) || str_contains($b, $a);
    }

    private function forme(string $key, bool $passed): ConformityFinding
    {
        return new ConformityFinding(ConformityType::Forme, $key, $passed);
    }

    private function fond(string $key, bool $passed): ConformityFinding
    {
        return new ConformityFinding(ConformityType::Fond, $key, $passed);
    }
}
