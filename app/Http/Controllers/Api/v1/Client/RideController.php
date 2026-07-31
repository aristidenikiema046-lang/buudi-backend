<?php

namespace App\Http\Controllers\Api\v1\Client;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RideController extends Controller
{
    /**
     * POST /v1/client/rides — Créer une demande de course (statut initial : pending).
     * Un chauffeur disponible pourra ensuite l'accepter via DriverRideController::acceptRide.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pickup_lat' => 'required|numeric',
            'pickup_lng' => 'required|numeric',
            'pickup_address' => 'required|string|max:255',
            'destination_lat' => 'required|numeric',
            'destination_lng' => 'required|numeric',
            'destination_address' => 'required|string|max:255',
            'service_type' => 'required|string|in:OK Taxi,OK Confort,OK Van',
            'payment_method' => 'required|string|in:wallet,mobile_money,carte,especes',
            'estimated_price' => 'required|numeric|min:0',
            'estimated_distance_km' => 'required|numeric|min:0',
            'estimated_duration_min' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
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
            'price' => $request->estimated_price,
            'distance_km' => $request->estimated_distance_km,
            'duration_min' => $request->estimated_duration_min,
            'payment_method' => $request->payment_method,
            'status' => 'pending',
        ]);

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

        return response()->json([
            'success' => true,
            'message' => 'Course annulée.',
            'data' => $ride,
        ], 200);
    }
}
