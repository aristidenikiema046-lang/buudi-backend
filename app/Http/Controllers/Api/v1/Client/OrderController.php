<?php

namespace App\Http\Controllers\Api\v1\Client;

use App\Http\Controllers\Controller;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\PaymentRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * POST /v1/client/orders — Crée une commande Supermarché.
     *
     * Le prix de chaque article n'est JAMAIS pris depuis la requête : chaque
     * product_id est rechargé depuis la table products pour recalculer le
     * sous-total à partir du prix actuel en base. Si le client envoie un prix
     * dans le payload, il est simplement ignoré (aucun champ "price" n'est
     * lu ci-dessous).
     *
     * delivery_fee reste fourni par le client, comme estimated_price pour
     * Client\RideController::store aujourd'hui — c'est un montant de
     * logistique (course de livraison), pas un prix de marchandise, le
     * même niveau de confiance que le reste de l'app pour ce type de valeur.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'merchant_profile_id' => 'required|uuid|exists:merchant_profiles,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid',
            'items.*.quantity' => 'required|integer|min:1',
            'delivery_address' => 'required|string|max:255',
            'delivery_latitude' => 'required|numeric|between:-90,90',
            'delivery_longitude' => 'required|numeric|between:-180,180',
            'delivery_fee' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $supermarket = MerchantProfile::where('id', $request->merchant_profile_id)
            ->where('is_supermarket', true)
            ->where('status', 'approved')
            ->first();

        if (!$supermarket) {
            return response()->json([
                'success' => false,
                'message' => 'Supermarché introuvable ou non disponible.',
            ], 404);
        }

        $result = DB::transaction(function () use ($request, $supermarket) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($request->items as $item) {
                // lockForUpdate : si le marchand modifie le prix d'un produit
                // pendant que cette commande se crée, on veut lire soit
                // l'ancien prix soit le nouveau proprement, jamais un état
                // intermédiaire incohérent.
                $product = Product::where('id', $item['product_id'])
                    ->where('merchant_profile_id', $supermarket->id)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    return ['error' => 404, 'message' => "Produit introuvable : {$item['product_id']}."];
                }

                if (!$product->is_available) {
                    return ['error' => 422, 'message' => "Produit indisponible : {$product->name}."];
                }

                // Prix rechargé depuis la base de données — la valeur envoyée
                // par le client, s'il y en avait une, n'est jamais lue.
                $unitPrice = (float) $product->price;
                $quantity = (int) $item['quantity'];
                $lineTotal = round($unitPrice * $quantity, 2);

                $subtotal += $lineTotal;

                $lineItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];
            }

            $subtotal = round($subtotal, 2);
            $deliveryFee = (float) $request->delivery_fee;
            $total = round($subtotal + $deliveryFee, 2);

            $order = Order::create([
                'client_id' => Auth::id(),
                'merchant_profile_id' => $supermarket->id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'delivery_address' => $request->delivery_address,
                'delivery_latitude' => $request->delivery_latitude,
                'delivery_longitude' => $request->delivery_longitude,
            ]);

            foreach ($lineItems as $lineItem) {
                $order->items()->create($lineItem);
            }

            // Paiement des marchandises (subtotal uniquement, pas la
            // livraison) — réutilise le mécanisme QR/wallet déjà construit
            // pour les commerçants plutôt que d'inventer un nouveau flux.
            $paymentRequest = PaymentRequest::create([
                'merchant_id' => $supermarket->user_id,
                'token' => Str::random(40),
                'amount' => $subtotal,
                'description' => "Commande #{$order->id} — {$supermarket->business_name}",
                'status' => 'pending',
                'expires_at' => now()->addHours(24),
            ]);

            $order->update(['payment_request_id' => $paymentRequest->id]);

            return ['order' => $order->load('items', 'paymentRequest')];
        });

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['error']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Commande créée. Réglez le paiement pour que le supermarché puisse la confirmer.',
            'data' => $result['order'],
        ], 201);
    }
}
