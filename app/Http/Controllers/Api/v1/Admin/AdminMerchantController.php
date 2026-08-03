<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use Illuminate\Http\Request;

class AdminMerchantController extends Controller
{
    /**
     * 1. Lister tous les commerçants en attente de validation
     */
    public function getPendingMerchants()
    {
        $pendingMerchants = MerchantProfile::with('user')
            ->where('status', 'pending')
            ->get();

        return response()->json([
            'success' => true,
            'merchants' => $pendingMerchants,
        ], 200);
    }

    /**
     * 2. Approuver ou rejeter le dossier d'un commerçant
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'nullable|string',
        ]);

        // Recherche par ID du MerchantProfile OU par user_id. Contrairement à
        // driver_profiles (clé auto-incrémentée), merchant_profiles.id est
        // lui-même un UUID : la logique "si c'est un UUID → user_id, sinon
        // → id" de AdminDriverController ne peut pas s'appliquer ici, les
        // deux valeurs sont toujours des UUID. On accepte donc les deux
        // directement, un seul des deux peut matcher.
        $merchantProfile = MerchantProfile::where('id', $id)
            ->orWhere('user_id', $id)
            ->first();

        if (!$merchantProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Dossier commerçant introuvable.',
            ], 404);
        }

        $merchantProfile->update([
            'status' => $request->status,
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Le statut du commerçant a été mis à jour avec succès ({$request->status}).",
        ], 200);
    }
}
