<?php

namespace App\Filament\Resources\Rides;

use App\Filament\Resources\Rides\Pages\ListRides;
use App\Filament\Resources\Rides\Pages\ViewRide;
use App\Filament\Resources\Rides\Schemas\RideInfolist;
use App\Filament\Resources\Rides\Tables\RidesTable;
use App\Models\Ride;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Lecture seule, volontairement : annuler/modifier une course en cours
 * depuis l'admin a des implications de sécurité (client déjà en voiture,
 * chauffeur déjà en route) qui méritent leur propre réflexion — décision
 * prise explicitement avec le client, pas une omission.
 */
class RideResource extends Resource
{
    protected static ?string $model = Ride::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Courses & Livraisons';

    protected static ?string $modelLabel = 'course';

    protected static ?string $pluralModelLabel = 'courses';

    public static function infolist(Schema $schema): Schema
    {
        return RideInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RidesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRides::route('/'),
            'view' => ViewRide::route('/{record}'),
        ];
    }
}
