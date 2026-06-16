<?php

namespace App\Filament\App\Resources\AuditLogs\Tables;

use App\Domain\Approval\ApprovalDecision;
use App\Domain\Fiscalis\FiscalisPresence;
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
                    ->searchable(),
                TextColumn::make('vendor')
                    ->label(__('app.audit.vendor'))
                    ->state(fn (Verification $record): ?string => self::payloadString($record, 'taxpayer_name'))
                    ->placeholder(__('app.ui.placeholder_dash')),
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
                    ->formatStateUsing(fn (ApprovalDecision $state): string => $state->label())
                    ->color(fn (ApprovalDecision $state): string => $state->color())
                    ->icon(fn (ApprovalDecision $state): string => $state->icon()),
                TextColumn::make('archived_at')
                    ->label(__('app.audit.archived'))
                    ->dateTime('d/m/Y')
                    ->placeholder(__('app.ui.placeholder_dash'))
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
                    ->action(fn (Verification $record): BinaryFileResponse => response()
                        ->download(app(EvidenceArchiveService::class)->exportZip($record), 'evidence-'.($record->niu ?? $record->getKey()).'.zip')
                        ->deleteFileAfterSend()),
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
