<?php

declare(strict_types=1);

namespace PrakruthiSiri;

use PDO;
use PrakruthiSiri\Config\Database;

require_once __DIR__ . '/GeoFenceService.php';

/**
 * System configuration service that caches operational settings and evaluates
 * delivery fee thresholds.
 */
class ConfigService
{
    public const DEFAULT_MOV_THRESHOLD = 1.00;
    public const DEFAULT_DELIVERY_FEE = 0.00;
    public const DEFAULT_CUTOFF_TIME = '19:00:00';
    public const DEFAULT_STORE_STATUS = 'AUTO';
    public const DEFAULT_HUB_NAME = 'Prakruthi Siri Central Hub & Organic Farm';
    public const DEFAULT_HUB_ADDRESS = 'KU Cross Road, Naimnagar, Hanamkonda, Warangal, Telangana 506009';
    public const DEFAULT_HUB_LATITUDE = 18.02843900;
    public const DEFAULT_HUB_LONGITUDE = 79.63594100;
    public const DEFAULT_WHATSAPP_NUMBER = '919393767927';

    private PDO $pdo;

    /**
     * In-memory cache of key-value system settings.
     *
     * @var array<string, string>|null
     */
    private ?array $settingsCache = null;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo === null) {
            require_once __DIR__ . '/../config/database.php';
            $this->pdo = Database::getInstance()->getConnection();
        } else {
            $this->pdo = $pdo;
        }
    }

    /**
     * Loads all system settings into memory cache.
     *
     * @return array<string, string>
     */
    public function loadSettings(): array
    {
        if ($this->settingsCache !== null) {
            return $this->settingsCache;
        }

        $stmt = $this->pdo->query('SELECT `setting_key`, `setting_value` FROM `system_settings`');
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $this->settingsCache = $rows;
        return $this->settingsCache;
    }

    /**
     * Clears the in-memory cache and reloads settings from the database.
     */
    public function refresh(): void
    {
        $this->settingsCache = null;
        $this->loadSettings();
    }

    /**
     * Retrieve a specific setting value.
     */
    public function getSetting(string $key, ?string $default = null): ?string
    {
        $settings = $this->loadSettings();
        return $settings[$key] ?? $default;
    }

    /**
     * Get Minimum Order Value (MOV) threshold for free delivery.
     */
    public function getMovThreshold(): float
    {
        $val = $this->getSetting('mov_threshold');
        return $val !== null ? (float) $val : self::DEFAULT_MOV_THRESHOLD;
    }

    /**
     * Get standard delivery fee charged when subtotal is below the MOV threshold.
     */
    public function getStandardDeliveryFee(): float
    {
        $val = $this->getSetting('delivery_fee_amount');
        return $val !== null ? (float) $val : self::DEFAULT_DELIVERY_FEE;
    }

    /**
     * Get store operational override status ('AUTO', 'OPEN', 'CLOSED').
     */
    public function getStoreOverrideStatus(): string
    {
        return $this->getSetting('store_override_status', self::DEFAULT_STORE_STATUS) ?? self::DEFAULT_STORE_STATUS;
    }

    /**
     * Get the cutoff time string (e.g. '18:00:00').
     */
    public function getCutoffTime(): string
    {
        return $this->getSetting('cutoff_time', self::DEFAULT_CUTOFF_TIME) ?? self::DEFAULT_CUTOFF_TIME;
    }

    /**
     * Get official Store WhatsApp dispatch number.
     */
    public function getStoreWhatsAppNumber(): string
    {
        $raw = $this->getSetting('store_whatsapp_number', self::DEFAULT_WHATSAPP_NUMBER) ?? self::DEFAULT_WHATSAPP_NUMBER;
        $digits = preg_replace('/\D/', '', $raw);
        if (strlen($digits) === 10) {
            return '91' . $digits;
        }
        return $digits !== '' ? $digits : self::DEFAULT_WHATSAPP_NUMBER;
    }

    /**
     * Calculates the delivery fee based on order subtotal.
     * - If subtotal >= mov_threshold: Free delivery (0.00)
     * - Otherwise: Returns standard delivery fee amount
     */
    public function calculateDeliveryFee(float $subtotal): float
    {
        $threshold = $this->getMovThreshold();
        if ($subtotal >= $threshold) {
            return 0.00;
        }

        return $this->getStandardDeliveryFee();
    }

    /**
     * Get Store / Farm Dispatch Hub location details.
     * 
     * @return array{name: string, address: string, latitude: float, longitude: float}
     */
    public function getStoreHubLocation(): array
    {
        $name      = $this->getSetting('store_hub_name', self::DEFAULT_HUB_NAME) ?? self::DEFAULT_HUB_NAME;
        $address   = $this->getSetting('store_hub_address', self::DEFAULT_HUB_ADDRESS) ?? self::DEFAULT_HUB_ADDRESS;
        $latVal    = $this->getSetting('store_hub_latitude');
        $lngVal    = $this->getSetting('store_hub_longitude');

        $latitude  = $latVal !== null && is_numeric($latVal) ? (float) $latVal : self::DEFAULT_HUB_LATITUDE;
        $longitude = $lngVal !== null && is_numeric($lngVal) ? (float) $lngVal : self::DEFAULT_HUB_LONGITUDE;

        return [
            'name'      => $name,
            'address'   => $address,
            'latitude'  => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * Updates Store / Farm Dispatch Hub location details.
     */
    public function updateStoreHubLocation(string $name, string $address, float $latitude, float $longitude): bool
    {
        $settings = [
            'store_hub_name'      => trim($name),
            'store_hub_address'   => trim($address),
            'store_hub_latitude'  => number_format($latitude, 8, '.', ''),
            'store_hub_longitude' => number_format($longitude, 8, '.', ''),
        ];

        $stmt = $this->pdo->prepare('
            INSERT INTO `system_settings` (`setting_key`, `setting_value`)
            VALUES (:key, :val)
            ON DUPLICATE KEY UPDATE
                `setting_value` = VALUES(`setting_value`),
                `updated_at` = CURRENT_TIMESTAMP
        ');

        foreach ($settings as $k => $v) {
            $stmt->execute([':key' => $k, ':val' => $v]);
        }

        $this->refresh();
        return true;
    }
}
