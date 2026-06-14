<?php

namespace App\Filament\App\Resources\AuditLogs\Tables;

use App\Domain\Approval\ApprovalDecision;
use App\Models\Verification;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
            ])
            ->recordActions([
                ViewAction::make(),
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
