<?php
namespace App\Services;

use PDO;

class DeliveryPricingService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, string>
     */
    public function getSettingsMap(): array
    {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTariffZones(): array
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
     * @param array<string, string>|null $settings
     * @return array{lat: float, lng: float}
     */
    public function getDadataGeoCenter(?array $settings = null): array
    {
        $settings = $settings ?? $this->getSettingsMap();
        $lat = $this->parseNullableFloat($settings['delivery_dadata_center_lat'] ?? '') ?? 56.233717;
        $lng = $this->parseNullableFloat($settings['delivery_dadata_center_lng'] ?? '') ?? 92.842600;
        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * @param array<string, string>|null $settings
     */
    public function getDadataRadiusMeters(?array $settings = null): int
    {
        $settings = $settings ?? $this->getSettingsMap();
        $radius = (int)($settings['delivery_dadata_radius_m'] ?? 60000);
        return max(1000, min(300000, $radius));
    }

    /**
     * @param array<string, string>|null $settings
     */
    public function getDadataSuggestionCount(?array $settings = null): int
    {
        $settings = $settings ?? $this->getSettingsMap();
        $count = (int)($settings['delivery_dadata_suggestion_count'] ?? 8);
        return max(1, min(20, $count));
    }

    /**
     * @param array<string, string>|null $settings
     * @return array<int, array<string, mixed>>
     */
    public function suggestAddresses(string $query, ?array $settings = null): array
    {
        $settings = $settings ?? $this->getSettingsMap();
        $apiKey = trim((string)($settings['dadata_api_key'] ?? ''));
        if ($apiKey === '') {
            throw new \RuntimeException('Для подсказок адреса сохраните DaData API key в настройках доставки.');
        }

        $center = $this->getDadataGeoCenter($settings);
        $radiusMeters = $this->getDadataRadiusMeters($settings);
        $count = $this->getDadataSuggestionCount($settings);

        $suggest = $this->postJsonDetailed(
            'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address',
            [
                'query' => $query,
                'count' => $count,
                'locations_geo' => [[
                    'lat' => $center['lat'],
                    'lon' => $center['lng'],
                    'radius_meters' => $radiusMeters,
                ]],
            ],
            ['Authorization: Token ' . $apiKey],
            2,
            4
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

            $data = is_array($row['data'] ?? null) ? $row['data'] : [];
            $result[] = [
                'value' => $value,
                'unrestricted_value' => $unrestricted,
                'label' => $this->formatAddressSuggestionLabel($data, $value),
                'city' => (string)($data['settlement_with_type'] ?? $data['city_with_type'] ?? $data['settlement'] ?? $data['city'] ?? ''),
                'district' => (string)($data['city_district_with_type'] ?? ''),
                'street' => (string)($data['street_with_type'] ?? $data['street'] ?? ''),
                'house' => (string)($data['house'] ?? ''),
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
     * @param array<string, string>|null $settings
     * @param array{selected_lat?: mixed, selected_lng?: mixed, selected_address?: mixed} $selected
     * @return array<string, mixed>
     */
    public function calculateForAddress(string $address, ?array $settings = null, array $selected = []): array
    {
        $settings = $settings ?? $this->getSettingsMap();
        $storeLat = $this->parseNullableFloat($settings['delivery_store_lat'] ?? '');
        $storeLng = $this->parseNullableFloat($settings['delivery_store_lng'] ?? '');
        if ($storeLat === null || $storeLng === null) {
            throw new \RuntimeException('Укажите широту и долготу магазина в настройках доставки и сохраните раздел.');
        }

        $destination = $this->resolveSelectedOrTypedAddress($address, $settings, $selected);
        $distance = $this->calculateDistanceKm($storeLat, $storeLng, (float)$destination['lat'], (float)$destination['lng'], $settings);
        $pricing = $this->calculatePriceForDistance((float)$distance['km'], $settings);
        $isPreliminary = (string)$distance['source'] !== 'openrouteservice';
        $warning = $isPreliminary
            ? 'Предварительный расчёт по прямой. Точную стоимость подтвердит менеджер.'
            : null;

        return [
            'ok' => true,
            'requested_address' => $address,
            'address' => (string)$destination['address'],
            'normalized_address' => (string)$destination['address'],
            'lat' => $this->formatDecimal((float)$destination['lat']),
            'lng' => $this->formatDecimal((float)$destination['lng']),
            'store' => [
                'lat' => $this->formatDecimal($storeLat),
                'lng' => $this->formatDecimal($storeLng),
                'openrouteservice_coordinate' => [$this->formatDecimal($storeLng), $this->formatDecimal($storeLat)],
            ],
            'destination' => [
                'lat' => $this->formatDecimal((float)$destination['lat']),
                'lng' => $this->formatDecimal((float)$destination['lng']),
                'openrouteservice_coordinate' => [$this->formatDecimal((float)$destination['lng']), $this->formatDecimal((float)$destination['lat'])],
            ],
            'distance_km' => $this->formatDecimal((float)$distance['km']),
            'distance_m' => isset($distance['meters']) && $distance['meters'] !== null ? $this->formatDecimal((float)$distance['meters']) : null,
            'duration_min' => isset($distance['duration_sec']) && $distance['duration_sec'] !== null ? $this->formatDecimal(((float)$distance['duration_sec']) / 60.0) : null,
            'distance_source' => (string)$distance['source'],
            'distance_note' => (string)$distance['note'],
            'price_rub' => (int)$pricing['price_rub'],
            'delivery_fee' => (int)$pricing['price_rub'],
            'pricing_source' => (string)$pricing['source'],
            'delivery_pricing_source' => $isPreliminary ? 'straight_line' : (string)$pricing['source'],
            'zone' => $pricing['zone'],
            'delivery_tariff_zone_id' => is_array($pricing['zone']) && isset($pricing['zone']['id']) ? (int)$pricing['zone']['id'] : null,
            'message' => (string)$pricing['message'],
            'is_preliminary' => $isPreliminary,
            'warning' => $warning,
            'diagnostics' => [
                'dadata' => $destination['diagnostics'] ?? [],
                'openrouteservice' => $distance['diagnostics'] ?? [],
                'pricing' => [
                    'source' => $pricing['source'],
                    'zone' => $pricing['zone'],
                    'message' => $pricing['message'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, string>|null $settings
     * @return array{price_rub: int, source: string, zone: ?array<string, mixed>, message: string}
     */
    public function calculatePriceForDistance(float $distanceKm, ?array $settings = null): array
    {
        $settings = $settings ?? $this->getSettingsMap();
        foreach ($this->getTariffZones() as $zone) {
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
     * @param array<string, string> $settings
     * @param array{selected_lat?: mixed, selected_lng?: mixed, selected_address?: mixed} $selected
     * @return array{lat: float, lng: float, address: string, diagnostics: array<string, mixed>}
     */
    private function resolveSelectedOrTypedAddress(string $address, array $settings, array $selected): array
    {
        $selectedLat = $this->parseNullableFloat($selected['selected_lat'] ?? '');
        $selectedLng = $this->parseNullableFloat($selected['selected_lng'] ?? '');
        $selectedAddress = trim((string)($selected['selected_address'] ?? ''));

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
            return $this->resolveAddress($address, $settings);
        }

        $suggestions = $this->suggestAddresses($address, $settings);
        if (count($suggestions) > 0) {
            throw new \RuntimeException('Выберите адрес из подсказок DaData, чтобы не подставить случайный город/улицу. Найдено вариантов: ' . count($suggestions) . '.');
        }

        return $this->resolveAddress($address, $settings);
    }

    /**
     * @param array<string, string> $settings
     * @return array{lat: float, lng: float, address: string, diagnostics: array<string, mixed>}
     */
    private function resolveAddress(string $address, array $settings): array
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
     * @return array{km: float, meters: ?float, duration_sec: ?float, source: string, note: string, diagnostics: array<string, mixed>}
     */
    private function calculateDistanceKm(float $storeLat, float $storeLng, float $lat, float $lng, array $settings): array
    {
        $apiKey = trim((string)($settings['openrouteservice_api_key'] ?? ''));
        $endpoint = 'https://api.openrouteservice.org/v2/directions/driving-car/json';
        $snapRadiusMeters = isset($settings['openrouteservice_snap_radius_m']) ? (int)$settings['openrouteservice_snap_radius_m'] : 2000;
        $snapRadiusMeters = max(1, min(50000, $snapRadiusMeters));
        $payload = [
            'coordinates' => [[$storeLng, $storeLat], [$lng, $lat]],
            'units' => 'm',
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

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
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

    private function looksLikeCoordinates(string $address): bool
    {
        return (bool)preg_match('/^\s*-?\d+(?:[\.,]\d+)?\s*[,; ]\s*-?\d+(?:[\.,]\d+)?\s*$/u', $address);
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function postJsonDetailed(string $url, array $payload, array $headers = [], int $connectTimeout = 5, int $timeout = 12): array
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
            CURLOPT_CONNECTTIMEOUT => max(1, $connectTimeout),
            CURLOPT_TIMEOUT => max(2, $timeout),
            CURLOPT_NOSIGNAL => true,
            CURLOPT_IPRESOLVE => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 1,
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

    private function formatCoordinate(float $value): string
    {
        return rtrim(rtrim(number_format($value, 7, '.', ''), '0'), '.');
    }

    private function formatDecimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
