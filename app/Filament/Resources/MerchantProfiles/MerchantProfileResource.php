<?php

namespace App\Filament\Resources\MerchantProfiles;

use App\Filament\Resources\MerchantProfiles\Pages\EditMerchantProfile;
use App\Filament\Resources\MerchantProfiles\Pages\ListMerchantProfiles;
use App\Filament\Resources\MerchantProfiles\Schemas\MerchantProfileForm;
use App\Filament\Resources\MerchantProfiles\Tables\MerchantProfilesTable;
use App\Models\MerchantProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MerchantProfileResource extends Resource
{
    protected static ?string $model = MerchantProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Marchands';

    protected static ?string $modelLabel = 'marchand';

    protected static ?string $pluralModelLabel = 'marchands';

    public static function form(Schema $schema): Schema
    {
        return MerchantProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchantProfilesTable::configure($table);
    }

    // Même raisonnement que DriverProfileResource : un dossier marchand
    // naît de l'inscription mobile (compte User + logo), pas d'un
    // formulaire vide créé depuis le panel.
    public static function canCreate(): bool
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
            'index' => ListMerchantProfiles::route('/'),
            'edit' => EditMerchantProfile::route('/{record}/edit'),
        ];
    }
}
