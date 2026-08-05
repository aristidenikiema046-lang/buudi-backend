<?php

namespace App\Console\Commands;

use App\Jobs\CreateNotificationJob;
use App\Models\DriverProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckDriverDocumentExpiry extends Command
{
    protected $signature = 'drivers:check-document-expiry';

    protected $description = "Alerte les chauffeurs/livreurs dont le permis ou l'assurance arrive à expiration (J-7) ou vient d'expirer (jour J).";

    /**
     * Documents suivis : colonne date => [libellé, pronom] pour accorder
     * correctement "le/la renouveler" ("permis" masculin, "assurance"
     * féminin). La carte grise n'expire pas dans la vraie vie, elle est
     * volontairement absente d'ici (voir migration 2026_08_05_090000).
     */
    private array $documents = [
        'license_expires_at' => ['label' => 'permis de conduire', 'pronoun' => 'le', 'subject' => "qu'il", 'participle' => 'renouvelé'],
        'insurance_expires_at' => ['label' => 'assurance véhicule', 'pronoun' => 'la', 'subject' => "qu'elle", 'participle' => 'renouvelée'],
    ];

    /**
     * Égalité de date stricte (pas de "<=") pour chaque passe : chaque
     * document ne peut matcher qu'un seul jour, ce qui rend la commande
     * idempotente sans colonne "déjà notifié" à maintenir. Contrepartie
     * assumée pour ce MVP : si le scheduler est indisponible pile le jour
     * concerné, la notification correspondante est ratée sans rattrapage —
     * ça n'affecte que l'alerte, jamais le blocage réel (voir
     * DriverProfileController::toggleOnlineStatus, qui relit ces mêmes
     * colonnes en direct).
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $warningDate = $today->copy()->addDays(7);

        foreach ($this->documents as $column => $doc) {
            $this->notify($column, $warningDate, 'document_expiring', 'Document bientôt expiré',
                "Votre {$doc['label']} expire dans 7 jours. Pensez à {$doc['pronoun']} renouveler pour continuer à recevoir des courses.");

            $this->notify($column, $today, 'document_expired', 'Document expiré',
                "Votre {$doc['label']} a expiré. Vous ne pourrez plus vous mettre en ligne tant {$doc['subject']} n'est pas {$doc['participle']}.");
        }

        return self::SUCCESS;
    }

    private function notify(string $column, Carbon $date, string $type, string $title, string $body): void
    {
        $profiles = DriverProfile::where('status', 'approved')
            ->whereDate($column, $date)
            ->get(['user_id']);

        foreach ($profiles as $profile) {
            CreateNotificationJob::dispatch($profile->user_id, $type, $title, $body, ['document_column' => $column]);
        }
    }
}
