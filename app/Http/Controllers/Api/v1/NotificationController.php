<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * GET /v1/notifications — Toutes les notifications de l'utilisateur
     * connecté, les plus récentes en premier. Pas de pagination pour le MVP.
     */
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ], 200);
    }

    /**
     * GET /v1/notifications/unread-count — Endpoint léger dédié au badge,
     * ne renvoie qu'un compteur plutôt que la liste complète.
     */
    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count,
        ], 200);
    }

    /**
     * POST /v1/notifications/{id}/read — Marque une notification comme lue.
     * Recherche sans filtrer par user_id pour distinguer "introuvable" (404)
     * de "appartient à quelqu'un d'autre" (403), comme MessageController.
     */
    public function markAsRead(Request $request, string $id)
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification introuvable.',
            ], 404);
        }

        if ($notification->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé : cette notification ne vous appartient pas.',
            ], 403);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue.',
            'data' => $notification,
        ], 200);
    }

    /**
     * POST /v1/notifications/read-all — Marque toutes les notifications non
     * lues de l'utilisateur connecté comme lues, en une seule requête.
     */
    public function markAllAsRead(Request $request)
    {
        Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues.',
        ], 200);
    }
}
