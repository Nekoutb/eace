<?php

namespace App\Filament\App\Resources\AuditLogs\Tables;

use App\Domain\Approval\ApprovalDecision;
use App\Domain\Fiscalis\FiscalisPresence;
use App\Models\DataAccessLog;
use App\Models\Verification;
use App\Services\Evidence\EvidenceArchiveService;
use App\Services\Fiscalis\FiscalisStatusService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('app.ui.verified_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('niu')
                    ->label(__('app.ui.niu'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder(__('app.ui.placeholder_invalid'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('taxpayer_name')
                    ->label(__('app.ui.taxpayer'))
                    ->state(fn (Verification $record): ?string => self::vendorName($record))
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->orWhere('invoice_data->supplier_name', 'like', "%{$search}%")),
                TextColumn::make('invoice_ref')
                    ->label(__('app.audit.invoice_ref'))
                    ->fontFamily(FontFamily::Mono)
                    ->state(fn (Verification $record): ?string => self::invoiceField($record, 'invoice_number'))
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('invoice_data->invoice_number', $direction))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->orWhere('invoice_data->invoice_number', 'like', "%{$search}%")),
                TextColumn::make('active')
                    ->label(__('app.audit.active'))
                    ->badge()
                    ->state(fn (Verification $record): string => self::statusLabel($record))
                    ->color(fn (Verification $record): string => self::isActive($record) ? 'success' : 'gray'),
                TextColumn::make('source')
                    ->label(__('app.audit.source'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (Verification $record): ?string => $record->snapshot()?->source)
                    ->placeholder(__('app.ui.placeholder_no_snapshot')),
                TextColumn::make('approval')
                    ->label(__('app.ui.approval'))
                    ->badge()
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->sortable()
                    ->formatStateUsing(fn (ApprovalDecision $state): string => $state->label())
                    ->color(fn (ApprovalDecision $state): string => $state->color())
                    ->icon(fn (ApprovalDecision $state): string => $state->icon()),
                TextColumn::make('archived_at')
                    ->label(__('app.audit.archived'))
                    ->dateTime('d/m/Y')
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->sortable()
                    ->badge()
                    ->color('gray'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('captureFiscalis')
                    ->label(__('app.ui.capture_fiscalis'))
                    ->icon('heroicon-o-camera')
                    ->color('success')
                    ->visible(fn (Verification $record): bool => filled($record->niu))
                    ->requiresConfirmation()
                    ->modalHeading(__('app.ui.capture_fiscalis'))
                    ->modalDescription(__('app.ui.capture_fiscalis_confirm'))
                    ->action(function (Verification $record): void {
                        $result = app(FiscalisStatusService::class)->capture($record);

                        $notification = Notification::make()->title($result->presence->label());

                        match ($result->presence) {
                            FiscalisPresence::Present => $notification->success(),
                            FiscalisPresence::Absent => $notification->danger(),
                            FiscalisPresence::Unverified => $notification->warning()
                                ->body(__('app.ui.capture_unverified')),
                        };

                        $notification->send();
                    }),
                Action::make('export')
                    ->label(__('app.audit.export'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(function (Verification $record): BinaryFileResponse {
                        // Read-level audit (art. 27): record this export of evidence (PII).
                        DataAccessLog::record('export', 'evidence', 'verification #'.$record->getKey().($record->niu ? ' / '.$record->niu : ''));

                        return response()
                            ->download(app(EvidenceArchiveService::class)->exportZip($record), 'evidence-'.($record->niu ?? $record->getKey()).'.zip')
                            ->deleteFileAfterSend();
                    }),
                Action::make('archive')
                    ->label(__('app.audit.archive'))
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->color('warning')
                    ->visible(fn (Verification $record): bool => ! $record->isArchived())
                    ->requiresConfirmation()
                    ->modalDescription(__('app.audit.archive_confirm'))
                    ->action(fn (Verification $record) => app(EvidenceArchiveService::class)->archive($record)),
            ]);
    }

    private static function payloadString(Verification $record, string $key): ?string
    {
        $value = $record->snapshot()?->payload[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /** A field read off the scanned invoice (invoice_data JSON). */
    private static function invoiceField(Verification $record, string $key): ?string
    {
        $value = is_array($record->invoice_data) ? ($record->invoice_data[$key] ?? null) : null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /** The vendor: the supplier named on the invoice, else the DGI-registered name. */
    private static function vendorName(Verification $record): ?string
    {
        return self::invoiceField($record, 'supplier_name') ?? self::payloadString($record, 'taxpayer_name');
    }

    private static function isActive(Verification $record): bool
    {
        return (bool) ($record->snapshot()?->payload['on_active_file'] ?? false);
    }

    private static function statusLabel(Verification $record): string
    {
        $status = $record->snapshot()?->payload['fiscalis_status'] ?? null;

        return is_string($status)
            ? (string) __('app.fiscalis_status.'.$status)
            : (string) __('app.ui.placeholder_dash');
    }
}
