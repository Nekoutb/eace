<?php

namespace App\Filament\App\Resources\Verifications\Tables;

use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Domain\Niu\Verdict;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VerificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('niu')
                    ->label(__('app.ui.niu'))
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder(__('app.ui.placeholder_invalid'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('stage')
                    ->label(__('app.ui.stage'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (VerificationStage $state): string => $state->label()),
                TextColumn::make('approval')
                    ->label(__('app.ui.approval'))
                    ->badge()
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->formatStateUsing(fn (ApprovalDecision $state): string => $state->label())
                    ->color(fn (ApprovalDecision $state): string => $state->color())
                    ->icon(fn (ApprovalDecision $state): string => $state->icon()),
                TextColumn::make('verdict')
                    ->label(__('app.ui.verdict'))
                    ->badge()
                    ->formatStateUsing(fn (Verdict $state): string => $state->label())
                    ->color(fn (Verdict $state): string => $state->color())
                    ->icon(fn (Verdict $state): string => $state->icon()),
                TextColumn::make('thirdParty.name')
                    ->label(__('app.ui.third_party'))
                    ->placeholder(__('app.ui.placeholder_dash'))
                    ->searchable(),
                TextColumn::make('exposure_xaf')
                    ->label(__('app.ui.exposure'))
                    ->money('XAF', locale: 'fr')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('app.ui.verified_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('stage')
                    ->label(__('app.ui.stage'))
                    ->options(collect(VerificationStage::cases())
                        ->mapWithKeys(fn (VerificationStage $s): array => [$s->value => $s->label()])
                        ->all()),
                SelectFilter::make('approval')
                    ->label(__('app.ui.approval'))
                    ->options(collect(ApprovalDecision::cases())
                        ->mapWithKeys(fn (ApprovalDecision $d): array => [$d->value => $d->label()])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
