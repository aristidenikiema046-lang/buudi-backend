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
     * Bloque tant que le dossier n'est pas "approved" — même garde-fou que
     * MerchantWalletController, dupliqué ici plutôt que factorisé (méthode
     * privée de 5 lignes, cohérent avec le reste du code qui préfère la
     * petite duplication à une abstraction partagée pour si peu).
     */
    private function requireApprovedMerchant(): ?\Illuminate\Http\JsonResponse
    {
        $merchantProfile = Auth::user()->merchantProfile;

        if (!$merchantProfile || $merchantProfile->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est en attente d\'approbation par l\'administration.',
            ], 403);
        }

        return null;
    }

    /**
     * GET /v1/merchant/payment-requests — Liste paginée des demandes créées
     * par le marchand connecté, les plus récentes en premier. Les 3 statuts
     * (pending/paid/expired) sont mélangés, pas de filtre par défaut.
     */
    public function index(Request $request)
    {
        if ($blocked = $this->requireApprovedMerchant()) {
            return $blocked;
        }

        $paymentRequests = PaymentRequest::where('merchant_id', Auth::id())
            // "id" en critère secondaire : deux demandes créées dans la même
            // seconde ont un created_at identique (pas de microsecondes en
            // base), sinon leur ordre entre elles serait indéterminé. Les
            // UUID générés par HasUuids sont ordonnés (ordered UUID), donc
            // trier par id départage dans le vrai ordre de création.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (PaymentRequest $pr) => [
                'token' => $pr->token,
                'amount' => (float) $pr->amount,
                'description' => $pr->description,
                'status' => $pr->status,
                'expires_at' => $pr->expires_at,
                'created_at' => $pr->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => $paymentRequests,
        ], 200);
    }

    /**
     * POST /v1/merchant/payment-requests — Crée une demande de paiement
     * partageable. Expire 24h après sa création. Le payeur peut régler soit
     * par mobile money externe (pas encore branché), soit avec son wallet
     * Buudi (voir PaymentRequestController::payWithWallet, déjà en place).
     */
    public function store(Request $request)
    {
        if ($blocked = $this->requireApprovedMerchant()) {
            return $blocked;
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
