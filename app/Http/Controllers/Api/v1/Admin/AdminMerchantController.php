<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use App\Services\MerchantApprovalService;
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
    public function updateStatus(Request $request, $id, MerchantApprovalService $approvalService)
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

        $approvalService->updateStatus($merchantProfile, $request->status, $request->rejection_reason);

        return response()->json([
            'success' => true,
            'message' => "Le statut du commerçant a été mis à jour avec succès ({$request->status}).",
        ], 200);
    }

    /**
     * 3. Active/désactive le mode Supermarché d'un commerçant.
     *
     * Méthode séparée plutôt que fusionnée dans updateStatus() : is_supermarket
     * est un flag produit orthogonal au cycle d'approbation KYB (pending/
     * approved/rejected) — les mélanger forcerait à repasser "status" à
     * chaque bascule, avec le risque de l'écraser par erreur.
     *
     * Valeur explicite en body plutôt qu'un vrai flip aveugle : plus sûr si
     * deux appels partent en parallèle (pas de double-flip qui s'annule).
     */
    public function toggleSupermarket(Request $request, $id)
    {
        $request->validate([
            'is_supermarket' => 'required|boolean',
        ]);

        $merchantProfile = MerchantProfile::where('id', $id)
            ->orWhere('user_id', $id)
            ->first();

        if (!$merchantProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Dossier commerçant introuvable.',
            ], 404);
        }

        $merchantProfile->update(['is_supermarket' => $request->boolean('is_supermarket')]);

        return response()->json([
            'success' => true,
            'message' => $merchantProfile->is_supermarket
                ? 'Compte marqué comme supermarché.'
                : 'Statut supermarché retiré.',
            'data' => $merchantProfile,
        ], 200);
    }
}
