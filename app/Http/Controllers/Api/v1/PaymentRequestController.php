<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use Illuminate\Http\Request;

class PaymentRequestController extends Controller
{
    /**
     * GET /v1/payment-requests/{token} — Consultation publique, sans
     * authentification (le lien est partagé au payeur final, qui n'a pas
     * forcément de compte Buudi). On ne renvoie donc que le strict
     * nécessaire — jamais les infos de contact du marchand.
     *
     * Le paiement lui-même n'est pas encore branché : pour l'instant cet
     * endpoint sert uniquement à afficher le montant/statut. Les options
     * "payer par mobile money" et "payer avec mon compte Buudi" viendront
     * une fois les clés API disponibles (voir WebhookController) et le
     * transfert wallet-à-wallet conçu.
     */
    public function show(Request $request, string $token)
    {
        $paymentRequest = PaymentRequest::where('token', $token)
            ->with('merchant.merchantProfile')
            ->first();

        if (!$paymentRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Demande de paiement introuvable.',
            ], 404);
        }

        $paymentRequest->refreshExpiryStatus();

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $paymentRequest->token,
                'amount' => (float) $paymentRequest->amount,
                'description' => $paymentRequest->description,
                'status' => $paymentRequest->status,
                'expires_at' => $paymentRequest->expires_at,
                'paid_at' => $paymentRequest->paid_at,
                'merchant_name' => $paymentRequest->merchant->merchantProfile->business_name
                    ?? $paymentRequest->merchant->name,
            ],
        ], 200);
    }
}
