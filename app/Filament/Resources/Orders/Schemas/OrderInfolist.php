<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Rides\RideResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Commande')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('status')->label('Statut')->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'confirmed' => 'success',
                                'cancelled' => 'danger',
                                default => 'warning',
                            }),
                        TextEntry::make('subtotal')->label('Sous-total (marchandises)')->money('XOF'),
                        TextEntry::make('delivery_fee')->label('Frais de livraison')->money('XOF'),
                        TextEntry::make('total')->label('Total')->money('XOF'),
                        TextEntry::make('created_at')->label('Créée le')->dateTime('d/m/Y H:i'),
                        TextEntry::make('confirmed_at')->label('Confirmée le')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('cancelled_at')->label('Annulée le')->dateTime('d/m/Y H:i')->placeholder('—'),
                    ]),

                Section::make('Client & Marchand')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('client.name')->label('Client')
                            ->formatStateUsing(fn ($record) => $record->client
                                ? "{$record->client->name} — {$record->client->phone}"
                                : '—'),
                        TextEntry::make('merchantProfile.business_name')->label('Supermarché')
                            ->formatStateUsing(fn ($record) => $record->merchantProfile?->business_name ?? '—'),
                        TextEntry::make('delivery_address')->label('Adresse de livraison')->columnSpanFull(),
                    ]),

                Section::make('Articles commandés')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('product_name')->label('Produit'),
                                TextEntry::make('quantity')->label('Quantité'),
                                TextEntry::make('unit_price')->label('Prix unitaire')->money('XOF'),
                                TextEntry::make('line_total')->label('Total ligne')->money('XOF'),
                            ]),
                    ]),

                // Paiement des MARCHANDISES uniquement — les frais de
                // livraison sont réglés séparément via le Ride (voir
                // ci-dessous), jamais tracés par une Transaction propre
                // aujourd'hui (trou de traçabilité déjà identifié, hors
                // scope de cette section).
                Section::make('Paiement des marchandises')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('paymentRequest.status')->label('Statut du paiement')
                            ->formatStateUsing(fn ($record) => $record->paymentRequest?->status ?? 'Aucune demande'),
                        TextEntry::make('paymentRequest.amount')->label('Montant')->money('XOF')->placeholder('—'),
                        TextEntry::make('paymentRequest.paid_at')->label('Payé le')->dateTime('d/m/Y H:i')->placeholder('Pas encore payé'),
                    ]),

                // Lien croisé vers le Ride de livraison — voir RideResource
                // pour le lien inverse. N'existe qu'après confirmation
                // marchand (order.status = confirmed).
                Section::make('Livraison')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('ride.status')->label('Statut de la course')
                            ->formatStateUsing(fn ($record) => $record->ride?->status ?? 'Pas encore confirmée'),
                        TextEntry::make('ride.driver.name')->label('Chauffeur')
                            ->formatStateUsing(fn ($record) => $record->ride?->driver?->name ?? '—'),
                        TextEntry::make('ride.id')
                            ->label('Fiche course')
                            ->formatStateUsing(fn ($record) => $record->ride ? 'Voir la course →' : '—')
                            ->url(fn ($record) => $record->ride
                                ? RideResource::getUrl('view', ['record' => $record->ride])
                                : null)
                            ->color('primary'),
                    ]),
            ]);
    }
}
