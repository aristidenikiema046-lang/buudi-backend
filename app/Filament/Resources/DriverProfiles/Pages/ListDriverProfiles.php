<?php

namespace App\Filament\Resources\DriverProfiles\Pages;

use App\Filament\Resources\DriverProfiles\DriverProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListDriverProfiles extends ListRecords
{
    protected static string $resource = DriverProfileResource::class;

    // Pas de CreateAction : un dossier chauffeur naît de l'inscription
    // mobile, jamais créé à vide depuis le panel (voir
    // DriverProfileResource::canCreate()).
    protected function getHeaderActions(): array
    {
        return [];
    }
}
