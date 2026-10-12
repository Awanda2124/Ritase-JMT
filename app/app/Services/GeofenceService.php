<?php

namespace App\Services;

use App\Exceptions\LocationNotConfiguredException;
use App\Exceptions\OutsideRadiusException;

class GeofenceService
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    public function __construct(private SettingService $settings) {}

    /**
     * Fail closed: titik JMT atau radius belum diatur berarti semua lapor ditolak.
     *
     * @return float jarak ke titik JMT dalam meter
     */
    public function ensureWithinRadius(float $lat, float $lng): float
    {
        $jmtLat = $this->settings->jmtLatitude();
        $jmtLng = $this->settings->jmtLongitude();
        $radius = $this->settings->radiusMeters();

        if ($jmtLat === null || $jmtLng === null || $radius <= 0) {
            throw new LocationNotConfiguredException;
        }

        if (! is_finite($lat) || ! is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) {
            throw new OutsideRadiusException(null);
        }

        $distance = $this->haversine($lat, $lng, $jmtLat, $jmtLng);

        if ($distance > $radius) {
            throw new OutsideRadiusException($distance);
        }

        return $distance;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}