<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentRequestController extends Controller
{
    /**
     * POST /v1/merchant/payment-requests — Crée une demande de paiement
     * partageable. Expire 24h après sa création. Le vrai paiement (webhook
     * mobile money réel ou transfert wallet-à-wallet) n'est pas encore
     * branché — voir GET /v1/payment-requests/{token} pour la suite prévue.
     */
    public function store(Request $request)
    {
        // Même garde-fou que WalletController : inutile de générer des
        // demandes de paiement pour un dossier pas encore validé.
        $merchantProfile = Auth::user()->merchantProfile;
        if (!$merchantProfile || $merchantProfile->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est en attente d\'approbation par l\'administration.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $paymentRequest = PaymentRequest::create([
            'merchant_id' => Auth::id(),
            'token' => Str::random(40),
            'amount' => $request->amount,
            'description' => $request->description,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande de paiement créée.',
            'data' => $paymentRequest,
        ], 201);
    }
}
