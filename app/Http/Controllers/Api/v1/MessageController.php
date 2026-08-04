<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Jobs\CreateNotificationJob;
use App\Jobs\WriteMessageRtdbSignal;
use App\Models\Message;
use App\Models\Ride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
     * Sauvegardé en base (source de vérité) avant toute chose ; le signal
     * temps réel RTDB est ensuite dispatché en queue (WriteMessageRtdbSignal)
     * plutôt qu'écrit en synchrone ici, pour ne jamais faire attendre la
     * réponse HTTP sur cet appel réseau externe (voir ce Job pour le detail
     * du comportement best-effort).
     */
    public function store(Request $request, string $rideId)
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

        WriteMessageRtdbSignal::dispatch($ride->id);

        // Destinataire = l'autre participant de la course. Si le client
        // écrit avant qu'un chauffeur ait accepté (driver_id encore null,
        // autorisé par authorizeRideParticipant), il n'y a personne à
        // notifier — on saute simplement.
        $recipientId = $message->sender_role === 'client' ? $ride->driver_id : $ride->passenger_id;
        if ($recipientId) {
            CreateNotificationJob::dispatch(
                $recipientId,
                'new_message',
                'Nouveau message',
                Str::limit($message->content, 100),
                ['ride_id' => $ride->id, 'message_id' => $message->id]
            );
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
