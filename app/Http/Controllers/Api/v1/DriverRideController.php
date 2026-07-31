<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Ride;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\DriverDebt;
use Carbon\Carbon;

class DriverRideController extends Controller
{
    /**
     * Récupérer la course active du chauffeur (si elle existe)
     */
    public function getActiveRide(Request $request)
    {
        $user = Auth::user();
        
        // Recherche une course assignée au chauffeur avec un statut actif
        $ride = Ride::where('driver_id', $user->id)
            ->whereIn('status', ['accepted', 'arrived', 'in_progress'])
            ->with('client') // relation vers le client
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
     * Accepter une course
     */
    public function acceptRide($id)
    {
        $user = Auth::user();
        $ride = Ride::find($id);

        if (!$ride) {
            return response()->json(['success' => false, 'message' => 'Course introuvable.'], 404);
        }

        if ($ride->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Cette course a déjà été prise par un autre chauffeur.'], 400);
        }

        $ride->update([
            'driver_id'   => $user->id,
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Course acceptée avec succès !',
            'ride'    => $ride
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

        // Option A : Paiement par Wallet/Wave -> Crédit direct dans le wallet
        if ($ride->payment_method !== 'cash') {
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

            DriverDebt::create([
                'driver_id' => $user->id,
                'ride_id'   => $ride->id,
                'amount'    => $commissionAmount,
                'is_paid'   => false,
                'due_date'  => now()->addHours(24), // Exigible sous 24h
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Course terminée avec succès !',
            'ride'    => $ride
        ], 200);
    }
}