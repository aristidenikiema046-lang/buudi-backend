<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WebhookController extends Controller
{
    /**
     * POST /v1/webhooks/mobile-money/{operator} — Notification de confirmation
     * envoyée par l'opérateur (Wave, Orange, MTN, Moov) suite à un transfert
     * initié via TransferController::transfer.
     *
     * TODO: Chaque opérateur a son propre système de signature et son propre
     * format de payload. Cette implémentation part de deux hypothèses
     * génériques à ajuster dès que les vraies clés/API des opérateurs seront
     * disponibles :
     *   1. Le header "X-Signature" contient un HMAC-SHA256 du corps brut de
     *      la requête, signé avec un secret propre à chaque opérateur
     *      (config('services.mobile_money.{operator}.webhook_secret')).
     *   2. Le payload contient "reference" = l'ID (UUID) de notre Transaction,
     *      transmis à l'opérateur au moment du transfert.
     * Tant que le secret n'est pas configuré, la vérification de signature est
     * ignorée (utile en développement) mais le sera automatiquement dès qu'un
     * secret sera renseigné dans .env.
     */
    public function handle(Request $request, string $operator)
    {
        if (!in_array($operator, ['wave', 'orange', 'mtn', 'moov'], true)) {
            return response()->json(['success' => false, 'message' => 'Opérateur inconnu.'], 404);
        }

        $secret = config("services.mobile_money.$operator.webhook_secret");

        if ($secret) {
            $signature = $request->header('X-Signature');
            $expected = hash_hmac('sha256', $request->getContent(), $secret);

            if (!$signature || !hash_equals($expected, $signature)) {
                Log::warning("Webhook mobile money [$operator] : signature invalide.");
                return response()->json(['success' => false, 'message' => 'Signature invalide.'], 401);
            }
        }

        $validator = Validator::make($request->all(), [
            'reference' => 'required|string',
            'status' => 'required|in:completed,failed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = Transaction::find($request->reference);

        if (!$transaction) {
            // On répond quand même "success" pour éviter que l'opérateur ne
            // renvoie la notification en boucle : la référence est simplement
            // inconnue de notre côté.
            Log::warning("Webhook mobile money [$operator] : référence introuvable {$request->reference}");
            return response()->json(['success' => true, 'message' => 'Référence inconnue, ignorée.'], 200);
        }

        // Idempotence : si cette transaction n'est déjà plus "pending" (webhook
        // déjà traité), on ne rejoue pas la logique métier une seconde fois.
        if ($transaction->status !== 'pending') {
            return response()->json(['success' => true, 'message' => 'Déjà traité.'], 200);
        }

        DB::transaction(function () use ($transaction, $request) {
            $transaction->update(['status' => $request->status]);

            if ($request->status === 'failed') {
                // Le transfert a échoué côté opérateur : on recrédite le client.
                Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->increment('balance', $transaction->amount);
            }
        });

        return response()->json(['success' => true], 200);
    }
}
