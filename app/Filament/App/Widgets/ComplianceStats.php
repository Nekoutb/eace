<?php

namespace App\Filament\App\Widgets;

use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Models\TaxpayerFileExtraction;
use App\Models\Verification;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard headline figures: invoices processed at recording (compliant vs not)
 * and at settlement (passed vs failed for payment), plus the taxpayer-file
 * download status.
 */
class ComplianceStats extends BaseWidget
{
    protected function getStats(): array
    {
        $recordingTotal = $this->count(VerificationStage::Recording, null);
        $compliant = $this->count(VerificationStage::Recording, ApprovalDecision::Accepted);
        $nonCompliant = $this->count(VerificationStage::Recording, ApprovalDecision::Rejected);

        $settlementTotal = $this->count(VerificationStage::Settlement, null);
        $passed = $this->count(VerificationStage::Settlement, ApprovalDecision::Accepted);
        $failed = $this->count(VerificationStage::Settlement, ApprovalDecision::Rejected);

        $extraction = TaxpayerFileExtraction::current();
        $filesCount = TaxpayerFileExtraction::query()->whereIn('status', ['completed', 'unchanged'])->count();
        $filesDescription = $extraction !== null
            ? (string) __('app.dash.files_desc', [
                'period' => $extraction->period_label,
                'count' => number_format($extraction->row_count),
                'date' => $extraction->extracted_at?->format('d/m/Y') ?? '—',
            ])
            : (string) __('app.dash.no_files');

        return [
            Stat::make(__('app.dash.invoices_processed'), number_format($recordingTotal))
                ->description(__('app.dash.invoices_processed_desc'))
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),
            Stat::make(__('app.dash.compliant'), number_format($compliant))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make(__('app.dash.non_compliant'), number_format($nonCompliant))
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('danger'),
            Stat::make(__('app.dash.payment_processed'), number_format($settlementTotal))
                ->description(__('app.dash.payment_processed_desc'))
                ->descriptionIcon('heroicon-m-queue-list')
                ->color('primary'),
            Stat::make(__('app.dash.payment_passed'), number_format($passed))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make(__('app.dash.payment_failed'), number_format($failed))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
            Stat::make(__('app.dash.files_downloaded'), number_format($filesCount))
                ->description($filesDescription)
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('info'),
        ];
    }

    protected function count(VerificationStage $stage, ?ApprovalDecision $approval): int
    {
        $query = Verification::query()->where('stage', $stage->value);

        if ($approval !== null) {
            $query->where('approval', $approval->value);
        }

        return $query->count();
    }

    protected function getColumns(): int
    {
        return 3;
    }
}
