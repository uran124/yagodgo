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
