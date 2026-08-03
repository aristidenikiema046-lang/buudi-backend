<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\PaymentWebhookLog;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\PaymentProviders\PaymentProviderFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * POST /v1/webhooks/mobile-money/{provider} — Notification de confirmation
     * envoyée par un fournisseur de paiement (opérateur direct ou agrégateur)
     * suite à un transfert initié via TransferController::transfer.
     *
     * Toute la logique propre à un fournisseur précis (nom du header de
     * signature, algorithme, noms des champs du payload) vit dans
     * PaymentProviderFactory/config('services.payment_providers') — ce
     * contrôleur ne connaît que PaymentProviderInterface, générique.
     *
     * Chaque appel est journalisé dans payment_webhook_logs, quel que soit le
     * résultat (signature invalide, référence introuvable, déjà traité...) :
     * c'est ce qui manquait pour déboguer un vrai souci de paiement en prod.
     */
    public function handle(Request $request, string $provider)
    {
        $providerService = PaymentProviderFactory::make($provider);

        if (!$providerService) {
            return response()->json(['success' => false, 'message' => 'Fournisseur de paiement inconnu.'], 404);
        }

        $log = PaymentWebhookLog::create([
            'provider' => $provider,
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
            'result' => 'received',
        ]);

        if (!$providerService->verifySignature($request)) {
            Log::warning("Webhook paiement [$provider] : signature invalide.");
            $log->update(['result' => 'invalid_signature']);

            return response()->json(['success' => false, 'message' => 'Signature invalide.'], 401);
        }

        $merchantReference = $providerService->extractMerchantReference($request);
        $externalReference = $providerService->extractExternalReference($request);
        $status = $providerService->extractStatus($request);

        if (!$merchantReference || !$status) {
            $log->update(['result' => 'invalid_payload']);

            return response()->json(['success' => false, 'message' => 'Payload invalide ou statut non reconnu.'], 422);
        }

        $transaction = Transaction::find($merchantReference);

        if (!$transaction) {
            // On répond quand même "success" pour éviter que le fournisseur ne
            // renvoie la notification en boucle : la référence est simplement
            // inconnue de notre côté.
            Log::warning("Webhook paiement [$provider] : référence introuvable $merchantReference");
            $log->update(['result' => 'reference_not_found']);

            return response()->json(['success' => true, 'message' => 'Référence inconnue, ignorée.'], 200);
        }

        $log->update(['transaction_id' => $transaction->id]);

        // Idempotence n°1 : ce même external_reference a-t-il déjà servi pour
        // UNE AUTRE transaction ? (la contrainte unique en base le bloquerait
        // de toute façon à l'écriture, mais on préfère un message clair ici
        // plutôt qu'une exception SQL.)
        if ($externalReference
            && Transaction::where('external_reference', $externalReference)
                ->where('id', '!=', $transaction->id)
                ->exists()
        ) {
            Log::warning("Webhook paiement [$provider] : external_reference déjà utilisé ailleurs $externalReference");
            $log->update(['result' => 'duplicate_external_reference']);

            return response()->json(['success' => true, 'message' => 'Référence externe déjà utilisée, ignorée.'], 200);
        }

        // Idempotence n°2 : la transaction visée est-elle déjà traitée ?
        // (vérifié une première fois ici pour sortir vite, puis revérifié
        // sous verrou juste en dessous pour fermer la fenêtre de course.)
        if ($transaction->status !== 'pending') {
            $log->update(['result' => 'already_processed']);

            return response()->json(['success' => true, 'message' => 'Déjà traité.'], 200);
        }

        DB::transaction(function () use ($transaction, $status, $externalReference, $log) {
            // lockForUpdate() : si deux webhooks identiques arrivent en même
            // temps, le deuxième attend que le premier ait fini avant de lire
            // le statut — il verra donc "completed"/"failed" et non plus
            // "pending", au lieu de foncer tête baissée sur un double crédit.
            $locked = Transaction::where('id', $transaction->id)->lockForUpdate()->first();

            if ($locked->status !== 'pending') {
                $log->update(['result' => 'already_processed']);

                return;
            }

            $locked->update([
                'status' => $status,
                'external_reference' => $externalReference,
            ]);

            if ($status === 'failed') {
                // Le transfert a échoué côté fournisseur : on recrédite le client.
                Wallet::where('id', $locked->wallet_id)->lockForUpdate()->increment('balance', $locked->amount);
            }

            $log->update(['result' => 'processed']);
        });

        return response()->json(['success' => true], 200);
    }
}
