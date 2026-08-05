<?php

namespace App\Filament\Resources\Rides\Pages;

use App\Filament\Resources\Rides\RideResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRide extends ViewRecord
{
    protected static string $resource = RideResource::class;

    // Pas d'EditAction : ressource en lecture seule (voir RideResource).
    protected function getHeaderActions(): array
    {
        return [];
    }
}
