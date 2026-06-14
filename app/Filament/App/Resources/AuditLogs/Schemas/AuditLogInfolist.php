<?php

namespace App\Filament\App\Resources\AuditLogs\Schemas;

use App\Domain\Approval\ApprovalDecision;
use App\Models\Verification;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('app.ui.section_decision'))
                ->columns(3)
                ->components([
                    TextEntry::make('niu')
                        ->label(__('app.ui.niu'))
                        ->fontFamily(FontFamily::Mono)
                        ->placeholder(__('app.ui.placeholder_invalid'))
                        ->copyable(),
                    TextEntry::make('approval')
                        ->label(__('app.ui.approval'))
                        ->badge()
                        ->placeholder(__('app.ui.placeholder_dash'))
                        ->formatStateUsing(fn (ApprovalDecision $state): string => $state->label())
                        ->color(fn (ApprovalDecision $state): string => $state->color())
                        ->icon(fn (ApprovalDecision $state): string => $state->icon()),
                    TextEntry::make('created_at')
                        ->label(__('app.ui.verified_at'))
                        ->dateTime('d/m/Y H:i'),
                ]),

            Section::make(__('app.audit.section_evidence'))
                ->description(__('app.audit.section_evidence_help'))
                ->columns(3)
                ->components([
                    TextEntry::make('evidence_vendor')
                        ->label(__('app.audit.vendor'))
                        ->state(fn (Verification $record): ?string => self::payloadString($record, 'taxpayer_name'))
                        ->placeholder(__('app.ui.placeholder_no_snapshot'))
                        ->weight(FontWeight::Medium),
                    TextEntry::make('evidence_status')
                        ->label(__('app.ui.status'))
                        ->badge()
                        ->state(fn (Verification $record): string => self::statusLabel($record))
                        ->color(fn (Verification $record): string => self::isActive($record) ? 'success' : 'gray'),
                    IconEntry::make('evidence_active')
                        ->label(__('app.audit.active'))
                        ->state(fn (Verification $record): bool => self::isActive($record))
                        ->boolean(),
                    TextEntry::make('evidence_regime')
                        ->label(__('app.ui.regime'))
                        ->state(fn (Verification $record): ?string => self::payloadString($record, 'regime'))
                        ->placeholder(__('app.ui.placeholder_dash')),
                    TextEntry::make('evidence_centre')
                        ->label(__('app.ui.centre'))
                        ->state(fn (Verification $record): ?string => self::payloadString($record, 'centre'))
                        ->placeholder(__('app.ui.placeholder_dash')),
                    TextEntry::make('evidence_source')
                        ->label(__('app.audit.source'))
                        ->badge()
                        ->color('gray')
                        ->state(fn (Verification $record): ?string => $record->snapshot()?->source)
                        ->placeholder(__('app.ui.placeholder_no_snapshot')),
                    TextEntry::make('evidence_fetched_at')
                        ->label(__('app.audit.fetched_at'))
                        ->state(fn (Verification $record): ?string => $record->snapshot()?->fetched_at?->format('d/m/Y H:i'))
                        ->placeholder(__('app.ui.placeholder_dash')),
                    TextEntry::make('evidence_hash')
                        ->label(__('app.audit.hash'))
                        ->state(fn (Verification $record): ?string => $record->snapshot()?->content_hash)
                        ->fontFamily(FontFamily::Mono)
                        ->placeholder(__('app.ui.placeholder_no_snapshot'))
                        ->copyable()
                        ->columnSpanFull(),
                ]),

            Section::make(__('app.ui.section_four_checks'))
                ->visible(fn (Verification $record): bool => filled($record->checks))
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
