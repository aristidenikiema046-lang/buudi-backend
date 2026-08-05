<?php

namespace App\Filament\Resources\Rides\Tables;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RidesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('service_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Livraison' => 'warning',
                        'Supermarché' => 'success',
                        default => 'info',
                    }),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'in_progress' => 'info',
                        'accepted', 'arrived' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('passenger.name')
                    ->label('Passager')
                    ->searchable(),
                TextColumn::make('driver.name')
                    ->label('Chauffeur')
                    ->placeholder('Non assigné')
                    ->searchable(),
                TextColumn::make('price')
                    ->label('Prix')
                    ->money('XOF')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'pending' => 'En attente',
                        'accepted' => 'Acceptée',
                        'arrived' => 'Chauffeur arrivé',
                        'in_progress' => 'En cours',
                        'completed' => 'Terminée',
                        'cancelled' => 'Annulée',
                    ]),
                SelectFilter::make('service_type')
                    ->label('Type')
                    ->options([
                        'OK Taxi' => 'OK Taxi',
                        'OK Confort' => 'OK Confort',
                        'OK Van' => 'OK Van',
                        'Livraison' => 'Livraison',
                        'Supermarché' => 'Supermarché',
                    ]),
                SelectFilter::make('driver_id')
                    ->label('Chauffeur')
                    ->relationship('driver', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->label('Période')
                    ->schema([
                        DatePicker::make('from')->label('Du'),
                        DatePicker::make('until')->label('Au'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
