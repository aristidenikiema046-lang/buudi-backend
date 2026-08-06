<?php

namespace App\Services;

use App\Support\GeoDistance;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RidePricingService
{
    /**
     * Tarifs VTC en FCFA/km. La Livraison n'a pas de calcul serveur (voir
     * RideController::store) et n'a donc pas d'entrée ici.
     */
    private const RATES_PER_KM = [
        'OK Taxi' => 160,
        'OK Confort' => 280,
        'OK Van' => 400,
    ];

    /**
     * Majoration appliquée à la distance à vol d'oiseau (Haversine) quand
     * Google Distance Matrix est indisponible, pour approcher une distance
     * routière réelle.
     */
    private const HAVERSINE_MARKUP = 1.2;

    /**
     * Vitesse moyenne supposée (km/h) pour estimer la durée en mode repli,
     * faute de données de trafic. Hypothèse prudente pour trafic urbain dense.
     */
    private const FALLBACK_SPEED_KMH = 25;

    /**
     * Écart relatif entre estimated_price (client) et le prix serveur
     * au-delà duquel on journalise un avertissement.
     */
    private const PRICE_MISMATCH_THRESHOLD = 0.20;

    /**
     * Calcule distance, durée et prix côté serveur pour une course VTC.
     *
     * @return array{distance_km: float, duration_min: int, price: float, source: string}
     */
    public function calculate(
        string $serviceType,
        float $pickupLat,
        float $pickupLng,
        float $destinationLat,
        float $destinationLng,
        ?float $clientEstimatedPrice = null
    ): array {
        $rate = self::RATES_PER_KM[$serviceType] ?? null;

        if ($rate === null) {
            throw new \InvalidArgumentException("Type de course non tarifé côté serveur : {$serviceType}");
        }

        [$distanceKm, $durationMin, $source] = $this->resolveDistanceAndDuration(
            $pickupLat,
            $pickupLng,
            $destinationLat,
            $destinationLng
        );

        $price = round($distanceKm * $rate);

        if ($clientEstimatedPrice !== null) {
            $this->logIfMismatch($serviceType, $clientEstimatedPrice, $price);
        }

        return [
            'distance_km' => $distanceKm,
            'duration_min' => $durationMin,
            'price' => $price,
            'source' => $source,
        ];
    }

    /**
     * @return array{0: float, 1: int, 2: string} [distanceKm, durationMin, source]
     */
    private function resolveDistanceAndDuration(
        float $pickupLat,
        float $pickupLng,
        float $destinationLat,
        float $destinationLng
    ): array {
        $viaGoogle = $this->fetchFromGoogleDistanceMatrix($pickupLat, $pickupLng, $destinationLat, $destinationLng);

        if ($viaGoogle !== null) {
            return [$viaGoogle['distance_km'], $viaGoogle['duration_min'], 'google_distance_matrix'];
        }

        $distanceKm = GeoDistance::haversineKm($pickupLat, $pickupLng, $destinationLat, $destinationLng) * self::HAVERSINE_MARKUP;
        $durationMin = (int) round(($distanceKm / self::FALLBACK_SPEED_KMH) * 60);

        return [$distanceKm, $durationMin, 'haversine_fallback'];
    }

    /**
     * @return array{distance_km: float, duration_min: int}|null null si l'appel échoue ou renvoie un statut non exploitable.
     */
    private function fetchFromGoogleDistanceMatrix(
        float $pickupLat,
        float $pickupLng,
        float $destinationLat,
        float $destinationLng
    ): ?array {
        $apiKey = config('services.google_maps.api_key');

        if (empty($apiKey)) {
            Log::warning('RidePricingService: GOOGLE_MAPS_API_KEY absente, repli Haversine utilisé.');
            return null;
        }

        try {
            $response = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'origins' => "{$pickupLat},{$pickupLng}",
                'destinations' => "{$destinationLat},{$destinationLng}",
                'mode' => 'driving',
                'key' => $apiKey,
            ]);

            if (!$response->successful()) {
                Log::warning('RidePricingService: appel Google Distance Matrix en échec (HTTP), repli Haversine.', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $body = $response->json();
            $element = $body['rows'][0]['elements'][0] ?? null;

            if (($body['status'] ?? null) !== 'OK' || ($element['status'] ?? null) !== 'OK') {
                Log::warning('RidePricingService: statut Google Distance Matrix non exploitable, repli Haversine.', [
                    'top_level_status' => $body['status'] ?? null,
                    'element_status' => $element['status'] ?? null,
                ]);
                return null;
            }

            return [
                'distance_km' => $element['distance']['value'] / 1000,
                'duration_min' => (int) round($element['duration']['value'] / 60),
            ];
        } catch (\Throwable $e) {
            Log::warning('RidePricingService: exception lors de l\'appel Google Distance Matrix, repli Haversine.', [
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function logIfMismatch(string $serviceType, float $clientEstimatedPrice, float $serverPrice): void
    {
        if ($serverPrice <= 0) {
            return;
        }

        $relativeDiff = abs($clientEstimatedPrice - $serverPrice) / $serverPrice;

        if ($relativeDiff > self::PRICE_MISMATCH_THRESHOLD) {
            Log::warning('RidePricingService: écart important entre estimated_price (client) et le prix serveur.', [
                'service_type' => $serviceType,
                'client_estimated_price' => $clientEstimatedPrice,
                'server_price' => $serverPrice,
                'relative_diff' => round($relativeDiff, 3),
            ]);
        }
    }
}
