<?php

namespace App\Services;

use App\Jobs\CreateNotificationJob;
use App\Models\DriverProfile;
use App\Notifications\DriverAccountStatusUpdated;
use Kreait\Firebase\Contract\Messaging;

class DriverApprovalService
{
    /**
     * Extrait de AdminDriverController::updateStatus() pour être réutilisé
     * tel quel par le panel Filament — même comportement, mêmes
     * notifications (mail + FCM + in-app), un seul endroit à maintenir au
     * lieu de dupliquer cette logique entre l'API et le panel admin.
     */
    public function updateStatus(DriverProfile $driverProfile, string $status, ?string $rejectionReason, Messaging $messaging): void
    {
        $driverProfile->update([
            'status' => $status,
            'rejection_reason' => $rejectionReason,
        ]);

        $user = $driverProfile->user;
        if ($user && $status === 'approved') {
            $user->update(['is_active' => true]);
        }

        if ($user) {
            try {
                $notification = new DriverAccountStatusUpdated($status, $rejectionReason);
                $user->notify($notification);
                $notification->sendFcmNotification($user, $messaging);

                CreateNotificationJob::dispatch(
                    $user->id,
                    'account_status_changed',
                    $status === 'approved' ? 'Compte validé' : 'Compte non validé',
                    $status === 'approved'
                        ? 'Votre compte chauffeur a été validé.'
                        : ('Votre dossier n\'a pas été validé. Motif : ' . ($rejectionReason ?? 'Non spécifié')),
                    ['new_status' => $status]
                );
            } catch (\Exception $e) {
                \Log::error("Erreur d'envoi de notification chauffeur : " . $e->getMessage());
            }
        }
    }
}
