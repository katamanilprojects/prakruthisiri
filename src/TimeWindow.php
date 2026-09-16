<?php

declare(strict_types=1);

namespace PrakruthiSiri;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Handles order time windows, delivery cutoffs, and scheduled delivery date calculation
 * for the Prakruthi Siri organic platform in the Hanamkonda-Warangal region.
 */
class TimeWindow
{
    public const TIMEZONE = 'Asia/Kolkata';
    public const CUTOFF_TIME = '18:00:00';
    public const CUTOFF_HOUR = 18;

    private static ?DateTimeZone $timeZoneInstance = null;

    /**
     * Get the standardized DateTimeZone for the platform.
     */
    public static function getTimeZone(): DateTimeZone
    {
        if (self::$timeZoneInstance === null) {
            self::$timeZoneInstance = new DateTimeZone(self::TIMEZONE);
        }

        return self::$timeZoneInstance;
    }

    /**
     * Create a current DateTimeImmutable guaranteed to be in Asia/Kolkata timezone.
     */
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::getTimeZone());
    }

    /**
     * Calculates the target delivery date according to the platform cutoff rules:
     * - 06:00:00 AM to 17:59:59 PM: Tomorrow (T + 1)
     * - 18:00:00 PM to 23:59:59 PM: Day-After-Tomorrow (T + 2)
     * - 00:00:00 AM to 05:59:59 AM: Tomorrow (T + 1)
     *
     * The returned DateTimeImmutable is normalized to 00:00:00 in Asia/Kolkata timezone.
     */
    public function getTargetDeliveryDate(DateTimeImmutable $now): DateTimeImmutable
    {
        $kolkataNow = $now->setTimezone(self::getTimeZone());
        $timeStr = $kolkataNow->format('H:i:s');

        // Check if current time falls in the evening post-cutoff window (18:00:00 - 23:59:59)
        if ($timeStr >= self::CUTOFF_TIME && $timeStr <= '23:59:59') {
            return $kolkataNow->modify('+2 days')->setTime(0, 0, 0);
        }

        // Both 00:00:00 - 05:59:59 (early morning before harvest) and 06:00:00 - 17:59:59 (daytime)
        // target Tomorrow morning (T + 1)
        return $kolkataNow->modify('+1 day')->setTime(0, 0, 0);
    }

    /**
     * Calculates the active production, harvest packing, and dispatch run date.
     * Unlike new customer orders (which target T+2 after 18:00 cutoff), operations
     * during the evening are actively packing for tomorrow morning's delivery.
     *
     * - 00:00:00 AM to 05:59:59 AM (Early morning dispatch): Today (T)
     * - 06:00:00 AM to 17:59:59 PM (Daytime preparation): Tomorrow (T + 1)
     * - 18:00:00 PM to 23:59:59 PM (Evening harvest & packing): Tomorrow (T + 1)
     */
    public function getActiveProductionRunDate(DateTimeImmutable $now): DateTimeImmutable
    {
        $kolkataNow = $now->setTimezone(self::getTimeZone());
        $timeStr = $kolkataNow->format('H:i:s');

        // Early morning delivery dispatch before 6:00 AM: Today (T)
        if ($timeStr < '06:00:00') {
            return $kolkataNow->setTime(0, 0, 0);
        }

        // Daytime preparation and evening harvest/packing: Tomorrow (T + 1)
        return $kolkataNow->modify('+1 day')->setTime(0, 0, 0);
    }

    /**
     * Checks if the given timestamp is between 18:00:00 and 23:59:59 in Asia/Kolkata.
     */
    public function isPastCutoff(DateTimeImmutable $now): bool
    {
        $kolkataNow = $now->setTimezone(self::getTimeZone());
        $timeStr = $kolkataNow->format('H:i:s');

        return $timeStr >= self::CUTOFF_TIME && $timeStr <= '23:59:59';
    }

    /**
     * Returns a customer-facing banner notice when past 6:00 PM cutoff, or null otherwise.
     */
    public function getBannerNotice(DateTimeImmutable $now): ?string
    {
        if (!$this->isPastCutoff($now)) {
            return null;
        }

        $targetDate = $this->getTargetDeliveryDate($now);
        $formattedDate = $targetDate->format('l, d M Y');

        return sprintf(
            'Notice: Today’s 6:00 PM order cutoff has passed for tomorrow morning delivery. ' .
            'Fresh chemical-free vegetables will be harvested tomorrow evening and delivered on %s.',
            $formattedDate
        );
    }
}
