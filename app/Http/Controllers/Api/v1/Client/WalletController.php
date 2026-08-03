<?php

namespace App\Http\Controllers\Api\v1\Client;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    /**
     * Récupère le portefeuille de l'utilisateur, ou le crée s'il n'existe pas encore.
     */
    private function getOrCreateWallet(string $userId): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $userId], ['balance' => 0]);
    }

    /**
     * GET /v1/client/wallet — Solde actuel du portefeuille.
     */
    public function show(Request $request)
    {
        $wallet = $this->getOrCreateWallet(Auth::id());

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => (float) $wallet->balance,
                'currency' => 'XOF',
            ],
        ], 200);
    }

    /**
     * POST /v1/client/wallet/deposit — Crédite le portefeuille.
     */
    public function deposit(Request $request)
    {
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

        $userId = Auth::id();

        $transaction = DB::transaction(function () use ($userId, $request) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first()
                ?? Wallet::create(['user_id' => $userId, 'balance' => 0]);

            $wallet->increment('balance', $request->amount);

            return Transaction::create([
                'wallet_id' => $wallet->id,
                'amount' => $request->amount,
                'type' => 'credit',
                'category' => 'deposit',
                'description' => $request->description ?? 'Dépôt sur le portefeuille',
                'status' => 'completed',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Dépôt effectué avec succès.',
            'data' => $transaction,
        ], 201);
    }

    /**
     * POST /v1/client/wallet/withdraw — Débite le portefeuille (si solde suffisant).
     */
    public function withdraw(Request $request)
    {
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
                    'category' => 'withdrawal',
                    'description' => $request->description ?? 'Retrait du portefeuille',
                    'status' => 'completed',
                ]);
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_FUNDS') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solde insuffisant pour effectuer ce retrait.',
                ], 400);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Retrait effectué avec succès.',
            'data' => $transaction,
        ], 201);
    }

    /**
     * GET /v1/client/wallet/transactions — Historique paginé.
     */
    public function transactions(Request $request)
    {
        $wallet = $this->getOrCreateWallet(Auth::id());

        $transactions = $wallet->transactions()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ], 200);
    }
}
