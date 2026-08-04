<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $userId,
        public string $type,
        public string $title,
        public string $body,
        public ?array $data = null,
    ) {}

    /**
     * Contrairement à WriteMessageRtdbSignal (best-effort silencieux, appel
     * réseau externe), cette écriture cible notre propre base Postgres :
     * un échec répété signalerait un vrai bug plutôt qu'un aléa réseau
     * normal. On laisse donc le mécanisme de retry natif de la queue
     * (--tries=3 du worker) faire son travail, et un échec définitif finit
     * volontairement dans failed_jobs plutôt que d'être avalé.
     */
    public function handle(): void
    {
        Notification::create([
            'user_id' => $this->userId,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
        ]);
    }
}
