<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DriverSubscription;
use App\Models\DriverDebt;

class DriverProfileController extends Controller
{
    /**
     * Récupérer le profil complet et les informations du chauffeur connecté
     */
    public function show(Request $request)
    {
        $user = Auth::user();
        $profile = $user->driverProfile; // Récupère la relation avec les infos du véhicule, etc.

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                // Informations provenant de la relation driverProfile (ou valeurs par défaut)
                'vehicle_model' => $profile->vehicle_model ?? $profile->vehicle ?? 'Véhicule non renseigné',
                'plate_number' => $profile->plate_number ?? '--------',
                'rating' => $profile->rating ?? '5.0',
                'status' => $profile->status ?? 'pending',
                'is_online' => $profile->is_online ?? false,
            ]
        ], 200);
    }

    /**
     * Récupérer l'état actuel de validation du chauffeur
     */
    public function checkStatus()
    {
        $user = Auth::user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil chauffeur non trouvé.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $profile->status,
            'rejection_reason' => $profile->rejection_reason ?? '',
        ], 200);
    }

    /**
     * Basculer le statut du chauffeur (En ligne / Hors ligne) avec contrôles financiers
     */
    public function toggleOnlineStatus(Request $request)
    {
        $user = Auth::user();
        $driverProfile = $user->driverProfile;

        if (!$driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil chauffeur introuvable.'
            ], 404);
        }

        // 1. Vérification : Validation par l'administration
        if ($driverProfile->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est en attente d\'approbation par l\'administration.'
            ], 403);
        }

        // Si le chauffeur essaye de passer EN LIGNE (is_online est actuellement false)
        if (!$driverProfile->is_online) {

            // 2. Vérification : Abonnement actif de 2000 F / 24h
            $hasActiveSubscription = DriverSubscription::where('driver_id', $user->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->exists();

            if (!$hasActiveSubscription) {
                return response()->json([
                    'success' => false,
                    'code'    => 'SUBSCRIPTION_REQUIRED',
                    'message' => 'Veuillez payer vos frais d\'activation de 2 000 FCFA/jour pour passer en ligne.'
                ], 402); // 402 Payment Required
            }

            // 3. Vérification : Pas de dette expirée (> 24h) sur les commissions espèces
            $hasExpiredDebt = DriverDebt::where('driver_id', $user->id)
                ->where('is_paid', false)
                ->where('due_date', '<', now())
                ->exists();

            if ($hasExpiredDebt) {
                return response()->json([
                    'success' => false,
                    'code'    => 'EXPIRED_DEBT',
                    'message' => 'Votre compte est bloqué. Vous avez une dette sur commission impayée depuis plus de 24 heures.'
                ], 403);
            }
        }

        // Bascule de statut (True <-> False)
        $driverProfile->is_online = !$driverProfile->is_online;
        $driverProfile->save();

        return response()->json([
            'success' => true,
            'message' => $driverProfile->is_online ? 'Vous êtes maintenant en ligne.' : 'Vous êtes maintenant hors ligne.',
            'data' => [
                'is_online' => $driverProfile->is_online
            ]
        ], 200);
    }

    /**
     * Achat du Pass journalier (2 000 FCFA pour 24h)
     */
    public function buyPass(Request $request)
    {
        $user = Auth::user();

        // 1. Vérification si le chauffeur a déjà un abonnement actif
        $existingSubscription = DriverSubscription::where('driver_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà un pass actif.'
            ], 400);
        }

        // 2. Création de l'abonnement actif pour 24h
        DriverSubscription::create([
            'driver_id' => $user->id,
            'amount'    => 2000,
            'status'    => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pass journalier de 2 000 FCFA activé avec succès pour 24h !',
            'expires_at' => now()->addHours(24)->toIso8601String(),
        ], 200);
    }
}