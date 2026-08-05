<?php

namespace App\Filament\Resources\DriverProfiles\Pages;

use App\Filament\Resources\DriverProfiles\DriverProfileResource;
use App\Services\DriverApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Kreait\Firebase\Contract\Messaging;

class EditDriverProfile extends EditRecord
{
    protected static string $resource = DriverProfileResource::class;

    // Pas de DeleteAction : dossier de conformité (documents d'identité,
    // permis...), pas de suppression depuis le panel (voir
    // DriverProfileResource::canDelete()).
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approuver')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status !== 'approved')
                ->requiresConfirmation()
                ->action(function () {
                    app(DriverApprovalService::class)->updateStatus(
                        $this->record,
                        'approved',
                        null,
                        app(Messaging::class)
                    );
                    $this->fillForm();
                }),

            Action::make('reject')
                ->label('Rejeter')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->status !== 'rejected')
                ->schema([
                    Textarea::make('rejection_reason')
                        ->label('Motif du rejet')
                        ->required(),
                ])
                ->action(function (array $data) {
                    app(DriverApprovalService::class)->updateStatus(
                        $this->record,
                        'rejected',
                        $data['rejection_reason'],
                        app(Messaging::class)
                    );
                    $this->fillForm();
                }),
        ];
    }

    // Formulaire en lecture seule sauf les 2 DatePicker de validité — pas
    // de bouton "Save" générique qui laisserait croire qu'on peut modifier
    // les documents ou le statut directement.
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Dates de validité mises à jour.';
    }
}
