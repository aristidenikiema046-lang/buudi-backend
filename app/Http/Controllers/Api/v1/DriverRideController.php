<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Jobs\CreateNotificationJob;
use App\Support\GeoDistance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Ride;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\DriverDebt;
use App\Services\DriverEligibilityService;
use Carbon\Carbon;

class DriverRideController extends Controller
{
    /**
     * Types de course qu'un véhicule donné peut honorer. Une Voiture peut
     * prendre n'importe quel niveau de VTC, une Moto/Vélo ne peut faire que
     * de la livraison de colis (pas de passager).
     *
     * "Supermarché" est ouvert aux deux : au lancement les paniers restent
     * une sélection curée de quelques dizaines de références (pas un
     * hypermarché complet), donc une Moto/Vélo peut généralement l'honorer
     * comme pour Livraison — mais rien n'empêche une Voiture de le faire
     * aussi, ce qui élargit le pool de chauffeurs disponibles pendant que
     * peu de chauffeurs dédiés existent encore.
     */
    private function allowedServiceTypesFor(?string $vehicleType): array
    {
        return match ($vehicleType) {
            'Voiture' => ['OK Taxi', 'OK Confort', 'OK Van', 'Supermarché'],
            'Moto', 'Vélo' => ['Livraison', 'Supermarché'],
            default => [],
        };
    }

    /**
     * GET /v1/driver/rides/pending — Courses en attente proches de la position
     * du chauffeur, filtrées par compatibilité vehicle_type/service_type.
     * La position n'étant stockée nulle part en base (driver_profiles n'a pas
     * de latitude/longitude), elle doit être envoyée à chaque appel via les
     * query params ?lat=...&lng=....
     */
    public function getPendingRides(Request $request, DriverEligibilityService $eligibility)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $check = $eligibility->checkCanWork(Auth::user());
        if (isset($check['error'])) {
            return $check['error'];
        }

        $vehicleType = $check['driver_profile']->vehicle_type ?? null;
        $allowedServiceTypes = $this->allowedServiceTypesFor($vehicleType);

