<?php

namespace App\Filament\Resources\MerchantProfiles\Pages;

use App\Filament\Resources\MerchantProfiles\MerchantProfileResource;
use App\Services\MerchantApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;

class EditMerchantProfile extends EditRecord
{
    protected static string $resource = MerchantProfileResource::class;

    // Pas de DeleteAction : voir MerchantProfileResource::canDelete().
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
                    app(MerchantApprovalService::class)->updateStatus($this->record, 'approved', null);
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
                    app(MerchantApprovalService::class)->updateStatus($this->record, 'rejected', $data['rejection_reason']);
                    $this->fillForm();
                }),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Paramètres mis à jour.';
    }
}
