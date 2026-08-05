<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class OrderRefundService
{
    /**
     * Annule une commande "pending" et rembourse le client si elle avait
     * déjà été payée. Utilisé aussi bien depuis Merchant\OrderController
     * (rupture de stock...) que Client\OrderController (le client change
     * d'avis avant confirmation) — mêmes mouvements d'argent dans les
     * deux cas, centralisés ici plutôt que dupliqués.
     */
    public function cancelAndRefund(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::where('id', $order->id)->lockForUpdate()->first();

            $paymentRequest = $locked->paymentRequest;

            if ($paymentRequest && $paymentRequest->status === 'paid') {
                $this->refund($locked, $paymentRequest);
            } elseif ($paymentRequest && $paymentRequest->status === 'pending') {
                // Neutralise la demande de paiement : sans ça, rien n'empêche
                // POST /payment-requests/{token}/pay-with-wallet de l'honorer
                // après coup pour une commande déjà annulée — cet endpoint ne
                // connaît rien de l'Order. 'expired' est déjà un statut
                // supporté et déjà rejeté proprement par payWithWallet.
                $paymentRequest->update(['status' => 'expired']);
            }

            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            return $locked;
        });
    }

    /**
     * Remboursement intégral du client (montant du payment_request), reprise
     * du montant EXACT reçu par le marchand — retrouvé via la Transaction
     * d'origine (paymentRequest->transaction_id), jamais recalculé depuis le
     * commission_rate actuel qui a pu changer depuis. Chaque mouvement est
     * tracé par une Transaction (contrairement à l'incrément brut utilisé
     * dans WebhookController pour un transfert échoué — voir décision prise
     * pour ce chantier : on ne reproduit pas cette incohérence ici).
     *
     * Le wallet marchand est autorisé à passer négatif : c'est une reprise
     * de fonds côté plateforme, pas une demande de retrait volontaire —
     * pas de vérification de solde suffisant, contrairement à withdraw().
     */
    private function refund(Order $order, $paymentRequest): void
    {
        $merchantTransaction = Transaction::find($paymentRequest->transaction_id);

        $clientWallet = Wallet::firstOrCreate(['user_id' => $order->client_id], ['balance' => 0]);
        $clientWallet = Wallet::where('id', $clientWallet->id)->lockForUpdate()->first();

        $merchantUserId = $order->merchantProfile->user_id;
        $merchantWallet = Wallet::firstOrCreate(['user_id' => $merchantUserId], ['balance' => 0]);
        $merchantWallet = Wallet::where('id', $merchantWallet->id)->lockForUpdate()->first();

        $refundAmount = (float) $paymentRequest->amount;
        $merchantClawback = $merchantTransaction ? (float) $merchantTransaction->amount : $refundAmount;

        $clientWallet->increment('balance', $refundAmount);
        Transaction::create([
            'wallet_id' => $clientWallet->id,
            'amount' => $refundAmount,
            'type' => 'credit',
            'category' => 'order_refund',
            'description' => "Remboursement commande annulée #{$order->id}",
            'reference_id' => $order->id,
            'status' => 'completed',
        ]);

        $merchantWallet->decrement('balance', $merchantClawback);
        Transaction::create([
            'wallet_id' => $merchantWallet->id,
            'amount' => $merchantClawback,
            'type' => 'debit',
            'category' => 'order_refund',
            'description' => "Reprise suite à l'annulation de la commande #{$order->id}",
            'reference_id' => $order->id,
            'status' => 'completed',
        ]);
    }
}
