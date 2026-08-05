<?php

namespace App\Filament\Resources\Rides\Pages;

use App\Filament\Resources\Rides\RideResource;
use Filament\Resources\Pages\ListRecords;

class ListRides extends ListRecords
{
    protected static string $resource = RideResource::class;

    // Pas de CreateAction : une course naît du flux client/marchand, jamais
    // créée à la main depuis le panel.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
