<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    /**
     * Récupère le portefeuille du commerçant, ou le crée s'il n'existe pas
     * encore (même mécanisme paresseux que pour le client/chauffeur — aucun
     * Wallet n'est créé automatiquement à l'inscription, pour aucun rôle).
     */
    private function getOrCreateWallet(string $userId): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $userId], ['balance' => 0]);
    }

    /**
     * Bloque l'accès au wallet tant que le dossier n'est pas "approved" —
     * même règle que DriverProfileController::toggleOnlineStatus pour les
     * chauffeurs. Inutile d'avoir un wallet actif avant validation admin.
     * Retourne un message d'erreur JSON si bloqué, null si l'accès est permis.
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
     * GET /v1/merchant/wallet — Solde actuel.
     */
    public function show(Request $request)
    {
        if ($blocked = $this->requireApprovedMerchant()) {
            return $blocked;
        }

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
     * GET /v1/merchant/wallet/transactions — Historique des ventes.
     * Filtré sur category = 'sale' : c'est la catégorie que devra utiliser
     * la future fonctionnalité "Demande de paiement" en créditant ce wallet
     * (pas encore construite — voir l'audit précédent).
     */
    public function transactions(Request $request)
    {
        if ($blocked = $this->requireApprovedMerchant()) {
            return $blocked;
        }

        $wallet = $this->getOrCreateWallet(Auth::id());

        $transactions = $wallet->transactions()
            ->where('category', 'sale')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ], 200);
    }
}
