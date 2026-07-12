<?php

namespace App\Domain\Shared\Services;

class GeoDistanceService
{
    /**
     * Distância em metros entre dois pontos (latitude/longitude), usando a
     * fórmula de Haversine.
     */
    public function distanceInMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusMeters = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMeters * $c;
    }

    public function isWithinRadius(float $lat1, float $lon1, float $lat2, float $lon2, float $radiusMeters): bool
    {
        return $this->distanceInMeters($lat1, $lon1, $lat2, $lon2) <= $radiusMeters;
    }
}
