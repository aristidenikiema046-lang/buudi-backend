<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Phase 1 : liste en lecture seule (recherche + filtre rôle) uniquement,
 * comme demandé — pas de création/édition/suppression depuis le panel.
 * Créer un User à la main contournerait le hash du mot de passe et la
 * création du profil associé (driver_profiles/merchant_profiles) gérés par
 * les contrôleurs d'inscription ; le supprimer casserait les FK partout
 * (rides, transactions, notifications...).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static ?string $modelLabel = 'utilisateur';

    protected static ?string $pluralModelLabel = 'utilisateurs';

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
        ];
    }
}
