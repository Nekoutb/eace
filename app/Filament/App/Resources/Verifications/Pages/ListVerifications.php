<?php

namespace App\Filament\App\Resources\Verifications\Pages;

use App\Filament\App\Resources\Verifications\VerificationResource;
use Filament\Resources\Pages\ListRecords;

/**
 * History of verifications. Running a new verification happens from the
 * Compliance Engine page (the phase gate), not here.
 */
class ListVerifications extends ListRecords
{
    protected static string $resource = VerificationResource::class;
}
