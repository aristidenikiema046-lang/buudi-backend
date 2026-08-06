<?php

namespace App\Services;

use App\Models\DriverDebt;
use App\Models\DriverSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Centralise les vérifications "ce chauffeur a-t-il le droit de travailler ?"
 * (approbation admin, abonnement journalier, dette commission, documents
 * valides), reprises telles quelles depuis DriverProfileController::
 * toggleOnlineStatus() — utilisée aussi par DriverRideController pour que
 * getPendingRides()/acceptRide() appliquent exactement les mêmes règles.
 */
class DriverEligibilityService
{
    /**
     * Vérifie que le chauffeur peut passer OFFLINE -> ONLINE (ou, pour
     * acceptRide()/getPendingRides(), qu'il remplit les conditions de base
     * pour travailler). Ne vérifie PAS is_online lui-même : c'est l'état
     * que toggleOnlineStatus() est justement en train de faire basculer.
     *
     * @return array{error: JsonResponse}|array{driver_profile: \App\Models\DriverProfile}
     */
    public function checkCanGoOnline(User $driver): array
    {
        $driverProfile = $driver->driverProfile;

        if (!$driverProfile) {
            return ['error' => response()->json([
                'success' => false,
                'code'    => 'PROFILE_NOT_FOUND',
                'message' => 'Profil chauffeur introuvable.',
            ], 404)];
        }

        if ($driverProfile->status !== 'approved') {
            return ['error' => response()->json([
                'success' => false,
                'code'    => 'NOT_APPROVED',
                'message' => "Votre compte est en attente d'approbation par l'administration.",
            ], 403)];
        }

        $hasActiveSubscription = DriverSubscription::where('driver_id', $driver->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();

        if (!$hasActiveSubscription) {
            return ['error' => response()->json([
                'success' => false,
                'code'    => 'SUBSCRIPTION_REQUIRED',
                'message' => "Veuillez payer vos frais d'activation de 2 000 FCFA/jour pour passer en ligne.",
            ], 402)];
        }

        $hasExpiredDebt = DriverDebt::where('driver_id', $driver->id)
            ->where('is_paid', false)
            ->where('due_date', '<', now())
            ->exists();

        if ($hasExpiredDebt) {
            return ['error' => response()->json([
                'success' => false,
                'code'    => 'EXPIRED_DEBT',
                'message' => 'Votre compte est bloqué. Vous avez une dette sur commission impayée depuis plus de 24 heures.',
            ], 403)];
        }

        if ($driverProfile->license_expires_at && $driverProfile->license_expires_at->isPast()) {
            return ['error' => response()->json([
                'success' => false,
                'code'    => 'EXPIRED_LICENSE',
                'message' => 'Votre permis de conduire a expiré. Merci de le renouveler pour repasser en ligne.',
            ], 403)];
        }

        if ($driverProfile->insurance_expires_at && $driverProfile->insurance_expires_at->isPast()) {
            return ['error' => response()->json([
                'success' => false,
                'code'    => 'EXPIRED_INSURANCE',
                'message' => 'Votre assurance véhicule a expiré. Merci de la renouveler pour repasser en ligne.',
            ], 403)];
        }

        return ['driver_profile' => $driverProfile];
    }

    /**
     * Vérifie que le chauffeur peut effectivement travailler maintenant :
     * mêmes conditions que checkCanGoOnline(), plus is_online = true.
     * Utilisée par getPendingRides() et acceptRide() : voir ou accepter des
     * courses suppose d'être en ligne, pas seulement éligible à l'être.
     *
     * @return array{error: JsonResponse}|array{driver_profile: \App\Models\DriverProfile}
     */
    public function checkCanWork(User $driver): array
    {
        $check = $this->checkCanGoOnline($driver);

        if (isset($check['error'])) {
            return $check;
        }

        if (!$check['driver_profile']->is_online) {
            return ['error' => response()->json([
                'success' => false,
                'code'    => 'DRIVER_OFFLINE',
                'message' => 'Vous devez être en ligne pour effectuer cette action.',
            ], 403)];
        }

        return $check;
    }
}
