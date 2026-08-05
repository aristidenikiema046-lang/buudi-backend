<?php

namespace App\Filament\Resources\Rides\Schemas;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RideInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Course')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('service_type')->label('Type')->badge(),
                        TextEntry::make('status')->label('Statut')->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                'in_progress' => 'info',
                                'accepted', 'arrived' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('price')->label('Prix')->money('XOF'),
                        TextEntry::make('payment_method')->label('Paiement'),
                        TextEntry::make('distance_km')->label('Distance (km)')->placeholder('—'),
                        TextEntry::make('duration_min')->label('Durée (min)')->placeholder('—'),
                        TextEntry::make('created_at')->label('Créée le')->dateTime('d/m/Y H:i'),
                    ]),

                Section::make('Trajet')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('pickup_address')->label('Départ'),
                        TextEntry::make('destination_address')->label('Arrivée'),
                    ]),

                Section::make('Participants')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('passenger.name')->label('Passager')
                            ->formatStateUsing(fn ($record) => $record->passenger
                                ? "{$record->passenger->name} — {$record->passenger->phone}"
                                : '—'),
                        TextEntry::make('driver.name')->label('Chauffeur')
                            ->placeholder('Pas encore assigné')
                            ->formatStateUsing(fn ($record) => $record->driver
                                ? "{$record->driver->name} — {$record->driver->phone}"
                                : null),
                    ]),

                // Uniquement pertinent pour service_type=Livraison — le
                // destinataire n'est pas forcément un utilisateur Buudi,
                // d'où ces champs séparés plutôt qu'une relation.
                Section::make('Colis')
                    ->columns(3)
                    ->visible(fn ($record) => $record->service_type === 'Livraison')
                    ->schema([
                        TextEntry::make('recipient_name')->label('Destinataire')->placeholder('—'),
                        TextEntry::make('recipient_phone')->label('Téléphone destinataire')->placeholder('—'),
                        TextEntry::make('package_type')->label('Type de colis')->placeholder('—'),
                        TextEntry::make('package_weight_kg')->label('Poids (kg)')->placeholder('—'),
                        TextEntry::make('package_code')->label('Code colis')->placeholder('—'),
                        TextEntry::make('delivery_instructions')->label('Instructions')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                // Lien croisé vers l'Order d'origine (voir OrderResource pour
                // le lien inverse) — jamais de fusion des deux objets, voir
                // la décision prise pour ce chantier.
                Section::make('Commande Supermarché liée')
                    ->columns(3)
                    ->visible(fn ($record) => $record->service_type === 'Supermarché')
                    ->schema([
                        TextEntry::make('order.status')->label('Statut commande')->placeholder('Introuvable'),
                        TextEntry::make('order.subtotal')->label('Sous-total marchandises')->money('XOF')->placeholder('—'),
                        TextEntry::make('order.id')
                            ->label('Fiche commande')
                            ->formatStateUsing(fn ($record) => $record->order ? 'Voir la commande →' : '—')
                            ->url(fn ($record) => $record->order
                                ? OrderResource::getUrl('view', ['record' => $record->order])
                                : null)
                            ->color('primary'),
                    ]),

                Section::make('Historique')
                    ->columns(5)
                    ->schema([
                        TextEntry::make('accepted_at')->label('Acceptée')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('arrived_at')->label('Arrivée')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('started_at')->label('Démarrée')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('completed_at')->label('Terminée')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('cancelled_at')->label('Annulée')->dateTime('d/m/Y H:i')->placeholder('—'),
                    ]),
            ]);
    }
}
