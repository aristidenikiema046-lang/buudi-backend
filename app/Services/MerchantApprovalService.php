<?php

namespace App\Services;

use App\Jobs\CreateNotificationJob;
use App\Models\MerchantProfile;

class MerchantApprovalService
{
    /**
     * Extrait de AdminMerchantController::updateStatus() — voir
     * DriverApprovalService pour la même logique côté chauffeur, même
     * raisonnement : un seul endroit à maintenir pour l'API et le panel.
     */
    public function updateStatus(MerchantProfile $merchantProfile, string $status, ?string $rejectionReason): void
    {
        $merchantProfile->update([
            'status' => $status,
            'rejection_reason' => $rejectionReason,
        ]);

        CreateNotificationJob::dispatch(
            $merchantProfile->user_id,
            'account_status_changed',
            $status === 'approved' ? 'Compte validé' : 'Compte non validé',
            $status === 'approved'
                ? 'Votre compte commerçant a été validé.'
                : ('Votre dossier n\'a pas été validé. Motif : ' . ($rejectionReason ?? 'Non spécifié')),
            ['new_status' => $status]
        );
    }
}
