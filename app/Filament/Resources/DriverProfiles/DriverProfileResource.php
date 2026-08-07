<?php

namespace App\Filament\Resources\DriverProfiles;

use App\Filament\Resources\DriverProfiles\Pages\EditDriverProfile;
use App\Filament\Resources\DriverProfiles\Pages\ListDriverProfiles;
use App\Filament\Resources\DriverProfiles\Schemas\DriverProfileForm;
use App\Filament\Resources\DriverProfiles\Tables\DriverProfilesTable;
use App\Models\DriverProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DriverProfileResource extends Resource
{
    protected static ?string $model = DriverProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Chauffeurs';

    protected static ?string $modelLabel = 'chauffeur';

    protected static ?string $pluralModelLabel = 'chauffeurs';

    /**
     * chauffeurs et livreurs partagent driver_profiles, distingués uniquement
     * par vehicle_type (voir DriverRideController::allowedServiceTypesFor()
     * pour le mapping inverse côté API) — seul point de mapping du libellé
     * affiché, utilisé par le titre Edit, la section du formulaire et la
     * colonne "Type" de la Liste. Ne pas dupliquer ce match() ailleurs.
     */
    public static function roleLabel(?string $vehicleType): string
    {
        return in_array($vehicleType, ['Moto', 'Vélo'], true) ? 'Livreur' : 'Chauffeur';
    }

    public static function form(Schema $schema): Schema
    {
        return DriverProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriverProfilesTable::configure($table);
    }

    // Un dossier chauffeur naît uniquement via l'inscription mobile
    // (documents, véhicule, compte User) — en créer un vide depuis le panel
    // n'aurait pas de sens et laisserait un profil incomplet.
    public static function canCreate(): bool
    {
        return false;
    }

    // Dossier de conformité (documents d'identité, permis...) — pas de
    // suppression depuis le panel, sciemment.
    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverProfiles::route('/'),
            'edit' => EditDriverProfile::route('/{record}/edit'),
        ];
    }
}
