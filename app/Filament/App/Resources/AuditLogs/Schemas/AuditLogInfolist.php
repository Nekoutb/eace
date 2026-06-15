<?php

namespace App\Filament\App\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('evidence_detail')
                ->hiddenLabel()
                ->view('filament.app.evidence-detail'),
        ]);
    }
}
