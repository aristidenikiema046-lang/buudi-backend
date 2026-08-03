<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class PaymentRequestController extends Controller
{
    /**
     * GET /v1/payment-requests/{token} — Consultation publique, sans
     * authentification obligatoire (le lien est partagé au payeur final, qui
     * n'a pas forcément de compte Buudi). On ne renvoie donc que le strict
     * nécessaire côté marchand — jamais ses infos de contact.
     *
     * Si un jeton JWT valide est quand même fourni (le payeur est connecté à
     * la Super App), on ajoute une indication "payer" pour que Flutter sache
     * s'il faut proposer "Payer avec mon compte Buudi" en plus du mobile
     * money externe (pas encore actif).
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

        // Authentification optionnelle : cette route reste publique (pas de
        // middleware auth:api), donc pas de 401 si le token est absent ou
        // invalide — on tente juste de savoir qui demande, sans l'exiger.
        $payer = null;
        try {
            $payer = JWTAuth::parseToken()->authenticate();
        } catch (\Throwable $e) {
            $payer = null;
        }

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
            'payer' => [
                'authenticated' => (bool) $payer,
                'can_pay_with_wallet' => $payer
                    && $payer->role === 'client'
                    && $paymentRequest->status === 'pending',
            ],
        ], 200);
    }

    /**
     * POST /v1/payment-requests/{token}/pay-with-wallet — Paie une demande
     * depuis le wallet Buudi du client connecté. Entièrement synchrone : pas
     * de statut "pending" ni de webhook, débit + crédit + statut "paid" se
     * font dans une seule transaction DB atomique.
     */
    public function payWithWallet(Request $request, string $token)
    {
        $user = Auth::user();

        $result = DB::transaction(function () use ($token, $user) {
            // Verrou sur la demande elle-même : si le client double-clique,
            // ou si elle expire pile au moment du paiement, le deuxième appel
            // attend le premier puis relit un statut déjà "paid"/"expired".
            $paymentRequest = PaymentRequest::where('token', $token)->lockForUpdate()->first();

            if (!$paymentRequest) {
                return ['error' => 404, 'message' => 'Demande de paiement introuvable.'];
            }

            if ($paymentRequest->status === 'pending' && now()->greaterThan($paymentRequest->expires_at)) {
                $paymentRequest->update(['status' => 'expired']);

                return ['error' => 400, 'message' => 'Cette demande de paiement a expiré.'];
            }

            if ($paymentRequest->status !== 'pending') {
                return ['error' => 400, 'message' => 'Cette demande de paiement a déjà été traitée.'];
            }

            $clientWallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$clientWallet || $clientWallet->balance < $paymentRequest->amount) {
                return ['error' => 422, 'message' => 'Solde insuffisant pour effectuer ce paiement.'];
            }

            $merchant = $paymentRequest->merchant()->with('merchantProfile')->first();
            $merchantWallet = Wallet::firstOrCreate(['user_id' => $merchant->id], ['balance' => 0]);
            $merchantWallet = Wallet::where('id', $merchantWallet->id)->lockForUpdate()->first();

            $commissionRate = (float) ($merchant->merchantProfile->commission_rate ?? 0);
            $commissionAmount = round($paymentRequest->amount * ($commissionRate / 100), 2);
            $netAmount = $paymentRequest->amount - $commissionAmount;

            $clientWallet->decrement('balance', $paymentRequest->amount);
            $merchantWallet->increment('balance', $netAmount);

            $businessName = $merchant->merchantProfile->business_name ?? $merchant->name;

            Transaction::create([
                'wallet_id' => $clientWallet->id,
                'amount' => $paymentRequest->amount,
                'type' => 'debit',
                'category' => 'payment_request',
                'description' => "Paiement à {$businessName}" . ($paymentRequest->description ? " — {$paymentRequest->description}" : ''),
                'status' => 'completed',
            ]);

            $merchantDescription = $commissionAmount > 0
                ? "Vente" . ($paymentRequest->description ? " ({$paymentRequest->description})" : '')
                    . " — {$paymentRequest->amount} XOF, commission {$commissionRate}% ({$commissionAmount} XOF) déduite"
                : "Vente" . ($paymentRequest->description ? " — {$paymentRequest->description}" : '');

            $merchantTransaction = Transaction::create([
                'wallet_id' => $merchantWallet->id,
                'amount' => $netAmount,
                'type' => 'credit',
                'category' => 'sale',
                'description' => $merchantDescription,
                'status' => 'completed',
            ]);

            $paymentRequest->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payer_id' => $user->id,
                'transaction_id' => $merchantTransaction->id,
            ]);

            return ['payment_request' => $paymentRequest];
        });

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['error']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Paiement effectué avec succès.',
            'data' => $result['payment_request'],
        ], 200);
    }
}
