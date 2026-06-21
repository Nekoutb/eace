<?php

namespace App\Filament\Resources\SupportStaff\Tables;

use App\Domain\Auth\StaffRole;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupportStaffTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('support.staff.name'))
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('support.staff.email'))
                    ->copyable()
                    ->searchable(),
                TextColumn::make('staff_role')
                    ->label(__('support.staff.role'))
                    ->badge()
                    ->formatStateUsing(fn (StaffRole $state): string => $state->label())
                    ->color(fn (StaffRole $state): string => $state->color()),
                IconColumn::make('two_factor')
                    ->label('2FA')
                    ->boolean()
                    ->state(fn (User $record): bool => filled($record->app_authentication_secret)),
                TextColumn::make('must_change_password')
                    ->label(__('support.staff.status'))
                    ->badge()
                    ->state(fn (User $record): string => $record->must_change_password ? (string) __('support.staff.pending_first_login') : (string) __('support.staff.active'))
                    ->color(fn (User $record): string => $record->must_change_password ? 'warning' : 'success'),
                TextColumn::make('last_login_at')
                    ->label(__('support.staff.last_login'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('support.staff.never'))
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->visible(fn (User $record): bool => $record->staff_role !== StaffRole::Owner && $record->getKey() !== auth()->id()),
            ]);
    }
}
