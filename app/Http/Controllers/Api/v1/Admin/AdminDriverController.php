<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CreateNotificationJob;
use App\Models\DriverProfile;
use App\Models\User;
use App\Notifications\DriverAccountStatusUpdated;
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
    public function updateStatus(Request $request, $id, Messaging $messaging)
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

        // Mise à jour du statut dans la BDD
        $driverProfile->update([
            'status'           => $request->status,
            'rejection_reason' => $request->rejection_reason
        ]);

        // Mise à jour du compte utilisateur associé
        $user = $driverProfile->user;
        if ($user && $request->status === 'approved') {
            $user->update(['is_active' => true]);
        }

        // --- DÉCLENCHEMENT DES NOTIFICATIONS (EMAIL + PUSH FCM) ---
        if ($user) {
            try {
                // 1. Envoi de l'Email
                $notification = new DriverAccountStatusUpdated($request->status, $request->rejection_reason);
                $user->notify($notification);

                // 2. Envoi de la Notification Push FCM
                $notification->sendFcmNotification($user, $messaging);

                // 3. Notification in-app (badge côté Flutter)
                CreateNotificationJob::dispatch(
                    $user->id,
                    'account_status_changed',
                    $request->status === 'approved' ? 'Compte validé' : 'Compte non validé',
                    $request->status === 'approved'
                        ? 'Votre compte chauffeur a été validé.'
                        : ('Votre dossier n\'a pas été validé. Motif : ' . ($request->rejection_reason ?? 'Non spécifié')),
                    ['new_status' => $request->status]
                );
            } catch (\Exception $e) {
                // Évite de bloquer la réponse API si l'envoi réseau échoue
                \Log::error("Erreur d'envoi de notification chauffeur : " . $e->getMessage());
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => "Le statut du chauffeur a été mis à jour avec succès ({$request->status})."
        ], 200);
    }
}