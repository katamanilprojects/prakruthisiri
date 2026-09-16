<?php
declare(strict_types=1);

namespace PrakruthiSiri;

/**
 * GeoFenceService
 * 
 * 2-Layer Restriction Strategy:
 * 1. Longitudinal Western Cutoff: Blocks Kazipet (anything west of 79.540°E).
 * 2. Radial Distance Cap: Max 11.5 km from Farm Hub at 18.028444, 79.635944.
 */
class GeoFenceService 
{
    // Exact farm coordinates from Google Maps (Mulugu Road / Arepalli bypass side)
    public const HUB_LAT = 18.02844440;
    public const HUB_LNG = 79.63594440;

    // Strict distance limit: Covers Warangal & Hanamkonda, excludes Kazipet
    public const MAX_RADIUS_KM = 11.5;

    // Western limit: Kazipet begins west of 79.540°E (Waddepally / Fatima Nagar corridor)
    public const WESTERN_LNG_LIMIT = 79.54000000;

    // Kazipet postal codes to reject if customer manual entry matches
    public const BLOCKED_PINCODES = ['506003', '506004'];

    public const ALLOWED_REGIONS = ['Hanamkonda', 'Warangal'];

    public static function getDistanceKm(float $lat, float $lng): float 
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat - self::HUB_LAT);
        $dLng = deg2rad($lng - self::HUB_LNG);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad(self::HUB_LAT)) * cos(deg2rad($lat)) *
             sin($dLng / 2) * sin($dLng / 2);

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Specifically checks if coordinates fall within the local Kazipet urban cluster
     * (west of 79.540°E, but within the local Warangal urban area: lat 17.92-18.06, lng 79.42-79.540, dist <= 18 km).
     * Prevents distant locations like Hyderabad (78.48°E), Secunderabad, or Karnataka from being misclassified as Kazipet.
     */
    public static function isKazipet(?float $latOrLng, ?float $lng = null): bool
    {
        if ($lng === null) {
            $lng = $latOrLng;
            $lat = null;
        } else {
            $lat = $latOrLng;
        }

        if ($lng === null || $lng <= 0) {
            return false;
        }

        // Must be west of 79.540°E
        if ($lng >= self::WESTERN_LNG_LIMIT) {
            return false;
        }

        // If latitude is provided, ensure it is within the local Kazipet urban agglomeration
        if ($lat !== null) {
            if ($lat < 17.9200 || $lat > 18.0600 || $lng < 79.4200) {
                return false;
            }
            if (self::getDistanceKm($lat, $lng) > 18.0) {
                return false;
            }
        } else {
            // Longitude-only check must still fall in the local Kazipet longitude band
            if ($lng < 79.4200) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifies if customer coordinates fall strictly inside Warangal/Hanamkonda delivery bounds.
     */
    public static function isWithinDeliveryRadius(?float $lat, ?float $lng): bool 
    {
        if ($lat === null || $lng === null || $lat <= 0 || $lng <= 0) {
            return false;
        }

        // 1. Longitude Check: Block Kazipet (anything west of 79.540°E)
        if ($lng < self::WESTERN_LNG_LIMIT) {
            return false;
        }

        // 2. Radial Cap: Ensure stop is within 11.5 km of farm hub
        return self::getDistanceKm($lat, $lng) <= self::MAX_RADIUS_KM;
    }

    /**
     * Automatically assigns Hanamkonda or Warangal based on GPS coordinates.
     */
    public static function resolveRegionFromCoords(float $lat, float $lng): string 
    {
        // North of latitude 18.005° is Hanamkonda; South is Warangal
        return ($lat >= 18.0050) ? 'Hanamkonda' : 'Warangal';
    }

    public static function isValidRegion(string $region): bool
    {
        return in_array(trim($region), self::ALLOWED_REGIONS, true);
    }
}
