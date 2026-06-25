<?php

namespace App\Filament\Resources\AcademyUsers\Tables;

use App\Mail\AcademyCredentialsMail;
use App\Models\AcademyUser;
use App\Support\TempPassword;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Throwable;

class AcademyUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('academy.f_name'))
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('academy.f_email'))
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('academy.col_active'))
                    ->boolean()
                    ->sortable(),
                IconColumn::make('must_change_password')
                    ->label(__('academy.col_pending'))
                    ->boolean()
                    ->trueIcon('heroicon-o-clock')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success')
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label(__('academy.col_last_login'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('academy.never'))
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::resetPasswordAction(),
                    self::toggleActiveAction(),
                    self::deleteAction(),
                ]),
            ]);
    }

    /**
     * Email the member their sign-in URL + one-time password (best-effort) and show
     * the same details once to the operator, noting whether the email went out.
     */
    public static function issueCredentials(AcademyUser $user, string $password, string $title): void
    {
        $emailed = false;
        try {
            Mail::to($user->email)->locale(app()->getLocale())->send(new AcademyCredentialsMail(
                name: $user->name,
                email: $user->email,
                temporaryPassword: $password,
                loginUrl: route('academy.login'),
            ));
            $emailed = true;
        } catch (Throwable $e) {
            report($e);
        }

        Notification::make()
            ->title($title)
            ->body(new HtmlString(
                '<div style="line-height:1.7">'
                .e(__('academy.cred_url')).' <strong>'.e(url('/academy')).'</strong><br>'
                .e(__('academy.cred_email')).': <code>'.e($user->email).'</code><br>'
                .e(__('academy.cred_password')).': <code>'.e($password).'</code><br>'
                .'<em>'.e(__('academy.cred_once')).'</em><br>'
                .e($emailed ? __('academy.cred_emailed') : __('academy.cred_not_emailed'))
                .'</div>'
            ))
            ->success()
            ->persistent()
            ->send();
    }

    private static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label(__('academy.reset_password'))
            ->icon('heroicon-o-key')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('academy.reset_desc'))
            ->action(function (AcademyUser $record): void {
                $password = TempPassword::generate();
                // 'hashed' cast hashes it; force a change again on next sign-in.
                $record->update(['password' => $password, 'must_change_password' => true]);
                self::issueCredentials($record, $password, (string) __('academy.pw_reset_title'));
            });
    }

    private static function toggleActiveAction(): Action
    {
        return Action::make('toggleActive')
            ->label(__('academy.toggle_active'))
            ->icon('heroicon-o-power')
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (AcademyUser $record): void {
                $record->update(['is_active' => ! $record->is_active]);
                Notification::make()
                    ->title($record->is_active ? (string) __('academy.activated') : (string) __('academy.deactivated'))
                    ->success()
                    ->send();
            });
    }

    private static function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('academy.delete'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn (AcademyUser $record) => $record->delete());
    }
}
