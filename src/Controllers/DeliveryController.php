<?php
namespace App\Controllers;

use App\Services\DeliveryPricingService;
use PDO;

class DeliveryController
{
    private DeliveryPricingService $deliveryPricing;

    public function __construct(PDO $pdo)
    {
        $this->deliveryPricing = new DeliveryPricingService($pdo);
    }


    public function addressSuggestions(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (function_exists('set_time_limit')) {
            @set_time_limit(8);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $query = trim((string)($_GET['query'] ?? $_POST['query'] ?? ''));
        $queryLength = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);

        if ($queryLength < 3) {
            echo json_encode([
                'ok' => true,
                'suggestions' => [],
                'message' => 'Введите минимум 3 символа.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $settings = $this->deliveryPricing->getSettingsMap();
            $suggestions = $this->deliveryPricing->suggestAddresses($query, $settings);

            echo json_encode([
                'ok' => true,
                'query' => $query,
                'suggestions' => $suggestions,
                'center' => $this->deliveryPricing->getDadataGeoCenter($settings),
                'radius_meters' => $this->deliveryPricing->getDadataRadiusMeters($settings),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'suggestions' => [],
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    public function calculate(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $method = strtolower(trim((string)($_POST['method'] ?? $_POST['delivery_method'] ?? 'delivery')));
        $isPickup = in_array($method, ['pickup', 'self_pickup', 'samovyvoz'], true)
            || (string)($_POST['is_pickup'] ?? '') === '1';

        if ($isPickup) {
            echo json_encode([
                'ok' => true,
                'method' => 'pickup',
                'address' => 'Самовывоз',
                'distance_km' => null,
                'distance_m' => null,
                'price_rub' => 0,
                'delivery_fee' => 0,
                'pricing_source' => 'pickup',
                'delivery_pricing_source' => 'pickup',
                'delivery_tariff_zone_id' => null,
                'is_preliminary' => false,
                'warning' => null,
                'message' => 'Самовывоз — доставка 0 ₽.',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $address = trim((string)($_POST['address'] ?? ''));
        if ($address === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Введите адрес доставки.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $manualDistanceRaw = str_replace(',', '.', trim((string)($_POST['delivery_distance_km_manual'] ?? '')));
        if ($manualDistanceRaw !== '' && is_numeric($manualDistanceRaw)) {
            $manualDistanceKm = max(0.0, (float)$manualDistanceRaw);
            $pricing = $this->deliveryPricing->calculatePriceForDistance($manualDistanceKm);
            echo json_encode([
                'ok' => true,
                'method' => 'delivery',
                'requested_address' => $address,
                'address' => $address,
                'distance_km' => rtrim(rtrim(number_format($manualDistanceKm, 3, '.', ''), '0'), '.'),
                'distance_m' => (string)(int)round($manualDistanceKm * 1000),
                'price_rub' => (int)$pricing['price_rub'],
                'delivery_fee' => (int)$pricing['price_rub'],
                'pricing_source' => 'manual',
                'delivery_pricing_source' => 'manual',
                'zone' => $pricing['zone'],
                'delivery_tariff_zone_id' => is_array($pricing['zone']) && isset($pricing['zone']['id']) ? (int)$pricing['zone']['id'] : null,
                'is_preliminary' => false,
                'warning' => null,
                'message' => 'Стоимость рассчитана по вручную указанному километражу. ' . (string)$pricing['message'],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $result = $this->deliveryPricing->calculateForAddress($address, null, [
                'selected_lat' => $_POST['selected_lat'] ?? $_POST['lat'] ?? '',
                'selected_lng' => $_POST['selected_lng'] ?? $_POST['lng'] ?? '',
                'selected_address' => $_POST['selected_address'] ?? $_POST['normalized_address'] ?? '',
            ]);
            $result['method'] = 'delivery';

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'message' => $e->getMessage(),
                'is_preliminary' => true,
                'warning' => 'Не удалось точно рассчитать доставку. Стоимость проверит менеджер перед подтверждением заказа.',
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
