<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Database;

class RidePendingSignal implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $serviceType,
    ) {}

    /**
     * Types de véhicule compatibles avec ce service_type — l'inverse de
     * DriverRideController::allowedServiceTypesFor() (vehicle_type ->
     * service_types). Les deux mappings doivent rester synchronisés si l'un
     * évolue (nouveau service_type, nouveau vehicle_type, etc).
     */
    private function vehicleTypesFor(string $serviceType): array
    {
        return match ($serviceType) {
            'OK Taxi', 'OK Confort', 'OK Van' => ['Voiture'],
            'Livraison' => ['Moto', 'Vélo'],
            'Supermarché' => ['Voiture', 'Moto', 'Vélo'],
            default => [],
        };
    }

    /**
     * Bump rides_pending_signal/{vehicle_type} pour chaque type de véhicule
     * compatible avec cette course : dit "il y a du nouveau, va refetch
     * GET /driver/rides/pending" aux chauffeurs qui écoutent ce chemin.
     * Neutre côté éligibilité/rayon — ce filtrage reste entièrement dans
     * getPendingRides(), exactement comme avant ce signal.
     *
     * Best-effort comme WriteMessageRtdbSignal : jusqu'à 3 tentatives par
     * type de véhicule, mais handle() ne relance jamais d'exception (un
     * signal manqué n'a aucune conséquence, le chauffeur retrouve la course
     * au prochain refetch normal).
     */
    public function handle(Database $database): void
    {
        foreach ($this->vehicleTypesFor($this->serviceType) as $vehicleType) {
            $this->bump($database, $vehicleType);
        }
    }

    private function bump(Database $database, string $vehicleType): void
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $database->getReference("rides_pending_signal/{$vehicleType}")
                    ->set(Database::SERVER_TIMESTAMP);

                return;
            } catch (\Throwable $e) {
                if ($attempt === $maxAttempts) {
                    Log::warning("Échec écriture RTDB rides_pending_signal/{$vehicleType} après {$maxAttempts} tentatives : {$e->getMessage()}");
                    return;
                }

                usleep(200_000 * $attempt);
            }
        }
    }
}
