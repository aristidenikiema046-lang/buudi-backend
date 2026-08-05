<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    // Pas de CreateAction : voir UserResource pour le raisonnement.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
