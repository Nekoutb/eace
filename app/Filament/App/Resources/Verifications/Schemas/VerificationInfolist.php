<?php

namespace App\Filament\App\Resources\Verifications\Schemas;

use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;

class VerificationInfolist
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
