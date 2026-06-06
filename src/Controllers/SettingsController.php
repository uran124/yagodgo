<?php
namespace App\Controllers;

use App\Services\DeliveryPricingService;
use PDO;

class SettingsController
{
    private PDO $pdo;
    private ?DeliveryPricingService $deliveryPricingService = null;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    private function deliveryPricing(): DeliveryPricingService
    {
        if ($this->deliveryPricingService === null) {
            $this->deliveryPricingService = new DeliveryPricingService($this->pdo);
        }

        return $this->deliveryPricingService;
    }

    /**
     * @return array<string, string>
     */
    private function sections(): array
    {
        return [
            'general'  => 'Основные',
            'pricing'  => 'Цены',
            'preorder' => 'Предзаказ',
            'payments' => 'Оплата',
            'delivery' => 'Доставка',
            'theme'    => 'Тема',
        ];
    }

    private function normalizeSection(?string $section): string
    {
        $section = trim((string)$section);
        return array_key_exists($section, $this->sections()) ? $section : 'general';
    }

    // Форма настроек
    public function index(?string $section = null): void
    {
        $activeSection = $this->normalizeSection($section);
        // Например, читаем из таблицы settings
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings");
        $all = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        viewAdmin('settings', [
          'pageTitle'           => 'Настройки — ' . $this->sections()[$activeSection],
          'settings'            => $all,
          'themeColors'         => \get_theme_palette(),
          'deliveryTariffZones' => $activeSection === 'delivery' ? $this->getDeliveryTariffZones() : [],
          'settingsSections'    => $this->sections(),
          'activeSection'       => $activeSection,
        ]);
    }

