<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Jobs\CreateNotificationJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\DriverSubscription;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\DriverEligibilityService;

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
                // vehicle_type ("Voiture"/"Moto"/"Vélo") permet à buudi_partner_app
                // de choisir le bon parcours (Chauffeur ou Livreur) après connexion.
                'vehicle_type' => $profile->vehicle_type ?? null,
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
    public function toggleOnlineStatus(Request $request, DriverEligibilityService $eligibility)
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

        // Si le chauffeur essaye de passer EN LIGNE (is_online est actuellement false) :
        // abonnement actif, pas de dette expirée, documents valides. Même logique que
        // celle utilisée par DriverRideController::getPendingRides()/acceptRide() (voir
        // DriverEligibilityService) — 'approved' est revérifié ici sans coût (profil déjà
        // chargé en mémoire), la vérification ci-dessus reste inchangée pour ne rien
        // modifier au comportement de sortie de ligne (is_online -> false, jamais bloqué
        // par ces règles financières).
        if (!$driverProfile->is_online) {
            $check = $eligibility->checkCanGoOnline($user);
            if (isset($check['error'])) {
                return $check['error'];
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
     * POST /v1/driver/location — Reçoit la position courante du chauffeur
     * (appelé toutes les 5s tant qu'il est en ligne, côté app). Alimentera
     * la Phase 3 (cascade de dispatch par proximité) via last_latitude/
     * last_longitude/last_location_at sur driver_profiles. Volontairement
     * minimal : pas de vérification d'éligibilité ici (ce n'est pas une
     * action métier, juste un signal de position best-effort), la fréquence
     * d'appel impose de rester rapide.
     */
    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $driverProfile = Auth::user()->driverProfile;

        if (!$driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil chauffeur introuvable.',
            ], 404);
        }

        $driverProfile->update([
            'last_latitude' => $request->latitude,
            'last_longitude' => $request->longitude,
            'last_location_at' => now(),
        ]);

        return response()->json(['success' => true], 200);
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

        // 2. Débit du wallet (même pattern que WalletController::withdraw()) puis
        // création de l'abonnement, dans la même transaction : si le solde est
        // insuffisant, rien n'est créé (rollback via l'exception).
        try {
            $subscription = DB::transaction(function () use ($user) {
                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

                if (!$wallet || $wallet->balance < 2000) {
                    throw new \RuntimeException('INSUFFICIENT_FUNDS');
                }

                $wallet->decrement('balance', 2000);

                Transaction::create([
                    'wallet_id'   => $wallet->id,
                    'amount'      => 2000,
                    'type'        => 'debit',
                    'category'    => 'subscription',
                    'description' => 'Pass journalier chauffeur (24h)',
                    'status'      => 'completed',
                ]);

                return DriverSubscription::create([
                    'driver_id'   => $user->id,
                    'amount_paid' => 2000,
                    'status'      => 'active',
                    'starts_at'   => now(),
                    'expires_at'  => now()->addHours(24),
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_FUNDS') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solde insuffisant pour payer le pass journalier (2 000 FCFA requis).',
                ], 400);
            }
            throw $e;
        }

        CreateNotificationJob::dispatch(
            $user->id,
            'wallet_transaction',
            'Pass journalier activé',
            'Votre pass journalier de 2 000 FCFA a été activé pour 24h.',
            ['subscription_id' => $subscription->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Pass journalier de 2 000 FCFA activé avec succès pour 24h !',
            'expires_at' => $subscription->expires_at->toIso8601String(),
        ], 200);
    }
}