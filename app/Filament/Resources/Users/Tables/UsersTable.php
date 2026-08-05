<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Téléphone')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rôle')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'driver' => 'info',
                        'merchant' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Inscription')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Rôle')
                    ->options([
                        'client' => 'Client',
                        'driver' => 'Chauffeur/Livreur',
                        'merchant' => 'Marchand',
                        'admin' => 'Admin',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
