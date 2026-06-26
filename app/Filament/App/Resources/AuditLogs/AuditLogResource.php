<?php

namespace App\Filament\App\Resources\AuditLogs;

use App\Filament\App\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\App\Resources\AuditLogs\Pages\ViewAuditLog;
use App\Filament\App\Resources\AuditLogs\Schemas\AuditLogInfolist;
use App\Filament\App\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Models\Verification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Audit Logs — for every invoice checked, the evidence gathered: the immutable
 * Fiscalis extract (showing the vendor active), the four checks, and the
 * taxpayer-file extraction it was checked against. Read-only.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = Verification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'niu';

    // Distinct slug so it does not collide with the Audit Logs hub page (/app/audit-logs).
    protected static ?string $slug = 'audit-journal';

    protected static ?int $navigationSort = 5;

    /**
     * Client-portal resource: only a tenant (client) user may reach it. A platform
     * (central) user is barred here too — defence in depth beyond the /app panel
     * boundary, and a self-documenting statement of audience.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->isCentralUser() === false;
    }

    /** Reached from the Audit Logs hub, not the top-level sidebar. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('app.nav.audit_journal');
    }

    public static function getModelLabel(): string
    {
        return (string) __('app.nav.audit_journal');
    }

    public static function infolist(Schema $schema): Schema
    {
        return AuditLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }
}
