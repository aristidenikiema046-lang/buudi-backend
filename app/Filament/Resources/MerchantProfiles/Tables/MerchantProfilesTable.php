<?php

namespace App\Filament\Resources\MerchantProfiles\Tables;

use App\Services\MerchantApprovalService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MerchantProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business_name')
                    ->label('Commerce')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Titulaire')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                ToggleColumn::make('is_supermarket')
                    ->label('Supermarché'),
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
                TernaryFilter::make('is_supermarket')
                    ->label('Supermarché'),
            ])
            ->recordActions([
                EditAction::make()->label('Voir le dossier'),

                Action::make('approve')
                    ->label('Approuver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status !== 'approved')
                    ->requiresConfirmation()
                    ->action(fn ($record) => app(MerchantApprovalService::class)->updateStatus($record, 'approved', null)),

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
                    ->action(fn ($record, array $data) => app(MerchantApprovalService::class)->updateStatus($record, 'rejected', $data['rejection_reason'])),
            ]);
    }
}
