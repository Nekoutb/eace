<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

use App\Domain\Acf\VendorRegime;
use App\Domain\Niu\NiuNumber;

/**
 * Checks an invoice against CGI art. 150 — the conditions de forme (mandatory
 * mentions present) and de fond (figures coherent). VAT controls apply ONLY to a
 * vendor under the Régime du Réel: for any positively non-Réel vendor (Simplifié
 * /RSI, IGS, non-business) VAT is ignored entirely — no VAT checks run at all,
 * whether or not VAT is billed. Pure and unit-testable; the extraction that
 * builds the InvoiceData lives elsewhere.
 */
final class InvoiceConformityChecker
{
    public const VAT_RATE = 0.1925;

    private const AMOUNT_TOLERANCE = 2; // FCFA, absorbs rounding

    /** Set per check(): the user attested the operation is VAT-exempt (CGI). */
    private bool $attested = false;

    /** Exempt by the invoice's own mention, or by the user's attestation. */
    private function treatExempt(InvoiceData $invoice): bool
    {
        return $invoice->isExempt() || $this->attested;
    }

    /**
     * @param  array<int, string>  $acceptedClientNames  every accepted spelling of
     *                                                   the buyer's own name (the
     *                                                   primary name plus admin-
     *                                                   entered variations); when
     *                                                   empty, $expectedClientName
     *                                                   alone is used.
     */
    public function check(
        InvoiceData $invoice,
        ?NiuNumber $expectedSupplierNiu = null,
        ?NiuNumber $expectedClientNiu = null,
        ?string $expectedClientName = null,
        ?string $expectedClientAddress = null,
        array $acceptedClientNames = [],
        ?string $vendorRegime = null,
        bool $userAttestedVatExempt = false,
    ): InvoiceConformityResult {
        $this->attested = $userAttestedVatExempt;

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
            $this->forme('ttc', $invoice->ttcAmount !== null),
        ];

        // VAT and totals — gated by the vendor's régime (see vatFindings).
        foreach ($this->vatFindings($invoice, $vendorRegime) as $finding) {
            $findings[] = $finding;
        }

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

        // Check the buyer identity whenever the profile defines a primary name OR
        // any accepted variation — an aliases-only profile must still be enforced.
        if ($this->filled($expectedClientName) || $acceptedClientNames !== []) {
            // Accept the primary buyer name OR any admin-registered variation.
            $candidates = $acceptedClientNames !== [] ? $acceptedClientNames : [(string) $expectedClientName];

            $findings[] = $this->fond(
                'client_identity_match',
                $this->nameMatchesAny($invoice->clientIdentity, $candidates),
                $this->matchDetail($invoice->clientIdentity, $this->filled($expectedClientName) ? $expectedClientName : ($candidates[0] ?? null)),
            );
        }

        // Deliberately NO client-RCCM control: CGI art. 150 requires the
        // trade-register number of the SUPPLIER only — a customer's RCCM is not a
        // legal invoice mention, so its absence must never reject an invoice.

        if ($this->filled($expectedClientAddress)) {
            $findings[] = $this->fond(
                'client_address_match',
                $this->addressMatches($invoice->clientAddress, $expectedClientAddress),
                $this->matchDetail($invoice->clientAddress, $expectedClientAddress),
            );
        }

        $conform = array_reduce($findings, fn (bool $c, ConformityFinding $f): bool => $c && $f->passed, true);

