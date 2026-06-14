<?php

namespace App\Filament\App\Resources\AuditLogs\Pages;

use App\Filament\App\Resources\AuditLogs\AuditLogResource;
use App\Models\TaxpayerFileExtraction;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    /**
     * Surface which taxpayer-file extraction the checks are currently running
     * against — the weekly extraction reference for the whole section.
     */
    public function getSubheading(): ?string
    {
        $extraction = TaxpayerFileExtraction::current();

        if ($extraction === null) {
            return (string) __('app.audit.no_extraction');
        }

        return (string) __('app.audit.checked_against', [
            'period' => $extraction->period_label,
            'count' => number_format($extraction->row_count),
            'date' => $extraction->extracted_at?->format('d/m/Y') ?? '—',
        ]);
    }
}
