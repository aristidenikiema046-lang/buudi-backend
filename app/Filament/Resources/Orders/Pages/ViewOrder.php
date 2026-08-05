<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    // Pas d'EditAction : ressource en lecture seule (voir OrderResource).
    protected function getHeaderActions(): array
    {
        return [];
    }
}
