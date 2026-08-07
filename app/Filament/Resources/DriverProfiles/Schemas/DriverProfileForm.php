<?php

namespace App\Filament\Resources\DriverProfiles\Schemas;

use App\Filament\Resources\DriverProfiles\DriverProfileResource;
use App\Filament\Support\ResolvesDocumentUrls;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class DriverProfileForm
{
    use ResolvesDocumentUrls;

    /**
     * Documents affichés en lecture seule (image cliquable, ouvre l'original
     * dans un nouvel onglet). Ce ne sont que des URLs déjà publiques
     * (storage:link) — pas de FileUpload ici, on ne les remplace jamais
     * depuis le panel, seulement depuis l'inscription mobile.
     */
    private static function documentPreview(string $field, string $label): Placeholder
    {
        return Placeholder::make($field)
            ->label($label)
            ->content(function ($record) use ($field) {
                $url = self::resolveDocumentUrl($record?->{$field});

                if (!$url) {
                    return new HtmlString('<span class="text-gray-400 text-sm">Non fourni</span>');
                }

                return new HtmlString(
                    '<a href="' . e($url) . '" target="_blank" rel="noopener">' .
                    '<img src="' . e($url) . '" style="max-width:220px;max-height:220px;border-radius:8px;object-fit:cover;" />' .
                    '</a>'
                );
            });
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn ($record) => DriverProfileResource::roleLabel($record?->vehicle_type))
                    ->columns(3)
                    ->schema([
                        Placeholder::make('user.name')->label('Nom')
                            ->content(fn ($record) => $record?->user?->name ?? '—'),
                        Placeholder::make('user.email')->label('Email')
                            ->content(fn ($record) => $record?->user?->email ?? '—'),
                        Placeholder::make('user.phone')->label('Téléphone')
                            ->content(fn ($record) => $record?->user?->phone ?? '—'),
                        Placeholder::make('status')->label('Statut')
                            ->content(fn ($record) => ucfirst($record?->status ?? '—')),
                        Placeholder::make('rejection_reason')->label('Motif de rejet')
                            ->content(fn ($record) => $record?->rejection_reason ?? '—')
                            ->columnSpan(2),
                    ]),

                Section::make('Véhicule')
                    ->columns(3)
                    ->schema([
                        Placeholder::make('vehicle_type')->label('Type')
                            ->content(fn ($record) => $record?->vehicle_type ?? '—'),
                        Placeholder::make('vehicle_brand_model')->label('Marque / Modèle')
                            ->content(fn ($record) => trim(($record?->vehicle_brand ?? '') . ' ' . ($record?->vehicle_model ?? '')) ?: '—'),
                        Placeholder::make('vehicle_plate')->label('Immatriculation')
                            ->content(fn ($record) => $record?->vehicle_plate ?? '—'),
                    ]),

                Section::make('Documents')
                    ->description('Cliquer sur une image pour l\'ouvrir en taille réelle.')
                    ->schema([
                        Grid::make(4)->schema([
                            self::documentPreview('cni_url', 'Pièce d\'identité'),
                            self::documentPreview('license_url', 'Permis de conduire'),
                            self::documentPreview('selfie_url', 'Selfie'),
                            self::documentPreview('criminal_record_url', 'Casier judiciaire'),
                            self::documentPreview('vehicle_image_url', 'Photo véhicule'),
                            self::documentPreview('vehicle_registration_url', 'Carte grise'),
                            self::documentPreview('insurance_url', 'Assurance'),
                        ]),
                    ]),

                Section::make('Validité des documents')
                    ->description('Saisie manuelle après vérification du document — aucune app mobile ne transmet cette date aujourd\'hui.')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('license_expires_at')
                            ->label('Expiration du permis')
                            ->native(false),
                        DatePicker::make('insurance_expires_at')
                            ->label('Expiration de l\'assurance')
                            ->native(false),
                    ]),
            ]);
    }
}
