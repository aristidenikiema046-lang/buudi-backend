<?php

namespace App\Http\Controllers\Api\v1\Client;

use App\Http\Controllers\Controller;
use App\Jobs\CreateNotificationJob;
use App\Jobs\RidePendingSignal;
use App\Models\Ride;
use App\Models\RideReview;
use App\Services\RidePricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RideController extends Controller
{
    /**
     * POST /v1/client/rides — Créer une demande de course (statut initial : pending).
     * Un chauffeur disponible pourra ensuite l'accepter via DriverRideController::acceptRide.
     */
    public function store(Request $request, RidePricingService $pricingService)
    {
        $validator = Validator::make($request->all(), [
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'pickup_address' => 'required|string|max:255',
            'destination_lat' => 'required|numeric',
            'destination_lng' => 'required|numeric',
            'destination_address' => 'required|string|max:255',
            'service_type' => 'required|string|in:OK Taxi,OK Confort,OK Van,Livraison',
            'payment_method' => 'required|string|in:wallet,mobile_money,carte,especes',
            'estimated_price' => 'required|numeric|min:0',
            'estimated_distance_km' => 'required|numeric|min:0',
            'estimated_duration_min' => 'required|integer|min:0',
            // Uniquement pertinents quand service_type = "Livraison", mais
            // optionnels dans tous les cas (pas de règle conditionnelle) :
            // ils restent simplement vides pour une course VTC classique.
            'recipient_name' => 'nullable|string|max:255',
            'recipient_phone' => 'nullable|string|max:20',
            'package_type' => 'nullable|string|max:255',
            'package_weight_kg' => 'nullable|numeric|min:0',
            'package_code' => 'nullable|string|max:255',
            'delivery_instructions' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Le prix est recalculé côté serveur pour toute course VTC (pas la
        // Livraison, dont la logistique/tarification n'est pas encore gérée
        // ici) : estimated_price du client ne sert plus qu'à détecter les
        // écarts suspects (RidePricingService::logIfMismatch), il est ignoré
        // pour le prix final facturé.
        if ($request->service_type === 'Livraison') {
            $price = $request->estimated_price;
            $distanceKm = $request->estimated_distance_km;
            $durationMin = $request->estimated_duration_min;
        } else {
            $pricing = $pricingService->calculate(
                $request->service_type,
                (float) $request->pickup_lat,
                (float) $request->pickup_lng,
                (float) $request->destination_lat,
                (float) $request->destination_lng,
                (float) $request->estimated_price
            );

            $price = $pricing['price'];
            $distanceKm = $pricing['distance_km'];
            $durationMin = $pricing['duration_min'];
        }

        $ride = Ride::create([
            'passenger_id' => Auth::id(),
            'pickup_address' => $request->pickup_address,
            'pickup_latitude' => $request->pickup_lat,
            'pickup_longitude' => $request->pickup_lng,
            'destination_address' => $request->destination_address,
            'destination_latitude' => $request->destination_lat,
            'destination_longitude' => $request->destination_lng,
            'service_type' => $request->service_type,
            'price' => $price,
            'distance_km' => $distanceKm,
            'duration_min' => $durationMin,
            'payment_method' => $request->payment_method,
            'status' => 'pending',
            'recipient_name' => $request->recipient_name,
            'recipient_phone' => $request->recipient_phone,
            'package_type' => $request->package_type,
            'package_weight_kg' => $request->package_weight_kg,
            'package_code' => $request->package_code,
            'delivery_instructions' => $request->delivery_instructions,
        ]);

        RidePendingSignal::dispatch($ride->service_type);

        return response()->json([
            'success' => true,
            'message' => 'Demande de course créée, recherche d\'un chauffeur en cours.',
            'data' => $ride,
        ], 201);
    }

    /**
     * GET /v1/client/rides/{id} — Suivre le statut d'une course.
     */
    public function show(Request $request, string $id)
    {
        $ride = Ride::where('id', $id)
            ->where('passenger_id', Auth::id())
            ->with('driver')
            ->first();

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'Course introuvable.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ride,
        ], 200);
    }

    /**
     * POST /v1/client/rides/{id}/cancel — Annuler une course pas encore terminée.
     */
    public function cancel(Request $request, string $id)
    {
        $ride = Ride::where('id', $id)
            ->where('passenger_id', Auth::id())
            ->first();

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'Course introuvable.',
            ], 404);
        }

        if (in_array($ride->status, ['completed', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Cette course ne peut plus être annulée.',
            ], 400);
        }

        $ride->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        // Seule exception à "on ne notifie que le passager" (voir DriverRideController) :
        // ici c'est le passager qui agit, donc le destinataire est le chauffeur —
        // s'il y en avait déjà un d'assigné (sinon rien à notifier).
        if ($ride->driver_id) {
            CreateNotificationJob::dispatch(
                $ride->driver_id,
                'ride_status_changed',
                'Course annulée',
                'Le client a annulé la course.',
                ['ride_id' => $ride->id, 'new_status' => 'cancelled']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Course annulée.',
            'data' => $ride,
        ], 200);
    }

    /**
     * POST /v1/client/rides/{id}/review — Le passager note le chauffeur/
     * livreur après une course terminée. Un seul avis par course (contrainte
     * unique sur ride_reviews.ride_id, vérifiée aussi explicitement ici pour
     * un message propre plutôt que de laisser remonter l'erreur SQL brute).
     */
    public function review(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ride = Ride::where('id', $id)
            ->where('passenger_id', Auth::id())
            ->first();

        if (!$ride) {
            return response()->json([
                'success' => false,
                'message' => 'Course introuvable.',
            ], 404);
        }

        if ($ride->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cette course n\'est pas encore terminée.',
            ], 400);
        }

        if (!$ride->driver_id) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun chauffeur associé à cette course.',
            ], 400);
        }

        if (RideReview::where('ride_id', $ride->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette course a déjà été notée.',
            ], 400);
        }

        $review = DB::transaction(function () use ($ride, $request) {
            $review = RideReview::create([
                'ride_id' => $ride->id,
                'reviewed_user_id' => $ride->driver_id,
                'reviewer_id' => Auth::id(),
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            RideReview::recalculateRatingFor($ride->driver_id);

            return $review;
        });

        $driverProfile = $ride->driver->driverProfile;

        return response()->json([
            'success' => true,
            'message' => 'Merci pour votre avis !',
            'data' => [
                'review' => $review,
                'driver_rating_average' => $driverProfile->rating_average,
                'driver_rating_count' => $driverProfile->rating_count,
            ],
        ], 201);
    }
}
