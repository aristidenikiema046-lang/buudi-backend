<?php

namespace App\Filament\Resources\MerchantProfiles\Pages;

use App\Filament\Resources\MerchantProfiles\MerchantProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListMerchantProfiles extends ListRecords
{
    protected static string $resource = MerchantProfileResource::class;

    // Pas de CreateAction : voir MerchantProfileResource::canCreate().
    protected function getHeaderActions(): array
    {
        return [];
    }
}
