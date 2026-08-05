<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    // Pas de CreateAction : une commande naît du flux client, jamais créée
    // à la main depuis le panel.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
