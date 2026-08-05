<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Jobs\CreateNotificationJob;
use App\Jobs\WriteMessageRtdbSignal;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Ride;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    /**
     * GET /v1/conversations — Une ligne par course où l'utilisateur connecté
     * a échangé au moins un message (passager ou chauffeur), avec le dernier
     * message et le compteur de notifications 'new_message' non lues pour
     * cette course. 3 requêtes au total, indépendamment du nombre de
     * conversations : les rides, le dernier message par ride (DISTINCT ON,
     * spécifique Postgres) et les compteurs non lus groupés par ride.
     */
    public function conversations(Request $request)
    {
        $userId = Auth::id();

        $rides = Ride::where(function ($q) use ($userId) {
                $q->where('passenger_id', $userId)->orWhere('driver_id', $userId);
            })
            ->whereHas('messages')
            ->with(['passenger', 'driver'])
            ->get();

        if ($rides->isEmpty()) {
            return response()->json(['success' => true, 'data' => []], 200);
        }

        $rideIds = $rides->pluck('id')->all();

        $lastMessages = DB::table('messages')
            ->select(DB::raw('DISTINCT ON (ride_id) ride_id, content, sender_role, created_at'))
            ->whereIn('ride_id', $rideIds)
            ->orderBy('ride_id')
            ->orderByDesc('created_at')
            ->get()
            ->keyBy('ride_id');

        // Le destinataire d'une notification 'new_message' est toujours un
        // participant de la course concernée (voir store() ci-dessus) : pas
        // besoin de refiltrer par $rideIds, le where user_id+type+read_at
        // suffit à ne balayer qu'un petit ensemble de lignes par utilisateur.
        $unreadCounts = DB::table('notifications')
            ->select(DB::raw("data->>'ride_id' as ride_id"), DB::raw('count(*) as cnt'))
            ->where('user_id', $userId)
            ->where('type', 'new_message')
            ->whereNull('read_at')
            ->groupBy(DB::raw("data->>'ride_id'"))
            ->pluck('cnt', 'ride_id');

        $conversations = $rides->map(function (Ride $ride) use ($userId, $lastMessages, $unreadCounts) {
            $last = $lastMessages->get($ride->id);
            $otherUser = $userId === $ride->passenger_id ? $ride->driver : $ride->passenger;

            return [
                'ride_id' => $ride->id,
                'service_type' => $ride->service_type,
                'ride_status' => $ride->status,
                'other_participant' => $otherUser ? [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'role' => $otherUser->role,
                ] : null,
                'last_message' => [
                    'content' => Str::limit($last->content, 100),
                    'sender_role' => $last->sender_role,
                    'created_at' => Carbon::parse($last->created_at)->toJSON(),
                ],
                'unread_count' => (int) ($unreadCounts[$ride->id] ?? 0),
                'sort_key' => $last->created_at,
            ];
        })
            ->sortByDesc('sort_key')
            ->values()
            ->map(function ($conversation) {
                unset($conversation['sort_key']);
                return $conversation;
            });

        return response()->json([
            'success' => true,
            'data' => $conversations,
        ], 200);
    }

    /**
     * POST /v1/rides/{ride}/messages/mark-read — Marque toutes les
     * notifications 'new_message' non lues de cette course comme lues pour
     * l'utilisateur connecté. Appelé côté app à l'ouverture du ChatScreen.
     */
    public function markRead(Request $request, string $rideId)
    {
        $auth = $this->authorizeRideParticipant($rideId);
        if (isset($auth['error'])) {
            return $auth['error'];
        }
        $ride = $auth['ride'];

        Notification::where('user_id', Auth::id())
            ->where('type', 'new_message')
            ->where('data->ride_id', $ride->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Conversation marquée comme lue.',
        ], 200);
    }
}
