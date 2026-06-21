<?php

namespace App\Filament\Resources\Conversations\Tables;

use App\Domain\Support\ConversationNature;
use App\Domain\Support\ConversationStatus;
use App\Domain\Support\MessageAuthor;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Support\TelegramNotifier;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('support.console.opened'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('tenant_name')
                    ->label(__('support.console.workspace'))
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('requester_name')
                    ->label(__('support.console.requester'))
                    ->description(fn (Conversation $r): ?string => $r->requester_email)
                    ->searchable(),
                TextColumn::make('subject')
                    ->label(__('support.console.thread'))
                    ->wrap()
                    ->limit(70)
                    ->searchable(),
                TextColumn::make('nature')
                    ->label(__('support.console.nature'))
                    ->badge()
                    ->formatStateUsing(fn (ConversationNature $state): string => $state->label())
                    ->color(fn (ConversationNature $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('support.console.status'))
                    ->badge()
                    ->formatStateUsing(fn (ConversationStatus $state): string => $state->label())
                    ->color(fn (ConversationStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('assignee.name')
                    ->label(__('support.console.assignee'))
                    ->placeholder(__('support.console.unassigned'))
                    ->toggleable(),
                TextColumn::make('first_response_at')
                    ->label(__('support.console.first_response'))
                    ->state(fn (Conversation $r): string => self::responseState($r))
                    ->badge()
                    ->color(fn (Conversation $r): string => $r->breachedSla() ? 'danger' : ($r->first_response_at ? 'success' : 'gray'))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('support.console.status'))
                    ->options(ConversationStatus::options()),
                SelectFilter::make('nature')
                    ->label(__('support.console.nature'))
                    ->options(ConversationNature::options()),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::replyAction(),
                    self::triageAction(),
                    self::resolveAction(),
                    self::escalateAction(),
                ]),
            ]);
    }

    private static function responseState(Conversation $r): string
    {
        if ($r->first_response_at !== null) {
            $m = $r->firstResponseMinutes() ?? 0;

            return $m >= 60 ? (string) __('support.kpi.hours', ['n' => round($m / 60, 1)]) : (string) __('support.kpi.minutes', ['n' => $m]);
        }

        return $r->breachedSla() ? (string) __('support.console.sla_breached') : (string) __('support.kpi.none');
    }

    /** Read the full thread and reply — the reply is shown to the customer in their workspace. */
    private static function replyAction(): Action
    {
        return Action::make('reply')
            ->label(__('support.console.reply'))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('success')
            ->modalHeading(fn (Conversation $record): string => $record->subject ?? (string) __('support.console.thread'))
            ->modalSubmitActionLabel(__('support.console.reply_send'))
            ->modalContent(fn (Conversation $record) => view('filament.admin.conversation-thread', ['conversation' => $record->load('messages')]))
            ->schema([
                Textarea::make('reply')
                    ->hiddenLabel()
                    ->placeholder(__('support.console.reply_ph'))
                    ->required()
                    ->rows(4)
                    ->maxLength(4000),
            ])
            ->action(function (Conversation $record, array $data): void {
                $record->postMessage(
                    MessageAuthor::Agent,
                    trim((string) $data['reply']),
                    auth()->user()?->name,
                    auth()->id(),
                );

                if (in_array($record->status, [ConversationStatus::New, ConversationStatus::Open, ConversationStatus::Escalated], true)) {
                    $record->forceFill(['status' => ConversationStatus::AwaitingCustomer])->save();
                }

                Notification::make()->title(__('support.console.reply_sent'))->success()->send();
            });
    }

    /** Set the nature, status, priority and assignee. */
    private static function triageAction(): Action
    {
        return Action::make('triage')
            ->label(__('support.console.assign'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->fillForm(fn (Conversation $record): array => [
                'nature' => $record->nature->value,
                'status' => $record->status->value,
                'priority' => $record->priority,
                'assigned_to' => $record->assigned_to,
            ])
            ->schema([
                Select::make('nature')->label(__('support.console.nature'))->options(ConversationNature::options())->required(),
                Select::make('status')->label(__('support.console.status'))->options(ConversationStatus::options())->required(),
                Select::make('priority')->label(__('support.console.priority'))->options([
                    'low' => (string) __('support.priority.low'),
                    'normal' => (string) __('support.priority.normal'),
                    'high' => (string) __('support.priority.high'),
                    'urgent' => (string) __('support.priority.urgent'),
                ])->required(),
                Select::make('assigned_to')->label(__('support.console.assignee'))
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder(__('support.console.unassigned')),
            ])
            ->action(function (Conversation $record, array $data): void {
                $record->forceFill([
                    'nature' => $data['nature'],
                    'status' => $data['status'],
                    'priority' => $data['priority'],
                    'assigned_to' => $data['assigned_to'] ?: null,
                    'last_activity_at' => now(),
                ])->save();

                Notification::make()->title(__('support.console.assign_saved'))->success()->send();
            });
    }

    private static function resolveAction(): Action
    {
        return Action::make('resolve')
            ->label(fn (Conversation $record): string => $record->status === ConversationStatus::Resolved ? (string) __('support.console.reopen') : (string) __('support.console.resolve'))
            ->icon('heroicon-o-check-circle')
            ->color(fn (Conversation $record): string => $record->status === ConversationStatus::Resolved ? 'gray' : 'success')
            ->requiresConfirmation()
            ->action(function (Conversation $record): void {
                if ($record->status === ConversationStatus::Resolved) {
                    $record->reopen();
                    Notification::make()->title(__('support.console.reopen'))->success()->send();

                    return;
                }

                $record->resolve();
                Notification::make()->title(__('support.console.resolved_done'))->success()->send();
            });
    }

    private static function escalateAction(): Action
    {
        return Action::make('escalate')
            ->label(__('support.console.escalate'))
            ->icon('heroicon-o-arrow-up-circle')
            ->color('danger')
            ->visible(fn (Conversation $record): bool => $record->status !== ConversationStatus::Escalated && $record->status !== ConversationStatus::Resolved)
            ->requiresConfirmation()
            ->action(function (Conversation $record): void {
                $record->escalate();
                app(TelegramNotifier::class)->pushEscalation($record);
                Notification::make()->title(__('support.console.escalated_done'))->warning()->send();
            });
    }
}
