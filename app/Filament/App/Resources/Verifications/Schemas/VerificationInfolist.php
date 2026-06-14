<?php

namespace App\Filament\App\Resources\Verifications\Schemas;

use App\Domain\Approval\ApprovalDecision;
use App\Domain\Approval\VerificationStage;
use App\Domain\Niu\Verdict;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;

class VerificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('app.ui.section_decision'))
                ->columns(3)
                ->components([
                    TextEntry::make('approval')
                        ->label(__('app.ui.approval_invoice'))
                        ->badge()
                        ->placeholder(__('app.ui.placeholder_dash'))
                        ->formatStateUsing(fn (ApprovalDecision $state): string => $state->label())
                        ->color(fn (ApprovalDecision $state): string => $state->color())
                        ->icon(fn (ApprovalDecision $state): string => $state->icon()),
                    TextEntry::make('stage')
                        ->label(__('app.ui.stage'))
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn (VerificationStage $state): string => $state->label()),
                    TextEntry::make('created_at')
                        ->label(__('app.ui.verified_at'))
                        ->dateTime('d/m/Y H:i'),
                ]),
            Section::make(__('app.ui.section_four_checks'))
                ->visible(fn ($record): bool => filled($record->checks))
                ->components([
                    RepeatableEntry::make('checks')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            IconEntry::make('passed')
                                ->label('')
                                ->boolean(),
                            TextEntry::make('label')
                                ->label(__('app.ui.check_col'))
                                ->weight(FontWeight::Medium),
                            TextEntry::make('article')
                                ->label(__('app.ui.basis'))
                                ->badge()
                                ->color('gray'),
                            TextEntry::make('detail')
                                ->hiddenLabel()
                                ->columnSpanFull(),
                        ]),
                ]),
            Section::make(__('app.ui.section_verdict_exposure'))
                ->columns(2)
                ->components([
                    TextEntry::make('verdict')
                        ->label(__('app.ui.verdict'))
                        ->badge()
                        ->formatStateUsing(fn (Verdict $state): string => $state->label())
                        ->color(fn (Verdict $state): string => $state->color())
                        ->icon(fn (Verdict $state): string => $state->icon()),
                    TextEntry::make('exposure_xaf')
                        ->label(__('app.ui.exposure_estimated'))
                        ->money('XAF', locale: 'fr'),
                    TextEntry::make('niu')
                        ->label(__('app.ui.niu'))
                        ->fontFamily(FontFamily::Mono)
                        ->placeholder(__('app.ui.placeholder_invalid'))
                        ->copyable(),
                    TextEntry::make('raw_input')
                        ->label(__('app.ui.raw_input')),
                    TextEntry::make('thirdParty.name')
                        ->label(__('app.ui.third_party'))
                        ->placeholder(__('app.ui.placeholder_dash')),
                    TextEntry::make('snapshot_id')
                        ->label(__('app.ui.snapshot'))
                        ->placeholder(__('app.ui.placeholder_no_snapshot'))
                        ->copyable()
                        ->columnSpanFull(),
                ]),
            Section::make(__('app.ui.section_triggered_rules'))
                ->visible(fn ($record): bool => filled($record->triggered_rules))
                ->components([
                    RepeatableEntry::make('triggered_rules')
                        ->hiddenLabel()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('title')
                                ->label(__('app.ui.rule'))
                                ->weight(FontWeight::Medium),
                            TextEntry::make('article')
                                ->label(__('app.ui.basis'))
                                ->badge()
                                ->color('gray'),
                            TextEntry::make('exposure_xaf')
                                ->label(__('app.ui.exposure'))
                                ->money('XAF', locale: 'fr'),
                            TextEntry::make('message')
                                ->hiddenLabel()
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }
}
