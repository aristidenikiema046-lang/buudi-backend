<?php

namespace App\Filament\Resources\MerchantProfiles\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class MerchantProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Marchand')
                    ->columns(3)
                    ->schema([
                        Placeholder::make('user.name')->label('Nom du compte')
                            ->content(fn ($record) => $record?->user?->name ?? '—'),
                        Placeholder::make('user.email')->label('Email')
                            ->content(fn ($record) => $record?->user?->email ?? '—'),
                        Placeholder::make('user.phone')->label('Téléphone')
                            ->content(fn ($record) => $record?->user?->phone ?? '—'),
                        Placeholder::make('business_name')->label('Nom du commerce')
                            ->content(fn ($record) => $record?->business_name ?? '—'),
                        Placeholder::make('business_type')->label('Type')
                            ->content(fn ($record) => $record?->business_type ?? '—'),
                        Placeholder::make('business_address')->label('Adresse')
                            ->content(fn ($record) => $record?->business_address ?? '—'),
                        Placeholder::make('status')->label('Statut')
                            ->content(fn ($record) => ucfirst($record?->status ?? '—')),
                        Placeholder::make('rejection_reason')->label('Motif de rejet')
                            ->content(fn ($record) => $record?->rejection_reason ?? '—')
                            ->columnSpan(2),
                    ]),

                Section::make('Logo')
                    ->schema([
                        Placeholder::make('logo_url')
                            ->label('')
                            ->content(function ($record) {
                                $url = $record?->logo_url;

                                if (!$url) {
                                    return new HtmlString('<span class="text-gray-400 text-sm">Aucun logo</span>');
                                }

                                return new HtmlString(
                                    '<a href="' . e($url) . '" target="_blank" rel="noopener">' .
                                    '<img src="' . e($url) . '" style="max-width:160px;max-height:160px;border-radius:8px;object-fit:cover;" />' .
                                    '</a>'
                                );
                            }),
                    ]),

                Section::make('Paramètres')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_supermarket')
                            ->label('Compte Supermarché')
                            ->helperText('Active le catalogue produits et le flux de commande dédié.'),
                        TextInput::make('commission_rate')
                            ->label('Taux de commission (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                    ]),
            ]);
    }
}
