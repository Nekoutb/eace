<?php

declare(strict_types=1);

namespace App\Services\Invoice;

/**
 * Drafts a ready-to-send notice to the vendor — accepting the invoice, or
 * rejecting it with the list of discrepancies to correct. Pure text generation:
 * the user reviews and sends it themselves (the app never sends email).
 */
final class InvoiceNoticeService
{
    /**
     * @param  array<int, array<string, mixed>>  $discrepancies  each with label + detail
     * @return array{subject: string, body: string}
     */
    public function draft(bool $accepted, ?string $supplierName, ?string $buyerName, array $discrepancies): array
    {
        $supplier = $this->filled($supplierName) ? (string) $supplierName : (string) __('app.notice.the_vendor');
        $buyer = $this->filled($buyerName) ? (string) $buyerName : (string) __('app.notice.our_company');

        if ($accepted) {
            return [
                'subject' => (string) __('app.notice.accept_subject'),
                'body' => (string) __('app.notice.accept_body', ['supplier' => $supplier, 'buyer' => $buyer]),
            ];
        }

        $issues = collect($discrepancies)
            ->map(function (array $d): string {
                $label = (string) ($d['label'] ?? '');
                $detail = (string) ($d['detail'] ?? '');

                return $detail !== '' ? "• {$label} — {$detail}" : "• {$label}";
            })
            ->implode("\n");

        return [
            'subject' => (string) __('app.notice.reject_subject'),
            'body' => (string) __('app.notice.reject_body', [
                'supplier' => $supplier,
                'buyer' => $buyer,
                'issues' => $issues !== '' ? $issues : (string) __('app.notice.no_issue_line'),
            ]),
        ];
    }

    private function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
