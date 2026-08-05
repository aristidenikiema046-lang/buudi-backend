<?php

namespace App\Filament\Resources\DriverProfiles\Tables;

use App\Services\DriverApprovalService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Kreait\Firebase\Contract\Messaging;

class DriverProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->label('Inscription')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'pending' => 'En attente',
                        'approved' => 'Approuvé',
                        'rejected' => 'Rejeté',
                    ]),
            ])
            ->recordActions([
                EditAction::make()->label('Voir le dossier'),

                Action::make('approve')
                    ->label('Approuver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'approved')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        app(DriverApprovalService::class)->updateStatus(
                            $record,
                            'approved',
                            null,
                            app(Messaging::class)
                        );
                    }),

                Action::make('reject')
                    ->label('Rejeter')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status !== 'rejected')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Motif du rejet')
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        app(DriverApprovalService::class)->updateStatus(
                            $record,
                            'rejected',
                            $data['rejection_reason'],
                            app(Messaging::class)
                        );
                    }),
            ]);
    }
}