    // Сохранение
    public function save(?string $section = null): void
    {
        $activeSection = $this->normalizeSection($section);
        $allowedKeys = [
            'general' => ['company_name', 'contact_phone'],
            'pricing' => ['pricing_instant_margin_percent', 'pricing_rounding_step'],
            'preorder' => ['ui_preorder_discount_percent', 'ui_preorder_price_hint', 'ui_home_no_stock_message'],
            'payments' => [
                'robokassa_enabled',
                'robokassa_is_test',
                'robokassa_merchant_login',
                'robokassa_hash_algorithm',
                'robokassa_password1',
                'robokassa_password2',
                'robokassa_payment_url',
                'robokassa_inc_curr_label',
                'robokassa_culture',
                'robokassa_encoding',
                'robokassa_expiration_minutes',
                'robokassa_default_description',
                'robokassa_result_url',
                'robokassa_success_url',
                'robokassa_fail_url',
            ],
            'delivery' => [
                'delivery_store_address',
                'delivery_default_fee',
                'delivery_store_lat',
                'delivery_store_lng',
                'delivery_per_km_from_km',
                'delivery_per_km_price',
                'openrouteservice_api_key',
                'openrouteservice_snap_radius_m',
                'dadata_api_key',
                'dadata_secret_key',
                'delivery_dadata_center_lat',
                'delivery_dadata_center_lng',
                'delivery_dadata_radius_m',
                'delivery_dadata_suggestion_count',
                'delivery_taxi_courier_enabled',
                'delivery_taxi_courier_button_text',
                'delivery_taxi_courier_instructions',
            ],
            'theme' => ['theme_light_primary', 'theme_dark_primary'],
        ][$activeSection];
        $_POST = array_intersect_key($_POST, array_flip(array_merge($allowedKeys, ['delivery_tariff_zones'])));
        if ($activeSection === 'pricing') {
            $instantMargin = isset($_POST['pricing_instant_margin_percent']) ? (float)$_POST['pricing_instant_margin_percent'] : 50.0;
            $_POST['pricing_instant_margin_percent'] = (string)max(0.0, min(500.0, $instantMargin));

            $roundingStep = isset($_POST['pricing_rounding_step']) ? (int)$_POST['pricing_rounding_step'] : 10;
            $_POST['pricing_rounding_step'] = (string)max(1, min(10000, $roundingStep));
        }

        if ($activeSection === 'preorder') {
            $discount = isset($_POST['ui_preorder_discount_percent']) ? (float)$_POST['ui_preorder_discount_percent'] : 10.0;
            $_POST['ui_preorder_discount_percent'] = (string)max(0.0, min(99.0, $discount));

            $hint = trim((string)($_POST['ui_preorder_price_hint'] ?? ''));
            if ($hint === '') {
                $hint = 'Цена ориентировочная, точная цена будет после поступления';
            }
            $_POST['ui_preorder_price_hint'] = $hint;

            $noStockMessage = trim((string)($_POST['ui_home_no_stock_message'] ?? ''));
            if ($noStockMessage === '') {
                $noStockMessage = 'На данный момент ягод нет в наличии. Воспользуйтесь нашим предложением предварительного заказа со скидкой 10% — это дополнительная скидка за оформление предварительного бронирования.';
            }
            $_POST['ui_home_no_stock_message'] = $noStockMessage;
        }

        if ($activeSection === 'payments') {
            $_POST['robokassa_enabled'] = isset($_POST['robokassa_enabled']) ? '1' : '0';
            $_POST['robokassa_is_test'] = isset($_POST['robokassa_is_test']) ? '1' : '0';

            $robokassaHash = strtoupper(trim((string)($_POST['robokassa_hash_algorithm'] ?? 'MD5')));
            if (!in_array($robokassaHash, ['MD5', 'SHA256', 'SHA384', 'SHA512'], true)) {
                $robokassaHash = 'MD5';
            }
            $_POST['robokassa_hash_algorithm'] = $robokassaHash;

            $robokassaCulture = trim((string)($_POST['robokassa_culture'] ?? 'ru'));
            $_POST['robokassa_culture'] = in_array($robokassaCulture, ['ru', 'en'], true) ? $robokassaCulture : 'ru';

            $robokassaExpiration = isset($_POST['robokassa_expiration_minutes']) ? (int)$_POST['robokassa_expiration_minutes'] : 60;
            $_POST['robokassa_expiration_minutes'] = (string)max(0, min(10080, $robokassaExpiration));

            $robokassaEncoding = strtoupper(trim((string)($_POST['robokassa_encoding'] ?? 'UTF-8')));
            $_POST['robokassa_encoding'] = $robokassaEncoding !== '' ? $robokassaEncoding : 'UTF-8';

            $robokassaDescription = trim((string)($_POST['robokassa_default_description'] ?? ''));
            if ($robokassaDescription === '') {
                $robokassaDescription = 'Оплата заказа BerryGo';
            }
            $_POST['robokassa_default_description'] = mb_substr($robokassaDescription, 0, 100);

            $robokassaUrlDefaults = [
                'robokassa_payment_url' => 'https://auth.robokassa.ru/Merchant/Index.aspx',
                'robokassa_result_url'  => 'https://berrygo.ru/payments/robokassa/result',
                'robokassa_success_url' => 'https://berrygo.ru/payments/robokassa/success',
                'robokassa_fail_url'    => 'https://berrygo.ru/payments/robokassa/fail',
            ];
            foreach ($robokassaUrlDefaults as $key => $defaultUrl) {
                $url = trim((string)($_POST[$key] ?? ''));
                $_POST[$key] = filter_var($url, FILTER_VALIDATE_URL) ? $url : $defaultUrl;
            }

            foreach (['robokassa_password1', 'robokassa_password2'] as $passwordKey) {
                if (trim((string)($_POST[$passwordKey] ?? '')) === '') {
                    unset($_POST[$passwordKey]);
                }
            }
        }

        if ($activeSection === 'delivery') {
            $_POST['delivery_taxi_courier_enabled'] = isset($_POST['delivery_taxi_courier_enabled']) ? '1' : '0';

            $deliveryDefaults = [
                'delivery_store_address' => 'Самовывоз: 9 мая, 73',
                'delivery_default_fee' => '300',
                'delivery_per_km_from_km' => '6',
                'delivery_per_km_price' => '50',
                'openrouteservice_snap_radius_m' => '2000',
                'delivery_dadata_center_lat' => '56.233717',
                'delivery_dadata_center_lng' => '92.842600',
                'delivery_dadata_radius_m' => '60000',
                'delivery_dadata_suggestion_count' => '8',
                'delivery_taxi_courier_button_text' => 'Вызову такси-курьера',
            ];
            foreach ($deliveryDefaults as $key => $defaultValue) {
                $value = trim((string)($_POST[$key] ?? ''));
                $_POST[$key] = $value !== '' ? $value : $defaultValue;
            }

            foreach (['delivery_default_fee', 'delivery_per_km_price'] as $moneyKey) {
                $value = isset($_POST[$moneyKey]) ? (int)$_POST[$moneyKey] : 0;
                $_POST[$moneyKey] = (string)max(0, min(100000, $value));
            }

            $snapRadius = isset($_POST['openrouteservice_snap_radius_m']) ? (int)$_POST['openrouteservice_snap_radius_m'] : 2000;
            $_POST['openrouteservice_snap_radius_m'] = (string)max(1, min(50000, $snapRadius));

            $dadataRadius = isset($_POST['delivery_dadata_radius_m']) ? (int)$_POST['delivery_dadata_radius_m'] : 60000;
            $_POST['delivery_dadata_radius_m'] = (string)max(1000, min(300000, $dadataRadius));

            $suggestionCount = isset($_POST['delivery_dadata_suggestion_count']) ? (int)$_POST['delivery_dadata_suggestion_count'] : 8;
            $_POST['delivery_dadata_suggestion_count'] = (string)max(1, min(20, $suggestionCount));

            foreach (['delivery_store_lat', 'delivery_store_lng', 'delivery_per_km_from_km', 'delivery_dadata_center_lat', 'delivery_dadata_center_lng'] as $floatKey) {
                $value = str_replace(',', '.', trim((string)($_POST[$floatKey] ?? '')));
                if ($value === '' || !is_numeric($value)) {
                    if ($floatKey === 'delivery_per_km_from_km') {
                        $_POST[$floatKey] = '6';
                    } elseif ($floatKey === 'delivery_dadata_center_lat') {
                        $_POST[$floatKey] = '56.233717';
                    } elseif ($floatKey === 'delivery_dadata_center_lng') {
                        $_POST[$floatKey] = '92.842600';
                    } else {
                        $_POST[$floatKey] = '';
                    }
                    continue;
                }
                $number = (float)$value;
                if ($floatKey === 'delivery_store_lat' || $floatKey === 'delivery_dadata_center_lat') {
                    $number = max(-90.0, min(90.0, $number));
                } elseif ($floatKey === 'delivery_store_lng' || $floatKey === 'delivery_dadata_center_lng') {
                    $number = max(-180.0, min(180.0, $number));
                } else {
                    $number = max(0.0, min(1000.0, $number));
                }
                $_POST[$floatKey] = rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
            }

            $_POST['delivery_taxi_courier_instructions'] = trim((string)($_POST['delivery_taxi_courier_instructions'] ?? ''));

            foreach (['openrouteservice_api_key', 'dadata_api_key', 'dadata_secret_key'] as $passwordKey) {
                if (trim((string)($_POST[$passwordKey] ?? '')) === '') {
                    unset($_POST[$passwordKey]);
                }
            }
        }

        $deliveryTariffZones = ($activeSection === 'delivery' && is_array($_POST['delivery_tariff_zones'] ?? null)) ? $_POST['delivery_tariff_zones'] : [];
        unset($_POST['delivery_tariff_zones']);

        // Важно: CREATE TABLE в MySQL делает implicit commit. Создаём таблицу зон ДО транзакции,
        // иначе первое сохранение раздела доставки может упасть на commit()/rollback().
        if ($activeSection === 'delivery') {
            $this->ensureDeliveryTariffZonesTable();
        }

        $this->pdo->beginTransaction();
        try {
            if ($activeSection === 'delivery') {
                $this->saveDeliveryTariffZones($deliveryTariffZones);
            }

            foreach ($_POST as $key => $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $stmt = $this->pdo->prepare(
                  "REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)"
                );
                $stmt->execute([$key, trim((string)$value)]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        header('Location: ' . $this->sectionUrl($activeSection));
        exit;
    }


    public function testDeliveryTariff(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $address = trim((string)($_POST['address'] ?? ''));
        if ($address === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Введите адрес доставки.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $settings = $this->getSettingsMap();
            $result = $this->deliveryPricing()->calculateForAddress($address, $settings, [
                'selected_lat' => $_POST['selected_lat'] ?? '',
                'selected_lng' => $_POST['selected_lng'] ?? '',
                'selected_address' => $_POST['selected_address'] ?? '',
            ]);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    public function suggestDeliveryAddresses(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        if (function_exists('set_time_limit')) {
            @set_time_limit(8);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $query = trim((string)($_GET['query'] ?? $_POST['query'] ?? ''));
        if (function_exists('mb_strlen')) {
            $queryLength = mb_strlen($query, 'UTF-8');
        } else {
            $queryLength = strlen($query);
        }

        if ($queryLength < 3) {
            echo json_encode(['ok' => true, 'suggestions' => [], 'message' => 'Введите минимум 3 символа.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $settings = $this->getSettingsMap();
            $suggestions = $this->deliveryPricing()->suggestAddresses($query, $settings);
            echo json_encode([
                'ok' => true,
                'query' => $query,
                'suggestions' => $suggestions,
                'center' => $this->deliveryPricing()->getDadataGeoCenter($settings),
                'radius_meters' => $this->deliveryPricing()->getDadataRadiusMeters($settings),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage(), 'suggestions' => []], JSON_UNESCAPED_UNICODE);
        }
    }

    private function sectionUrl(string $section): string
    {
        return $section === 'general' ? '/admin/settings' : '/admin/settings/' . $section;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDeliveryTariffZones(): array
    {
        $this->ensureDeliveryTariffZonesTable();
        if (!$this->tableExists('delivery_tariff_zones')) {
            return [];
        }

        $stmt = $this->pdo->query(
            "SELECT id, min_km, max_km, price_rub, sort_order, is_active
             FROM delivery_tariff_zones
             ORDER BY sort_order ASC, min_km ASC, id ASC"
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @param array<string, mixed> $zones
     */
    private function saveDeliveryTariffZones(array $zones): void
    {
        $this->ensureDeliveryTariffZonesTable();
        if (!$this->tableExists('delivery_tariff_zones')) {
            return;
        }

        $ids = $zones['id'] ?? [];
        $mins = $zones['min_km'] ?? [];
        $maxes = $zones['max_km'] ?? [];
        $prices = $zones['price_rub'] ?? [];
        $orders = $zones['sort_order'] ?? [];
        $actives = $zones['is_active'] ?? [];
        $deletes = $zones['delete'] ?? [];

        $count = max(count((array)$mins), count((array)$maxes), count((array)$prices), count((array)$ids));
        $upsert = $this->pdo->prepare(
            "INSERT INTO delivery_tariff_zones (id, min_km, max_km, price_rub, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               min_km = VALUES(min_km),
               max_km = VALUES(max_km),
               price_rub = VALUES(price_rub),
               sort_order = VALUES(sort_order),
               is_active = VALUES(is_active)"
        );
        $delete = $this->pdo->prepare("DELETE FROM delivery_tariff_zones WHERE id = ?");

        for ($i = 0; $i < $count; $i++) {
            $id = (int)($ids[$i] ?? 0);
            if ($id > 0 && isset($deletes[$i])) {
                $delete->execute([$id]);
                continue;
            }

            $minRaw = str_replace(',', '.', trim((string)($mins[$i] ?? '')));
            $maxRaw = str_replace(',', '.', trim((string)($maxes[$i] ?? '')));
            $price = (int)($prices[$i] ?? 0);
            if ($minRaw === '' && $maxRaw === '' && $price <= 0) {
                continue;
            }
            if ($minRaw === '' || !is_numeric($minRaw) || $price <= 0) {
                continue;
            }

            $min = max(0.0, (float)$minRaw);
            $max = ($maxRaw !== '' && is_numeric($maxRaw)) ? max(0.0, (float)$maxRaw) : null;
            if ($max !== null && $max <= $min) {
                continue;
            }

            $sortOrder = (int)($orders[$i] ?? ($i + 1));
            $isActive = isset($actives[$i]) ? 1 : 0;
            $upsert->execute([
                $id > 0 ? $id : null,
                $this->formatDecimal($min),
                $max !== null ? $this->formatDecimal($max) : null,
                max(0, min(100000, $price)),
                $sortOrder,
                $isActive,
            ]);
        }
    }


    private function ensureDeliveryTariffZonesTable(): void
    {
        if ($this->tableExists('delivery_tariff_zones')) {
            return;
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS delivery_tariff_zones (
              id int UNSIGNED NOT NULL AUTO_INCREMENT,
              min_km decimal(8,3) NOT NULL,
              max_km decimal(8,3) DEFAULT NULL,
              price_rub int UNSIGNED NOT NULL,
              sort_order int NOT NULL DEFAULT 0,
              is_active tinyint(1) NOT NULL DEFAULT 1,
              created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_delivery_tariff_zones_active_range (is_active, min_km, max_km),
              KEY idx_delivery_tariff_zones_sort (sort_order, min_km)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }


    /**
     * @return array<string, string>
     */
    private function getSettingsMap(): array
    {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
    }

    /**
     * @param array<string, string> $settings
     * @return array{lat: float, lng: float, address: string, diagnostics: array<string, mixed>}
     */
    private function resolvePostedOrTypedDeliveryAddress(string $address, array $settings): array
    {
        $selectedLat = $this->parseNullableFloat($_POST['selected_lat'] ?? '');
        $selectedLng = $this->parseNullableFloat($_POST['selected_lng'] ?? '');
        $selectedAddress = trim((string)($_POST['selected_address'] ?? ''));

        if ($selectedLat !== null && $selectedLng !== null && $selectedAddress !== '') {
            return [
                'lat' => $selectedLat,
                'lng' => $selectedLng,
                'address' => $selectedAddress,
                'diagnostics' => [
                    'input' => $address,
                    'source' => 'selected_dadata_suggestion',
                    'selected' => [
                        'address' => $selectedAddress,
                        'geo_lat' => $this->formatCoordinate($selectedLat),
                        'geo_lon' => $this->formatCoordinate($selectedLng),
                    ],
                ],
            ];
        }

        if ($this->looksLikeCoordinates($address)) {
            return $this->resolveDeliveryAddress($address, $settings);
        }

        $suggestions = $this->getDeliveryAddressSuggestions($address, $settings);
        if (count($suggestions) > 0) {
            $message = 'Выберите адрес из подсказок DaData, чтобы не подставить случайный город/улицу. Найдено вариантов: ' . count($suggestions) . '.';
            throw new \RuntimeException($message);
        }

        return $this->resolveDeliveryAddress($address, $settings);
    }

    /**
     * @param array<string, string> $settings
     * @return array<int, array<string, mixed>>
     */
    private function getDeliveryAddressSuggestions(string $query, array $settings): array
    {
        $apiKey = trim((string)($settings['dadata_api_key'] ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException('Для подсказок адреса сохраните DaData API key в настройках доставки.');
        }

        $center = $this->getDadataGeoCenter($settings);
        $radiusMeters = $this->getDadataRadiusMeters($settings);
        $count = $this->getDadataSuggestionCount($settings);

        $payload = [
            'query' => $query,
            'count' => $count,
            'locations_geo' => [[
                'lat' => $center['lat'],
                'lon' => $center['lng'],
                'radius_meters' => $radiusMeters,
            ]],
        ];

        $suggest = $this->postJsonDetailed(
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address',
            $payload,
            ['Authorization: Token ' . $apiKey]
        );

        if (!$suggest['ok']) {
            throw new \RuntimeException($this->externalCallErrorText('DaData suggest/address', $suggest));
        }

        $response = is_array($suggest['data'] ?? null) ? $suggest['data'] : [];
        $rows = is_array($response['suggestions'] ?? null) ? $response['suggestions'] : [];
        $result = [];
        $seen = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $data = is_array($row['data'] ?? null) ? $row['data'] : [];
            $lat = $this->parseNullableFloat($data['geo_lat'] ?? '');
            $lng = $this->parseNullableFloat($data['geo_lon'] ?? '');
            if ($lat === null || $lng === null) {
                continue;
            }

            $distanceFromCenterKm = $this->haversineKm((float)$center['lat'], (float)$center['lng'], $lat, $lng);
            if (($distanceFromCenterKm * 1000.0) > ($radiusMeters + 500.0)) {
                continue;
            }

            $value = (string)($row['value'] ?? '');
            $unrestricted = (string)($row['unrestricted_value'] ?? $value);
            $keySource = $unrestricted . '|' . $this->formatCoordinate($lat) . '|' . $this->formatCoordinate($lng);
            $key = function_exists('mb_strtolower') ? mb_strtolower($keySource, 'UTF-8') : strtolower($keySource);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $cityLabel = (string)($data['settlement_with_type'] ?? $data['city_with_type'] ?? $data['settlement'] ?? $data['city'] ?? '');
            $districtLabel = (string)($data['city_district_with_type'] ?? '');
            $streetLabel = (string)($data['street_with_type'] ?? $data['street'] ?? '');
            $houseLabel = (string)($data['house'] ?? '');

            $result[] = [
                'value' => $value,
                'unrestricted_value' => $unrestricted,
                'label' => $this->formatAddressSuggestionLabel($data, $value),
                'city' => $cityLabel,
                'district' => $districtLabel,
                'street' => $streetLabel,
                'house' => $houseLabel,
                'lat' => $this->formatCoordinate($lat),
                'lng' => $this->formatCoordinate($lng),
                'qc_geo' => $data['qc_geo'] ?? null,
                'fias_level' => $data['fias_level'] ?? null,
                'distance_from_center_km' => $this->formatDecimal($distanceFromCenterKm),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatAddressSuggestionLabel(array $data, string $fallback): string
    {
        $city = trim((string)($data['settlement_with_type'] ?? $data['city_with_type'] ?? $data['settlement'] ?? $data['city'] ?? ''));
        $street = trim((string)($data['street_with_type'] ?? $data['street'] ?? ''));
        $house = trim((string)($data['house'] ?? ''));
        $flat = trim((string)($data['flat'] ?? ''));

        $parts = [];
        if ($city !== '') $parts[] = $city;
        if ($street !== '') $parts[] = $street;
        if ($house !== '') $parts[] = 'д ' . $house;
        if ($flat !== '') $parts[] = 'кв ' . $flat;

        return $parts ? implode(', ', $parts) : $fallback;
    }

    /**
     * @param array<string, string> $settings
     * @return array{lat: float, lng: float}
     */
    private function getDadataGeoCenter(array $settings): array
    {
        $lat = $this->parseNullableFloat($settings['delivery_dadata_center_lat'] ?? '') ?? 56.233717;
        $lng = $this->parseNullableFloat($settings['delivery_dadata_center_lng'] ?? '') ?? 92.842600;
        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * @param array<string, string> $settings
     */
    private function getDadataRadiusMeters(array $settings): int
    {
        $radius = (int)($settings['delivery_dadata_radius_m'] ?? 60000);
        return max(1000, min(300000, $radius));
    }

    /**
     * @param array<string, string> $settings
     */
    private function getDadataSuggestionCount(array $settings): int
    {
        $count = (int)($settings['delivery_dadata_suggestion_count'] ?? 8);
        return max(1, min(20, $count));
    }

    private function looksLikeCoordinates(string $address): bool
    {
        return (bool)preg_match('/^\s*-?\d+(?:[\.,]\d+)?\s*[,; ]\s*-?\d+(?:[\.,]\d+)?\s*$/u', $address);
    }

    /**
     * @param array<string, string> $settings
     * @return array{lat: float, lng: float, address: string}
     */
    private function resolveDeliveryAddress(string $address, array $settings): array
    {
        $diagnostics = [
            'input' => $address,
            'source' => null,
            'clean' => ['attempted' => false],
            'suggest' => ['attempted' => false],
        ];

        if (preg_match('/^\s*(-?\d+(?:[\.,]\d+)?)\s*[,; ]\s*(-?\d+(?:[\.,]\d+)?)\s*$/u', $address, $m)) {
            $lat = (float)str_replace(',', '.', $m[1]);
            $lng = (float)str_replace(',', '.', $m[2]);
            if ($lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0) {
                $diagnostics['source'] = 'manual_coordinates';
                $diagnostics['manual_coordinates'] = ['lat' => $lat, 'lng' => $lng];
                return ['lat' => $lat, 'lng' => $lng, 'address' => $address, 'diagnostics' => $diagnostics];
            }
        }

        $apiKey = trim((string)($settings['dadata_api_key'] ?? ''));
        $secretKey = trim((string)($settings['dadata_secret_key'] ?? ''));
        $diagnostics['api_key_present'] = $apiKey !== '';
        $diagnostics['secret_key_present'] = $secretKey !== '';
        if ($apiKey === '') {
            throw new \RuntimeException('Для проверки по адресу сохраните DaData API key. Можно также ввести координаты в формате: 56.010, 92.852.');
        }

        $cleanError = null;
        if ($secretKey !== '') {
            $cleanUrl = 'https://cleaner.dadata.ru/api/v1/clean/address';
            $diagnostics['clean'] = ['attempted' => true, 'url' => $cleanUrl, 'method' => 'POST'];
            $clean = $this->postJsonDetailed($cleanUrl, [$address], [
                'Authorization: Token ' . $apiKey,
                'X-Secret: ' . $secretKey,
            ]);
            $diagnostics['clean'] = array_merge($diagnostics['clean'], $this->externalCallDiagnostics($clean));
            if ($clean['ok']) {
                $response = is_array($clean['data'] ?? null) ? $clean['data'] : [];
                $item = is_array($response[0] ?? null) ? $response[0] : [];
                $lat = $this->parseNullableFloat($item['geo_lat'] ?? '');
                $lng = $this->parseNullableFloat($item['geo_lon'] ?? '');
                $diagnostics['clean']['result_address'] = (string)($item['result'] ?? '');
                $diagnostics['clean']['qc_geo'] = $item['qc_geo'] ?? null;
                $diagnostics['clean']['geo_lat'] = $lat !== null ? $this->formatDecimal($lat) : null;
                $diagnostics['clean']['geo_lon'] = $lng !== null ? $this->formatDecimal($lng) : null;
                if ($lat !== null && $lng !== null) {
                    $diagnostics['source'] = 'dadata_clean';
                    return [
                        'lat' => $lat,
                        'lng' => $lng,
                        'address' => (string)($item['result'] ?? $address),
                        'diagnostics' => $diagnostics,
                    ];
                }
                $cleanError = 'clean/address не вернул geo_lat/geo_lon.';
            } else {
                $cleanError = $this->externalCallErrorText('DaData clean/address', $clean);
            }
        } else {
            $diagnostics['clean']['skipped_reason'] = 'DaData Secret key не заполнен.';
        }

        // Fallback на suggestions API: на рабочем сайте координаты часто берутся именно из подсказки.
        $suggestUrl = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address';
        $diagnostics['suggest'] = ['attempted' => true, 'url' => $suggestUrl, 'method' => 'POST'];
        $center = $this->getDadataGeoCenter($settings);
        $suggest = $this->postJsonDetailed($suggestUrl, [
            'query' => $address,
            'count' => 1,
            'locations_geo' => [[
                'lat' => $center['lat'],
                'lon' => $center['lng'],
                'radius_meters' => $this->getDadataRadiusMeters($settings),
            ]],
        ], [
            'Authorization: Token ' . $apiKey,
        ]);
        $diagnostics['suggest'] = array_merge($diagnostics['suggest'], $this->externalCallDiagnostics($suggest));
        if (!$suggest['ok']) {
            $suffix = $cleanError !== null ? ' Предыдущая ошибка clean/address: ' . $cleanError : '';
            throw new \RuntimeException($this->externalCallErrorText('DaData suggest/address', $suggest) . $suffix);
        }

        $response = is_array($suggest['data'] ?? null) ? $suggest['data'] : [];
        $item = is_array($response['suggestions'][0] ?? null) ? $response['suggestions'][0] : [];
        $data = is_array($item['data'] ?? null) ? $item['data'] : [];
        $lat = $this->parseNullableFloat($data['geo_lat'] ?? '');
        $lng = $this->parseNullableFloat($data['geo_lon'] ?? '');
        $diagnostics['suggest']['value'] = (string)($item['value'] ?? '');
        $diagnostics['suggest']['unrestricted_value'] = (string)($item['unrestricted_value'] ?? '');
        $diagnostics['suggest']['qc_geo'] = $data['qc_geo'] ?? null;
        $diagnostics['suggest']['fias_level'] = $data['fias_level'] ?? null;
        $diagnostics['suggest']['geo_lat'] = $lat !== null ? $this->formatDecimal($lat) : null;
        $diagnostics['suggest']['geo_lon'] = $lng !== null ? $this->formatDecimal($lng) : null;
        if ($lat === null || $lng === null) {
            $suffix = $cleanError !== null ? ' Ошибка clean/address: ' . $cleanError : '';
            throw new \RuntimeException('DaData не смогла определить координаты адреса. Уточните адрес и попробуйте ещё раз.' . $suffix);
        }

        $diagnostics['source'] = 'dadata_suggest';
        return [
            'lat' => $lat,
            'lng' => $lng,
            'address' => (string)($item['value'] ?? $address),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, string> $settings
     * @return array{km: float, source: string, note: string}
     */
    private function calculateDeliveryDistanceKm(float $storeLat, float $storeLng, float $lat, float $lng, array $settings): array
    {
        $apiKey = trim((string)($settings['openrouteservice_api_key'] ?? ''));
        $endpoint = 'https://api.openrouteservice.org/v2/directions/driving-car/json';
        $snapRadiusMeters = isset($settings['openrouteservice_snap_radius_m']) ? (int)$settings['openrouteservice_snap_radius_m'] : 2000;
        $snapRadiusMeters = max(1, min(50000, $snapRadiusMeters));
        $payload = [
            'coordinates' => [[$storeLng, $storeLat], [$lng, $lat]],
            'units' => 'm',
            // ORS по умолчанию ищет автомобильную дорогу только в радиусе 350 м.
            // Для адресов во дворах/парковках увеличиваем радиус привязки к дорожной сети.
            'radiuses' => [$snapRadiusMeters, $snapRadiusMeters],
        ];
        $diagnostics = [
            'enabled' => $apiKey !== '',
            'endpoint' => $endpoint,
            'method' => 'POST',
            'profile' => 'driving-car',
            'format' => 'json',
            'coordinate_order' => '[longitude, latitude]',
            'snap_radius_m' => $snapRadiusMeters,
            'from' => [
                'lat' => $this->formatDecimal($storeLat),
                'lng' => $this->formatDecimal($storeLng),
                'sent_to_ors' => [$this->formatDecimal($storeLng), $this->formatDecimal($storeLat)],
            ],
            'to' => [
                'lat' => $this->formatDecimal($lat),
                'lng' => $this->formatDecimal($lng),
                'sent_to_ors' => [$this->formatDecimal($lng), $this->formatDecimal($lat)],
            ],
            'request_payload' => $payload,
        ];

        $orsError = '';
        if ($apiKey !== '') {
            $result = $this->postJsonDetailed($endpoint, $payload, [
                'Authorization: ' . $apiKey,
            ]);
            $diagnostics = array_merge($diagnostics, $this->externalCallDiagnostics($result));

            if ($result['ok']) {
                $response = is_array($result['data'] ?? null) ? $result['data'] : [];
                $meters = $response['routes'][0]['summary']['distance'] ?? null;
                $duration = $response['routes'][0]['summary']['duration'] ?? null;
                $diagnostics['summary_found'] = is_numeric($meters);
                $diagnostics['response_distance_m'] = is_numeric($meters) ? $this->formatDecimal((float)$meters) : null;
                $diagnostics['response_duration_sec'] = is_numeric($duration) ? $this->formatDecimal((float)$duration) : null;
                if (is_numeric($meters) && (float)$meters > 0) {
                    return [
                        'km' => (float)$meters / 1000.0,
                        'meters' => (float)$meters,
                        'duration_sec' => is_numeric($duration) ? (float)$duration : null,
                        'source' => 'openrouteservice',
                        'note' => 'Расстояние рассчитано по автомобильному маршруту OpenRouteService.',
                        'diagnostics' => $diagnostics,
                    ];
                }
                $orsError = 'OpenRouteService ответил успешно, но не вернул distance в routes[0].summary.distance.';
            } else {
                $orsError = $this->externalCallErrorText('OpenRouteService', $result);
                $routingHint = $this->openRouteServiceRoutingHint((string)($diagnostics['decoded_error'] ?? ''));
                if ($routingHint !== '') {
                    $diagnostics['routing_hint'] = $routingHint;
                    $orsError .= ' ' . $routingHint;
                }
            }
        } else {
            $diagnostics['skipped_reason'] = 'OpenRouteService API key не заполнен.';
        }

        $fallbackKm = $this->haversineKm($storeLat, $storeLng, $lat, $lng);
        $diagnostics['fallback'] = [
            'used' => true,
            'method' => 'haversine_straight_line',
            'distance_km' => $this->formatDecimal($fallbackKm),
            'reason' => $orsError !== '' ? $orsError : ($diagnostics['skipped_reason'] ?? 'OpenRouteService не настроен.'),
        ];

        return [
            'km' => $fallbackKm,
            'meters' => null,
            'duration_sec' => null,
            'source' => 'straight_line',
            'note' => $orsError !== ''
                ? 'OpenRouteService недоступен — показана дистанция по прямой. Ошибка: ' . $orsError
                : 'OpenRouteService не настроен — показана дистанция по прямой для быстрой проверки зоны.',
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @param array<string, string> $settings
     * @return array{price_rub: int, source: string, zone: ?array<string, mixed>, message: string}
     */
    private function calculateDeliveryPriceForDistance(float $distanceKm, array $settings): array
    {
        foreach ($this->getDeliveryTariffZones() as $zone) {
            if ((int)($zone['is_active'] ?? 0) !== 1) {
                continue;
            }
            $min = (float)$zone['min_km'];
            $max = $zone['max_km'] !== null ? (float)$zone['max_km'] : null;
            if ($distanceKm >= $min && ($max === null || $distanceKm <= $max)) {
                return [
                    'price_rub' => (int)$zone['price_rub'],
                    'source' => 'tariff_zone',
                    'zone' => $zone,
                    'message' => sprintf('Попала в зону %.3g–%s км.', $min, $max !== null ? sprintf('%.3g', $max) : '∞'),
                ];
            }
        }

        $defaultFee = max(0, (int)($settings['delivery_default_fee'] ?? 300));
        $fromKm = max(0.0, (float)str_replace(',', '.', (string)($settings['delivery_per_km_from_km'] ?? 6)));
        $perKm = max(0, (int)($settings['delivery_per_km_price'] ?? 50));
        if ($distanceKm > $fromKm && $perKm > 0) {
            $extraKm = (int)ceil($distanceKm - $fromKm);
            return [
                'price_rub' => $defaultFee + ($extraKm * $perKm),
                'source' => 'per_km',
                'zone' => null,
                'message' => sprintf('Фиксированная зона не найдена: %d ₽ + %d км × %d ₽.', $defaultFee, $extraKm, $perKm),
            ];
        }

        return [
            'price_rub' => $defaultFee,
            'source' => 'default_fee',
            'zone' => null,
            'message' => 'Фиксированная зона не найдена — применена стоимость по умолчанию.',
        ];
    }

    /**
     * @param array<int, string> $headers
     * @return mixed
     */
    private function postJson(string $url, array $payload, array $headers = [])
    {
        $result = $this->postJsonDetailed($url, $payload, $headers);
        if (!$result['ok']) {
            throw new \RuntimeException($this->externalCallErrorText('Внешний сервис', $result));
        }
        return $result['data'];
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function postJsonDetailed(string $url, array $payload, array $headers = []): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('На сервере недоступен curl для проверки адреса.');
        }

        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encodedPayload === false) {
            throw new \RuntimeException('Не удалось подготовить JSON для внешнего сервиса.');
        }

        $startedAt = microtime(true);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
            CURLOPT_POSTFIELDS => $encodedPayload,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $totalTime = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $error = curl_error($ch);
        curl_close($ch);

        $bodyString = is_string($body) ? $body : '';
        $decoded = null;
        $jsonError = null;
        if ($bodyString !== '') {
            $decoded = json_decode($bodyString, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $jsonError = json_last_error_msg();
            }
        }

        $ok = $body !== false
            && $status >= 200
            && $status < 300
            && ($bodyString === '' || $jsonError === null);

        return [
            'ok' => $ok,
            'url' => $url,
            'effective_url' => $effectiveUrl !== '' ? $effectiveUrl : $url,
            'http_code' => $status,
            'curl_error' => $error,
            'content_type' => $contentType,
            'total_time_ms' => (int)round(($totalTime > 0 ? $totalTime : (microtime(true) - $startedAt)) * 1000),
            'primary_ip' => $primaryIp,
            'body_preview' => $this->truncateForDiagnostics($bodyString, 900),
            'json_error' => $jsonError,
            'data' => $decoded,
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function externalCallDiagnostics(array $result): array
    {
        return [
            'ok' => (bool)($result['ok'] ?? false),
            'http_code' => (int)($result['http_code'] ?? 0),
            'effective_url' => (string)($result['effective_url'] ?? $result['url'] ?? ''),
            'content_type' => (string)($result['content_type'] ?? ''),
            'total_time_ms' => (int)($result['total_time_ms'] ?? 0),
            'primary_ip' => (string)($result['primary_ip'] ?? ''),
            'curl_error' => (string)($result['curl_error'] ?? ''),
            'json_error' => $result['json_error'] ?? null,
            'decoded_error' => $this->extractExternalError($result['data'] ?? null),
            'body_preview' => (string)($result['body_preview'] ?? ''),
        ];
    }

    /**
     * @param mixed $data
     */
    private function extractExternalError($data): ?string
    {
        if (!is_array($data)) {
            return null;
        }

        if (isset($data['error'])) {
            if (is_string($data['error'])) {
                return $data['error'];
            }
            if (is_array($data['error'])) {
                $parts = [];
                if (isset($data['error']['code'])) {
                    $parts[] = 'code=' . $data['error']['code'];
                }
                if (isset($data['error']['message'])) {
                    $parts[] = (string)$data['error']['message'];
                }
                if ($parts) {
                    return implode('; ', $parts);
                }
            }
        }

        foreach (['message', 'detail', 'title'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }

        return null;
    }


    private function openRouteServiceRoutingHint(string $error): string
    {
        if ($error === '') {
            return '';
        }

        if (strpos($error, 'coordinate 0') !== false) {
            return 'Проблемная точка: координаты магазина. Проверьте delivery_store_lat/delivery_store_lng: точка должна быть рядом с автомобильной дорогой или фактическим адресом магазина.';
        }

        if (strpos($error, 'coordinate 1') !== false) {
            return 'Проблемная точка: координаты клиента. Адрес найден DaData, но точка слишком далеко от автомобильной дороги; уточните дом/корпус/подъезд или увеличьте ORS радиус привязки.';
        }

        if (strpos($error, 'radius of') !== false || strpos($error, 'routable point') !== false) {
            return 'ORS не смог привязать одну из точек к автомобильной дорожной сети. Проверьте порядок [longitude, latitude], координаты и ORS радиус привязки.';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $result
     */
    private function externalCallErrorText(string $serviceName, array $result): string
    {
        $status = (int)($result['http_code'] ?? 0);
        $parts = [];
        $parts[] = $serviceName . ($status > 0 ? ' вернул HTTP ' . $status : ' не вернул HTTP-код');

        $decodedError = $this->extractExternalError($result['data'] ?? null);
        if ($decodedError !== null && $decodedError !== '') {
            $parts[] = $decodedError;
        }
        if (!empty($result['curl_error'])) {
            $parts[] = 'curl: ' . $result['curl_error'];
        }
        if (!empty($result['json_error'])) {
            $parts[] = 'JSON: ' . $result['json_error'];
        }
        if (!empty($result['body_preview']) && $decodedError === null) {
            $parts[] = 'Ответ: ' . $this->truncateForDiagnostics((string)$result['body_preview'], 220);
        }

        return implode('. ', $parts) . '.';
    }

    private function truncateForDiagnostics(string $value, int $limit): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > $limit ? mb_substr($value, 0, $limit, 'UTF-8') . '…' : $value;
        }
        return strlen($value) > $limit ? substr($value, 0, $limit) . '…' : $value;
    }

    private function parseNullableFloat($value): ?float
    {
        $value = str_replace(',', '.', trim((string)$value));
        return $value !== '' && is_numeric($value) ? (float)$value : null;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function formatCoordinate(float $value): string
    {
        return rtrim(rtrim(number_format($value, 7, '.', ''), '0'), '.');
    }

    private function formatDecimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
