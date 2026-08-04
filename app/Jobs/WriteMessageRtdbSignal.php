<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Database;

class WriteMessageRtdbSignal implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $rideId,
    ) {}

    /**
     * Écrit le signal temps réel RTDB pour ce ride (messages_meta/{ride_id}/
     * last_message_at). Reprend exactement ce que faisait MessageController::
     * store() en synchrone, déplacé ici pour sortir cet appel réseau du
     * chemin critique de la requête HTTP.
     *
     * Reste best-effort comme avant : jusqu'à 3 tentatives internes (même
     * nombre que --tries=3 configuré sur le worker), mais handle() ne
     * relance jamais d'exception. Si on laissait le mécanisme de retry
     * standard de la queue gérer ça, un signal RTDB qui échoue 3 fois
     * finirait dans failed_jobs comme n'importe quel job critique en échec
     * — bruyant et trompeur pour un simple signal non-critique dont
     * l'absence n'a aucune conséquence (Postgres reste la source de
     * vérité, voir MessageController::store()).
     */
    public function handle(Database $database): void
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $database->getReference("messages_meta/{$this->rideId}/last_message_at")
                    ->set(Database::SERVER_TIMESTAMP);

                return;
            } catch (\Throwable $e) {
                if ($attempt === $maxAttempts) {
                    Log::warning("Échec écriture RTDB messages_meta pour la course {$this->rideId} après {$maxAttempts} tentatives : {$e->getMessage()}");
                    return;
                }

                usleep(200_000 * $attempt);
            }
        }
    }
}
