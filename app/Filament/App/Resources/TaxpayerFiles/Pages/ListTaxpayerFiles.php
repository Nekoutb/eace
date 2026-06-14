<?php

namespace App\Filament\App\Resources\TaxpayerFiles\Pages;

use App\Filament\App\Resources\TaxpayerFiles\TaxpayerFileResource;
use Filament\Resources\Pages\ListRecords;

class ListTaxpayerFiles extends ListRecords
{
    protected static string $resource = TaxpayerFileResource::class;

    public function getSubheading(): ?string
    {
        return (string) __('app.audit.taxpayer_files_help');
    }
}