        if (empty($allowedServiceTypes)) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de déterminer les courses compatibles : profil chauffeur ou type de véhicule manquant.',
            ], 422);
        }

        $driverLat = (float) $request->lat;
        $driverLng = (float) $request->lng;
        $radiusKm = (float) ($request->radius_km ?? 10);

        $rides = Ride::where('status', 'pending')
            ->whereIn('service_type', $allowedServiceTypes)
            ->get()
            ->map(function ($ride) use ($driverLat, $driverLng) {
                $ride->distance_km_from_driver = round(
                    GeoDistance::haversineKm($driverLat, $driverLng, (float) $ride->pickup_latitude, (float) $ride->pickup_longitude),
                    2
                );
                return $ride;
            })
            ->filter(fn ($ride) => $ride->distance_km_from_driver <= $radiusKm)
            ->sortBy('distance_km_from_driver')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rides,
        ], 200);
    }

    /**
     * Récupérer la course active du chauffeur (si elle existe)
     */
    public function getActiveRide(Request $request)
    {
        $user = Auth::user();
        
        // Recherche une course assignée au chauffeur avec un statut actif
        $ride = Ride::where('driver_id', $user->id)
            ->whereIn('status', ['accepted', 'arrived', 'in_progress'])
            ->with('passenger') // relation vers le client
            ->first();

        if (!$ride) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Aucune course en cours.'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $ride
        ]);
    }

    /**
     * GET /v1/driver/rides/history — courses/livraisons terminées du chauffeur,
     * les plus récentes d'abord. Même filtre que le compteur "today_rides_count"
     * de getDashboard(), sans la restriction "aujourd'hui".
     */
    public function getRideHistory(Request $request)
    {
        $user = Auth::user();

        $rides = Ride::where('driver_id', $user->id)
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rides,
        ], 200);
    }

    /**
     * Récupère le résumé financier (Solde + Gains du jour) & Courses disponibles
     */
    public function getDashboard(Request $request)
    {
        $user = Auth::user();

        // 1. Portefeuille & Solde
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00]
        );

        // 2. Gains du jour (Total des crédits de type ride_earning aujourd'hui)
        $todayEarnings = Transaction::where('wallet_id', $wallet->id)
            ->where('type', 'credit')
            ->where('category', 'ride_earning')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount');

        // 3. Nombre de courses réalisées aujourd'hui
        $todayRidesCount = Ride::where('driver_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('completed_at', Carbon::today())
            ->count();

        // 4. Liste des courses en attente (pending) à proximité
        $availableRides = Ride::where('status', 'pending')
            ->latest()
            ->take(10)
            ->get();

        // Récupération sécurisée de is_online (que ce soit sur la table users ou driverProfile)
        $isOnline = false;
        if (isset($user->is_online)) {
            $isOnline = (bool) $user->is_online;
        } elseif (method_exists($user, 'driverProfile') && $user->driverProfile) {
            $isOnline = (bool) $user->driverProfile->is_online;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'driver_name' => $user->name,
                'balance' => (float) $wallet->balance,
                'today_earnings' => (float) $todayEarnings,
                'today_rides_count' => $todayRidesCount,
                'available_rides' => $availableRides,
                'is_online' => $isOnline, 
            ]
        ], 200);
    }

    /**
     * Accepter une course.
     *
     * verrouillée avec lockForUpdate() à l'intérieur d'une transaction : si
     * deux chauffeurs appellent cet endpoint en même temps pour la même
     * course, le deuxième attend que le premier ait fini (commit ou rollback)
     * avant de lire le statut — il voit donc forcément "accepted" et non
     * "pending", et se fait rejeter proprement au lieu d'écraser le premier.
     */
    public function acceptRide($id, DriverEligibilityService $eligibility)
    {
        $user = Auth::user();

        $check = $eligibility->checkCanWork($user);
        if (isset($check['error'])) {
            return $check['error'];
        }

        $result = DB::transaction(function () use ($id, $user) {
            $ride = Ride::where('id', $id)->lockForUpdate()->first();

            if (!$ride) {
                return ['error' => 404, 'message' => 'Course introuvable.'];
            }

            if ($ride->status !== 'pending') {
                return ['error' => 400, 'message' => 'Cette course a déjà été prise par un autre chauffeur.'];
            }

            $ride->update([
                'driver_id'   => $user->id,
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);

            CreateNotificationJob::dispatch(
                $ride->passenger_id,
                'ride_status_changed',
                'Chauffeur trouvé',
                'Un chauffeur a accepté votre course.',
                ['ride_id' => $ride->id, 'new_status' => 'accepted']
            );

            return ['ride' => $ride];
        });

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['error']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Course acceptée avec succès !',
            'ride'    => $result['ride']
        ], 200);
    }

    /**
     * Annuler une course déjà acceptée par le chauffeur connecté.
     * - "accepted"/"arrived" -> repasse à "pending" (driver_id et horodatages
     *   remis à zéro) pour qu'un autre chauffeur puisse la reprendre.
     * - "in_progress" -> "cancelled" directement : on ne remet pas dans le
     *   pool une course déjà commencée à mi-chemin.
     * Même verrouillage que acceptRide() pour éviter les états incohérents
     * si le client annule au même moment côté client.
     */
    public function cancelRide($id)
    {
        $user = Auth::user();

        $result = DB::transaction(function () use ($id, $user) {
            $ride = Ride::where('id', $id)->where('driver_id', $user->id)->lockForUpdate()->first();

            if (!$ride) {
                return ['error' => 404, 'message' => 'Course introuvable.'];
            }

            if (!in_array($ride->status, ['accepted', 'arrived', 'in_progress'], true)) {
                return ['error' => 400, 'message' => 'Cette course ne peut pas être annulée dans son état actuel.'];
            }

            if ($ride->status === 'in_progress') {
                $ride->update([
                    'status'       => 'cancelled',
                    'cancelled_at' => now(),
                ]);

                CreateNotificationJob::dispatch(
                    $ride->passenger_id,
                    'ride_status_changed',
                    'Course annulée',
                    'Votre course a été annulée par le chauffeur.',
                    ['ride_id' => $ride->id, 'new_status' => 'cancelled']
                );
            } else {
                $ride->update([
                    'status'      => 'pending',
                    'driver_id'   => null,
                    'accepted_at' => null,
                    'arrived_at'  => null,
                ]);

                CreateNotificationJob::dispatch(
                    $ride->passenger_id,
                    'ride_status_changed',
                    'Chauffeur indisponible',
                    'Votre chauffeur a annulé, nous recherchons un nouveau chauffeur pour vous.',
                    ['ride_id' => $ride->id, 'new_status' => 'pending']
                );
            }

            return ['ride' => $ride];
        });

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['error']);
        }

        $message = $result['ride']->status === 'cancelled'
            ? 'Course annulée.'
            : 'Course annulée : elle est de nouveau disponible pour un autre chauffeur.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'ride'    => $result['ride'],
        ], 200);
    }

    /**
     * Signaler l'arrivée au point de prise en charge
     */
    public function arriveAtPickup($id)
    {
        $user = Auth::user();
        $ride = Ride::where('id', $id)->where('driver_id', $user->id)->first();

        if (!$ride) {
            return response()->json(['success' => false, 'message' => 'Course introuvable.'], 404);
        }

        $ride->update([
            'status'     => 'arrived',
            'arrived_at' => now(),
        ]);

        CreateNotificationJob::dispatch(
            $ride->passenger_id,
            'ride_status_changed',
            'Chauffeur arrivé',
            'Votre chauffeur est arrivé au point de départ.',
            ['ride_id' => $ride->id, 'new_status' => 'arrived']
        );

        return response()->json(['success' => true, 'message' => 'Statut : Arrivé sur place', 'ride' => $ride], 200);
    }

    /**
     * Démarrer la course
     */
    public function startRide($id)
    {
        $user = Auth::user();
        $ride = Ride::where('id', $id)->where('driver_id', $user->id)->first();

        if (!$ride) {
            return response()->json(['success' => false, 'message' => 'Course introuvable.'], 404);
        }

        $ride->update([
            'status'     => 'in_progress',
            'started_at' => now(),
        ]);

        CreateNotificationJob::dispatch(
            $ride->passenger_id,
            'ride_status_changed',
            'Course démarrée',
            'Votre course a commencé.',
            ['ride_id' => $ride->id, 'new_status' => 'in_progress']
        );

        return response()->json(['success' => true, 'message' => 'Course démarrée !', 'ride' => $ride], 200);
    }

    /**
     * Terminer la course + Créditer le Wallet + Enregistrer la commission Buudi (15%)
     */
    public function completeRide($id)
    {
        $user = Auth::user();
        $ride = Ride::where('id', $id)->where('driver_id', $user->id)->first();

        if (!$ride) {
            return response()->json(['success' => false, 'message' => 'Course introuvable.'], 404);
        }

        if ($ride->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'Course déjà terminée.'], 400);
        }

        // 1. Mettre à jour le statut de la course
        $ride->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // 2. Traitement Financier du Chauffeur
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id]);

        // Option A : Paiement par Wallet/Mobile Money/Carte -> Crédit direct dans le wallet
        if ($ride->payment_method !== 'especes') {
            $wallet->increment('balance', $ride->price);

            Transaction::create([
                'wallet_id'    => $wallet->id,
                'amount'       => $ride->price,
                'type'         => 'credit',
                'category'     => 'ride_earning',
                'description'  => "Gain course #{$ride->id}",
                'reference_id' => $ride->id,
            ]);
        } 
        // Option B : Paiement en Espèces (Cash) -> Le chauffeur reçoit l'argent liquide, mais doit la commission de 15% à la plateforme
        else {
            $commissionAmount = $ride->price * 0.15; // 15% de commission

            // La course est déjà marquée completed ci-dessus (pas de transaction
            // englobante) : un problème sur le suivi de la dette ne doit jamais
            // priver le chauffeur de sa confirmation de fin de course. On logue
            // pour rattrapage manuel plutôt que de faire échouer la réponse.
            try {
                DriverDebt::create([
                    'driver_id'         => $user->id,
                    'ride_id'           => $ride->id,
                    'commission_amount' => $commissionAmount,
                    'is_paid'           => false,
                    'due_date'          => now()->addHours(24), // Exigible sous 24h
                ]);
            } catch (\Throwable $e) {
                Log::error('Échec de création DriverDebt (commission course cash).', [
                    'ride_id' => $ride->id,
                    'driver_id' => $user->id,
                    'commission_amount' => $commissionAmount,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        CreateNotificationJob::dispatch(
            $ride->passenger_id,
            'ride_status_changed',
            'Course terminée',
            "Votre course est terminée. Merci d'avoir utilisé Buudi !",
            ['ride_id' => $ride->id, 'new_status' => 'completed']
        );

        return response()->json([
            'success' => true,
            'message' => 'Course terminée avec succès !',
            'ride'    => $ride
        ], 200);
    }
}