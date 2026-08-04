<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Contract\Database;

class MessageController extends Controller
{
    /**
     * Vérifie que l'utilisateur connecté est le passager ou le chauffeur de
     * la course — seuls les deux participants peuvent lire/écrire dans la
     * conversation. Pas de middleware role:... ici puisque client ET driver
     * doivent tous les deux passer, le contrôle se fait sur l'appartenance
     * à la course elle-même.
     */
    private function authorizeRideParticipant(string $rideId): array
    {
        $ride = Ride::find($rideId);

        if (!$ride) {
            return ['error' => response()->json([
                'success' => false,
                'message' => 'Course introuvable.',
            ], 404)];
        }

        $userId = Auth::id();
        if ($userId !== $ride->passenger_id && $userId !== $ride->driver_id) {
            return ['error' => response()->json([
                'success' => false,
                'message' => 'Accès refusé : vous ne participez pas à cette course.',
            ], 403)];
        }

        return ['ride' => $ride];
    }

    /**
     * POST /v1/rides/{ride}/messages — Envoie un message lié à la course.
     * Sauvegardé en base (source de vérité) avant toute chose, puis un
     * timestamp est écrit dans Firebase RTDB comme simple signal temps réel.
     * Cette écriture RTDB est best-effort : voir le try/catch plus bas.
     */
    public function store(Request $request, string $rideId, Database $database)
    {
        $auth = $this->authorizeRideParticipant($rideId);
        if (isset($auth['error'])) {
            return $auth['error'];
        }
        $ride = $auth['ride'];

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $message = Message::create([
            'ride_id' => $ride->id,
            'sender_id' => Auth::id(),
            'sender_role' => Auth::user()->role,
            'content' => $request->content,
        ]);

        // Signal temps réel best-effort : le message est déjà sauvegardé
        // ci-dessus, Postgres reste la source de vérité. RTDB ne sert qu'à
        // dire au destinataire "va rechercher via l'API" — une panne ici ne
        // doit jamais faire échouer l'envoi du message.
        try {
            $database->getReference("messages_meta/{$ride->id}/last_message_at")
                ->set(Database::SERVER_TIMESTAMP);
        } catch (\Throwable $e) {
            Log::warning("Échec écriture RTDB messages_meta pour la course {$ride->id} : {$e->getMessage()}");
        }

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé.',
            'data' => $message,
        ], 201);
    }

    /**
     * GET /v1/rides/{ride}/messages — Historique complet de la conversation,
     * du plus ancien au plus récent. Pas de pagination : une conversation
     * liée à une course reste courte (quelques dizaines de messages max).
     */
    public function index(Request $request, string $rideId)
    {
        $auth = $this->authorizeRideParticipant($rideId);
        if (isset($auth['error'])) {
            return $auth['error'];
        }
        $ride = $auth['ride'];

        $messages = Message::where('ride_id', $ride->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages,
        ], 200);
    }
}