        return new InvoiceConformityResult($conform, $findings);
    }

    /** HT × taux = montant de la TVA (or no VAT when exempt). */
    private function vatCalcOk(InvoiceData $invoice): bool
    {
        if ($this->treatExempt($invoice)) {
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

        $vat = $this->treatExempt($invoice) ? 0 : $invoice->vatAmount;

        if ($vat === null) {
            return false;
        }

        return abs(($invoice->htAmount + $vat) - $invoice->ttcAmount) <= self::AMOUNT_TOLERANCE;
    }

    /** The legal common rate (19.25 %), or no rate when exempt. */
    private function vatRateOk(InvoiceData $invoice): bool
    {
        if ($this->treatExempt($invoice)) {
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

    /**
     * Address comparison — deliberately order-INSENSITIVE. An address names the
     * same place whether it is written « Rue Bertaut Bali » or « Bali Rue
     * Bertaut », so the two sides are compared as WORD SETS rather than as
     * substrings: it matches when every word of one side appears in the other.
     * That also means extra detail on the invoice (BP, quartier, city) never
     * rejects it. Accents, case and punctuation are ignored.
     */
    private function addressMatches(?string $invoiceValue, ?string $expected): bool
    {
        if (! $this->filled($invoiceValue) || ! $this->filled($expected)) {
            return false;
        }

        $invoiceWords = $this->addressWords((string) $invoiceValue);
        $expectedWords = $this->addressWords((string) $expected);

        if ($invoiceWords === [] || $expectedWords === []) {
            return false;
        }

        // Either side may carry more detail than the other — a subset in either
        // direction is a match (mirrors the tolerant name comparison above).
        return array_diff($expectedWords, $invoiceWords) === []
            || array_diff($invoiceWords, $expectedWords) === [];
    }

    /**
     * The distinct, comparable words of an address: lower-cased, accents folded
     * (Bessengué = Bessengue), punctuation dropped.
     *
     * @return array<int, string>
     */
    private function addressWords(string $value): array
    {
        $folded = strtr(mb_strtolower(trim($value)), [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'ç' => 'c', 'ñ' => 'n',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y',
        ]);

        $folded = (string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $folded);
        $folded = (string) preg_replace('/\s+/u', ' ', $folded);

        $words = array_filter(explode(' ', trim($folded)), static fn (string $w): bool => $w !== '');

        return array_values(array_unique($words));
    }

    /**
     * True if the invoice value matches ANY of the accepted spellings.
     *
     * @param  array<int, string>  $expectedValues
     */
    private function nameMatchesAny(?string $invoiceValue, array $expectedValues): bool
    {
        foreach ($expectedValues as $expected) {
            if ($this->nameMatches($invoiceValue, $expected)) {
                return true;
            }
        }

        return false;
    }

    /** "VAT = HT × rate = expected; invoice states actual (variance …)." */
    private function vatCalcDetail(InvoiceData $invoice): string
    {
        if ($this->treatExempt($invoice)) {
            return (string) __($invoice->isExempt() ? 'app.conformity.narr.vat_exempt' : 'app.conformity.narr.vat_attested');
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

        $vat = $this->treatExempt($invoice) ? 0 : ($invoice->vatAmount ?? 0);
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
        if ($this->treatExempt($invoice)) {
            return (string) __($invoice->isExempt() ? 'app.conformity.narr.vat_rate_exempt' : 'app.conformity.narr.vat_attested');
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

    /**
     * The VAT and totals findings, gated by the vendor's régime.
     *
     * VAT controls apply ONLY to a vendor under the Régime du Réel — for them
     * the full figure checks run (mention present, rate 19.25 %, HT × rate = VAT,
     * TTC = HT + VAT). For a positively non-Réel vendor (Simplifié/RSI, IGS, or a
     * non-business category) VAT is ignored entirely: no VAT findings at all,
     * whether or not the invoice carries VAT. An undetermined régime is judged
     * conservatively, exactly like a Réel vendor, so a control is never silently
     * skipped just because the régime could not be read.
     *
     * @return array<int, ConformityFinding>
     */
    private function vatFindings(InvoiceData $invoice, ?string $vendorRegimeLabel): array
    {
        if (VendorRegime::fromLabel($vendorRegimeLabel)->vatApplicable() === false) {
            return [];
        }

        return [
            $this->forme('vat', $this->treatExempt($invoice) || ($invoice->vatAmount !== null && $invoice->vatRate !== null)),
            $this->fond('vat_calc', $this->vatCalcOk($invoice), $this->vatCalcDetail($invoice)),
            $this->fond('ttc_calc', $this->ttcCalcOk($invoice), $this->ttcCalcDetail($invoice)),
            $this->fond('vat_rate', $this->vatRateOk($invoice), $this->vatRateDetail($invoice)),
        ];
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
