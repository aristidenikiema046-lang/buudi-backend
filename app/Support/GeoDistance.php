<?php

namespace App\Support;

class GeoDistance
{
    /**
     * Distance à vol d'oiseau entre deux points GPS (formule de Haversine).
     * Extrait de DriverRideController::distanceKm() — désormais aussi
     * utilisé par RidePricingService comme repli quand Google Distance
     * Matrix est indisponible.
     */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
