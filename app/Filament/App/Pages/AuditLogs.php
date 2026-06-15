<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Resources\AuditLogs\AuditLogResource;
use App\Filament\App\Resources\TaxpayerFiles\TaxpayerFileResource;
use App\Filament\App\Resources\Verifications\VerificationResource;
use App\Models\TaxpayerFileExtraction;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Audit Logs hub. Structures the three audit areas in the centre of the screen:
 * the downloaded taxpayer files (drill-down), the third parties + verification
 * evidences, and the detailed audit journal.
 */
class AuditLogs extends Page
{
    protected string $view = 'filament.app.pages.audit-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = -10;

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.audit_logs');
    }

    public function getTitle(): string
    {
        return (string) __('app.nav.audit_logs');
    }

    /**
     * The downloaded taxpayer files, newest first (for the drill-down list).
     *
     * @return Collection<int, TaxpayerFileExtraction>
     */
    public function downloadedFiles(): Collection
    {
        return TaxpayerFileExtraction::query()
            ->orderByDesc('id')
            ->limit(36)
            ->get();
    }

    public function filesUrl(): string
    {
        return TaxpayerFileResource::getUrl();
    }

    public function evidencesUrl(): string
    {
        return VerificationResource::getUrl();
    }

    public function journalUrl(): string
    {
        return AuditLogResource::getUrl();
    }
}
