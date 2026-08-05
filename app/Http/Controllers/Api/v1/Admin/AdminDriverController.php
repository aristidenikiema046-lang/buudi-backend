<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Services\DriverApprovalService;
use Illuminate\Http\Request;
use Kreait\Firebase\Contract\Messaging;

class AdminDriverController extends Controller
{
    /**
     * 1. Lister tous les chauffeurs en attente de validation
     */
    public function getPendingDrivers()
    {
        $pendingDrivers = DriverProfile::with('user')
            ->where('status', 'pending')
            ->get();

        return response()->json([
            'success' => true,
            'drivers' => $pendingDrivers
        ], 200);
    }

    /**
     * 2. Approuver ou rejeter le profil d'un chauffeur
     */
    public function updateStatus(Request $request, $id, Messaging $messaging, DriverApprovalService $approvalService)
    {
        $request->validate([
            'status'           => 'required|in:approved,rejected',
            'rejection_reason' => 'nullable|string'
        ]);

        // Recherche par ID utilisateur (user_id) ou ID du DriverProfile
        $driverProfile = DriverProfile::where(function ($query) use ($id) {
            if (\Illuminate\Support\Str::isUuid($id)) {
                $query->where('user_id', $id);
            } else {
                $query->where('id', $id);
            }
        })->first();

        if (!$driverProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil chauffeur introuvable.'
            ], 404);
        }

        $approvalService->updateStatus($driverProfile, $request->status, $request->rejection_reason, $messaging);

        return response()->json([
            'success' => true,
            'message' => "Le statut du chauffeur a été mis à jour avec succès ({$request->status})."
        ], 200);
    }
}