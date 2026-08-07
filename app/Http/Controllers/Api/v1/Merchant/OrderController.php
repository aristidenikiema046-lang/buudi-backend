<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Jobs\CreateNotificationJob;
use App\Jobs\RidePendingSignal;
use App\Models\Order;
use App\Models\Ride;
use App\Services\OrderRefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Même garde que Merchant\ProductController::requireApprovedSupermarket
     * — dupliqué à dessein, cohérent avec le reste du code (voir commentaire
     * équivalent dans ProductController).
     */
    private function requireApprovedSupermarket(): ?\Illuminate\Http\JsonResponse
    {
        $merchantProfile = Auth::user()->merchantProfile;

        if (!$merchantProfile || $merchantProfile->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est en attente d\'approbation par l\'administration.',
            ], 403);
        }

        if (!$merchantProfile->is_supermarket) {
            return response()->json([
                'success' => false,
                'message' => 'Cette fonctionnalité est réservée aux comptes supermarché.',
            ], 403);
        }

        return null;
    }

    /**
     * GET /v1/merchant/orders — Commandes reçues par le supermarché connecté,
     * les plus récentes en premier.
     */
    public function index(Request $request)
    {
        if ($blocked = $this->requireApprovedSupermarket()) {
            return $blocked;
        }

        $orders = Order::where('merchant_profile_id', Auth::user()->merchantProfile->id)
            ->with(['items', 'paymentRequest', 'ride'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ], 200);
    }

    /**
     * POST /v1/merchant/orders/{id}/confirm — Le supermarché confirme la
     * commande une fois le paiement reçu : crée automatiquement la course de
     * livraison (service_type = "Supermarché"). Chemin dédié, ne réutilise
     * pas Client\RideController::store : il n'y a pas de client à l'origine
     * de cette course, c'est un événement système déclenché par le cycle de
     * vie de la commande, et le prix (delivery_fee) vient de l'Order, jamais
     * d'une requête entrante.
     */
    public function confirm(Request $request, string $id)
    {
        if ($blocked = $this->requireApprovedSupermarket()) {
            return $blocked;
        }

        $merchantProfile = Auth::user()->merchantProfile;

        $result = DB::transaction(function () use ($id, $merchantProfile) {
            $order = Order::where('id', $id)
                ->where('merchant_profile_id', $merchantProfile->id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                return ['error' => 404, 'message' => 'Commande introuvable.'];
            }

            if ($order->status !== 'pending') {
                return ['error' => 400, 'message' => 'Cette commande a déjà été traitée.'];
            }

            // Garde-fou métier : on ne dispatche pas de livraison pour une
            // commande dont les marchandises ne sont pas encore payées.
            $paymentRequest = $order->paymentRequest;
            if (!$paymentRequest || $paymentRequest->status !== 'paid') {
                return ['error' => 400, 'message' => 'Cette commande ne peut pas être confirmée : le paiement n\'a pas encore été reçu.'];
            }

            if ($merchantProfile->business_latitude === null || $merchantProfile->business_longitude === null) {
                return ['error' => 422, 'message' => 'Coordonnées GPS du supermarché manquantes : impossible de créer la course de livraison. Renseignez-les via PUT /v1/merchant/profile.'];
            }

            $ride = Ride::create([
                'passenger_id' => $order->client_id,
                'driver_id' => null,
                'pickup_address' => $merchantProfile->business_address ?? $merchantProfile->business_name,
                'pickup_latitude' => $merchantProfile->business_latitude,
                'pickup_longitude' => $merchantProfile->business_longitude,
                'destination_address' => $order->delivery_address,
                'destination_latitude' => $order->delivery_latitude,
                'destination_longitude' => $order->delivery_longitude,
                'service_type' => 'Supermarché',
                'price' => $order->delivery_fee,
                'payment_method' => 'wallet',
                'status' => 'pending',
            ]);

            $order->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'ride_id' => $ride->id,
            ]);

            CreateNotificationJob::dispatch(
                $order->client_id,
                'order_status_changed',
                'Commande confirmée',
                'Votre commande a été confirmée, recherche d\'un livreur en cours.',
                ['order_id' => $order->id, 'ride_id' => $ride->id, 'new_status' => 'confirmed']
            );

            RidePendingSignal::dispatch($ride->service_type);

            return ['order' => $order->load('ride')];
        });

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['error']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Commande confirmée. Course de livraison créée, en attente d\'un chauffeur.',
            'data' => $result['order'],
        ], 200);
    }

    /**
     * POST /v1/merchant/orders/{id}/cancel — Le supermarché annule une
     * commande pas encore confirmée (ex: rupture de stock découverte après
     * paiement). Remboursé si déjà payé (OrderRefundService), notifie
     * uniquement le client (contrairement à l'annulation côté client qui
     * notifie les deux — ici c'est le marchand qui agit, pas la peine de
     * se notifier lui-même).
     */
    public function cancel(Request $request, string $id)
    {
        if ($blocked = $this->requireApprovedSupermarket()) {
            return $blocked;
        }

        $merchantProfile = Auth::user()->merchantProfile;

        $order = Order::where('id', $id)
            ->where('merchant_profile_id', $merchantProfile->id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande introuvable.',
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne peut plus être annulée.',
            ], 400);
        }

        $wasPaid = $order->paymentRequest && $order->paymentRequest->status === 'paid';

        $order = app(OrderRefundService::class)->cancelAndRefund($order);

        CreateNotificationJob::dispatch(
            $order->client_id,
            'order_status_changed',
            'Commande annulée',
            $wasPaid
                ? 'Le supermarché a annulé votre commande. Vous avez été remboursé.'
                : 'Le supermarché a annulé votre commande.',
            ['order_id' => $order->id, 'new_status' => 'cancelled']
        );

        return response()->json([
            'success' => true,
            'message' => 'Commande annulée.' . ($wasPaid ? ' Le client a été remboursé.' : ''),
            'data' => $order,
        ], 200);
    }
}
