<?php

namespace App\Http\Controllers\Api\v1\Client;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TransferController extends Controller
{
    /**
     * POST /v1/client/transfer — Transfert du portefeuille Buudi vers un opérateur
     * mobile money (Wave, Orange, MTN, Moov).
     *
     * Le montant est débité immédiatement (status "pending"). La confirmation
     * définitive (succès ou échec) arrive plus tard via le webhook de l'opérateur
     * (voir WebhookController), qui met à jour le statut — et recrédite le
     * portefeuille automatiquement si l'opérateur signale un échec.
     */
    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Identifiants alignés sur config('services.payment_providers') —
            // wave/orange_money/mtn_momo/moov_money, ou aggregator_dexchange
            // si un agrégateur est choisi à la place des opérateurs directs.
            'operator' => 'required|string|in:wave,orange_money,mtn_momo,moov_money,aggregator_dexchange',
            'phone_number' => 'required|string|max:20',
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userId = Auth::id();

        try {
            $transaction = DB::transaction(function () use ($userId, $request) {
                $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

                if (!$wallet || $wallet->balance < $request->amount) {
                    throw new \RuntimeException('INSUFFICIENT_FUNDS');
                }

                $wallet->decrement('balance', $request->amount);

                return Transaction::create([
                    'wallet_id' => $wallet->id,
                    'amount' => $request->amount,
                    'type' => 'debit',
                    'category' => 'transfer_' . $request->operator,
                    'description' => "Transfert vers {$request->phone_number} via " . strtoupper($request->operator),
                    'status' => 'pending',
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_FUNDS') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solde insuffisant pour effectuer ce transfert.',
                ], 400);
            }
            throw $e;
        }

        // TODO: Une fois les contrats signés avec les opérateurs, appeler ici la
        // vraie API de paiement (Wave/Orange/MTN/Moov) en lui transmettant
        // $transaction->id comme référence marchande. C'est cette même référence
        // que l'opérateur doit renvoyer dans son webhook pour qu'on retrouve la
        // transaction (voir WebhookController::handle).

        return response()->json([
            'success' => true,
            'message' => 'Transfert initié, en attente de confirmation de l\'opérateur.',
            'data' => $transaction,
        ], 202);
    }
}
