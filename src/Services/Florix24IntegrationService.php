<?php
namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

class Florix24IntegrationService
{
    public const CODE = 'florix24';
    private const MAIN_STATUSES = ['new', 'confirmed', 'completed', 'cancelled'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<string,string> */
    public function settings(): array
    {
        $defaults = [
            'florix24_enabled' => '0',
            'florix24_base_url' => 'https://florix24.ru',
            'florix24_api_token' => '',
            'florix24_webhook_secret' => '',
            'florix24_send_orders' => '1',
            'florix24_send_statuses' => '1',
            'florix24_receive_statuses' => '1',
            'florix24_auto_retry' => '1',
            'florix24_enabled_at' => '',
        ];

        try {
            if (!$this->tableExists('settings')) {
                return $defaults;
            }
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'florix24_%'");
            $stored = $stmt ? ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: []) : [];
            foreach ($stored as $key => $value) {
                $defaults[(string)$key] = (string)$value;
            }
        } catch (Throwable $e) {
            // Integration must never break order creation when settings are unavailable.
        }

        return $defaults;
    }

    public function isEnabledForOrders(): bool
    {
        $s = $this->settings();
        return $s['florix24_enabled'] === '1'
            && $s['florix24_send_orders'] === '1'
            && trim($s['florix24_api_token']) !== '';
    }

    public function isEnabledForStatuses(): bool
    {
        $s = $this->settings();
        return $s['florix24_enabled'] === '1'
            && $s['florix24_send_statuses'] === '1'
            && trim($s['florix24_api_token']) !== '';
    }

    public function receivesStatuses(): bool
    {
        $s = $this->settings();
        return $s['florix24_enabled'] === '1'
            && $s['florix24_receive_statuses'] === '1'
            && trim($s['florix24_webhook_secret']) !== '';
    }

    /** @param array<int,int> $orderIds */
    public function enqueueNewOrders(array $orderIds, string $source = 'site'): int
    {
        $queued = 0;
        foreach (array_values(array_unique(array_filter(array_map('intval', $orderIds)))) as $orderId) {
            if ($this->enqueueNewOrder($orderId, $source)) {
                $queued++;
            }
        }
        return $queued;
    }

    public function enqueueNewOrder(int $orderId, string $source = 'site'): bool
    {
        if ($orderId <= 0 || !$this->isEnabledForOrders() || !$this->integrationTablesExist()) {
            return false;
        }

        $order = $this->fetchOrderSnapshot($orderId);
        if (!$order || !$this->orderCreatedAfterIntegrationEnabled((string)($order['created_at'] ?? ''))) {
            return false;
        }

        $payload = $this->buildOrderPayload($order, $source);
        $eventId = 'berrygo-order-' . $orderId . '-created';
        $externalOrderId = (string)$orderId;
        $json = $this->encodeJson($payload);

        $this->ensureOrderLink($orderId, $externalOrderId);
        $insert = $this->pdo->prepare(
            "INSERT INTO integration_outbox
                (integration_code, event_id, event_type, entity_type, entity_id, payload_json, status, attempts, next_attempt_at, created_at)
             VALUES (?, ?, 'order.created', 'order', ?, ?, 'pending', 0, " . $this->nowExpression() . ", " . $this->nowExpression() . ")"
        );
        try {
            $insert->execute([self::CODE, $eventId, $orderId, $json]);
            $this->markLink($orderId, 'pending', null);
            return true;
        } catch (Throwable $e) {
            if ($this->isDuplicateException($e)) {
                return false;
            }
            throw $e;
        }
    }

    public function enqueueStatusChange(
        int $orderId,
        ?string $fromStatus,
        string $toStatus,
        ?int $actorId = null,
        ?string $actorName = null,
        ?string $actorRole = null
    ): bool {
        $fromStatus = $fromStatus !== null ? trim($fromStatus) : null;
        $toStatus = trim($toStatus);
        if ($orderId <= 0 || $toStatus === '' || $fromStatus === $toStatus) {
            return false;
        }
        if (!in_array($toStatus, self::MAIN_STATUSES, true) || !$this->isEnabledForStatuses() || !$this->integrationTablesExist()) {
            return false;
        }

        // Only orders created after enabling the integration or already linked are eligible.
        $createdAt = $this->fetchOrderCreatedAt($orderId);
        if (!$this->hasOrderLink($orderId) && ($createdAt === null || !$this->orderCreatedAfterIntegrationEnabled($createdAt))) {
            return false;
        }

        $this->ensureOrderLink($orderId, (string)$orderId);
        $eventId = sprintf('berrygo-order-%d-status-%s', $orderId, bin2hex(random_bytes(10)));
        $payload = [
            'event_id' => $eventId,
            'event' => 'order.status_changed',
            'source' => 'berrygo',
            'external_order_id' => (string)$orderId,
            'external_order_number' => (string)$orderId,
            'status' => $toStatus,
            'previous_status' => $fromStatus,
            'occurred_at' => date(DATE_ATOM),
            'actor' => [
                'external_id' => $actorId !== null && $actorId > 0 ? (string)$actorId : null,
                'name' => trim((string)$actorName) !== '' ? trim((string)$actorName) : null,
                'role' => trim((string)$actorRole) !== '' ? trim((string)$actorRole) : null,
            ],
        ];

        $stmt = $this->pdo->prepare(
            "INSERT INTO integration_outbox
                (integration_code, event_id, event_type, entity_type, entity_id, payload_json, status, attempts, next_attempt_at, created_at)
             VALUES (?, ?, 'order.status_changed', 'order', ?, ?, 'pending', 0, " . $this->nowExpression() . ", " . $this->nowExpression() . ")"
        );
        $stmt->execute([self::CODE, $eventId, $orderId, $this->encodeJson($payload)]);
        $this->markLink($orderId, 'pending', null);
        return true;
    }

    /** @return array{processed:int,sent:int,error:int,conflict:int} */
    public function processQueue(int $limit = 50): array
    {
        $result = ['processed' => 0, 'sent' => 0, 'error' => 0, 'conflict' => 0];
        if (!$this->integrationTablesExist()) {
            return $result;
        }

        $settings = $this->settings();
        if ($settings['florix24_enabled'] !== '1' || trim($settings['florix24_api_token']) === '') {
            return $result;
        }

        $limit = max(1, min(200, $limit));
        $now = $this->nowExpression();
        $processingStale = $this->processingStaleExpression();
        $sql = "SELECT * FROM integration_outbox
                WHERE integration_code = ?
                  AND (
                        status = 'pending'
                        OR (status = 'error' AND next_attempt_at IS NOT NULL AND next_attempt_at <= {$now})
                        OR (status = 'processing' AND (last_attempt_at IS NULL OR last_attempt_at <= {$processingStale}))
                      )
                ORDER BY CASE event_type WHEN 'order.created' THEN 0 ELSE 1 END, id ASC
                LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([self::CODE]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $result['processed']++;
            $id = (int)$row['id'];
            if (!$this->claimQueueRow($id)) {
                continue;
            }

            try {
                $response = $this->sendQueueRow($row, $settings);
                $statusCode = (int)$response['status_code'];
                $body = (string)$response['body'];
                $decoded = is_array($response['json']) ? $response['json'] : [];

                if ($statusCode === 409) {
                    $this->finishQueueConflict($row, $statusCode, $body, $decoded);
                    $result['conflict']++;
                    continue;
                }

                if ($statusCode >= 200 && $statusCode < 300 && (($decoded['success'] ?? true) !== false)) {
                    $this->finishQueueSuccess($row, $statusCode, $body, $decoded);
                    $result['sent']++;
                    continue;
                }

                $message = $this->responseErrorMessage($decoded, $body, $statusCode);
                $this->finishQueueError($row, $statusCode, $body, $message, $settings['florix24_auto_retry'] === '1');
                $result['error']++;
            } catch (Throwable $e) {
                $this->finishQueueError($row, 0, '', $e->getMessage(), $settings['florix24_auto_retry'] === '1');
                $result['error']++;
            }
        }

        return $result;
    }

    /** @return array{ok:bool,message:string,http_code:int,details?:array<string,mixed>} */
    public function testConnection(?string $baseUrl = null, ?string $token = null): array
    {
        $settings = $this->settings();
        $baseUrl = $this->normalizeBaseUrl($baseUrl ?? $settings['florix24_base_url']);
        $token = trim($token ?? $settings['florix24_api_token']);
        if ($token === '') {
            return ['ok' => false, 'message' => 'Укажите API-токен Florix24.', 'http_code' => 0];
        }

        try {
            $response = $this->httpRequest('GET', $baseUrl . '/api/v1/orders/0/status', $token, null);
            $code = (int)$response['status_code'];
            $json = is_array($response['json']) ? $response['json'] : [];
            if (in_array($code, [200, 404], true)) {
                return ['ok' => true, 'message' => 'Подключение установлено. Florix24 принял API-токен.', 'http_code' => $code, 'details' => $json];
            }
            if ($code === 401) {
                return ['ok' => false, 'message' => 'Florix24 отклонил API-токен.', 'http_code' => $code, 'details' => $json];
            }
            if ($code === 403) {
                return ['ok' => false, 'message' => 'Токен действителен, но у него нет права orders.read.', 'http_code' => $code, 'details' => $json];
            }
            return ['ok' => false, 'message' => $this->responseErrorMessage($json, (string)$response['body'], $code), 'http_code' => $code, 'details' => $json];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Florix24 недоступен: ' . $e->getMessage(), 'http_code' => 0];
        }
    }

    public function retryEvent(int $outboxId): bool
    {
        if ($outboxId <= 0 || !$this->tableExists('integration_outbox')) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE integration_outbox
             SET status = 'pending', next_attempt_at = " . $this->nowExpression() . ", last_error = NULL
             WHERE id = ? AND integration_code = ? AND status IN ('error','conflict')"
        );
        $stmt->execute([$outboxId, self::CODE]);
        return $stmt->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function journal(int $limit = 100): array
    {
        if (!$this->tableExists('integration_outbox')) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT id, event_id, event_type, entity_id, status, attempts, response_http_code,
                    last_error, created_at, last_attempt_at, sent_at, 'outgoing' AS direction
             FROM integration_outbox
             WHERE integration_code = ?
             ORDER BY id DESC LIMIT {$limit}"
        );
        $stmt->execute([self::CODE]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($this->tableExists('integration_inbox_events')) {
            $in = $this->pdo->prepare(
                "SELECT id, event_id, event_type, entity_id, status, 1 AS attempts, NULL AS response_http_code,
                        error_message AS last_error, created_at, processed_at AS last_attempt_at,
                        processed_at AS sent_at, 'incoming' AS direction
                 FROM integration_inbox_events WHERE integration_code = ?
                 ORDER BY id DESC LIMIT {$limit}"
            );
            $in->execute([self::CODE]);
            $rows = array_merge($rows, $in->fetchAll(PDO::FETCH_ASSOC) ?: []);
            usort($rows, static fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));
            $rows = array_slice($rows, 0, $limit);
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function orderSyncState(int $orderId): ?array
    {
        if ($orderId <= 0 || !$this->tableExists('florix24_order_links')) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM florix24_order_links WHERE order_id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function webhookUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'berrygo.ru'));
        if ($host === '') {
            $host = 'berrygo.ru';
        }
        return $scheme . '://' . $host . '/api/integrations/florix24/order-status';
    }

    /** @return array<string,mixed>|null */
    private function fetchOrderSnapshot(int $orderId): ?array
    {
        $sql = "SELECT o.*, u.name AS customer_name, u.phone AS customer_phone, u.email AS customer_email,
                       a.street, a.apartment, a.recipient_name, a.recipient_phone,
                       ds.time_from, ds.time_to,
                       creator.name AS creator_name, creator.role AS creator_role
                FROM orders o
                INNER JOIN users u ON u.id = o.user_id
                LEFT JOIN addresses a ON a.id = o.address_id
                LEFT JOIN delivery_slots ds ON ds.id = o.slot_id
                LEFT JOIN users creator ON creator.id = o.created_by_user_id
                WHERE o.id = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return null;
        }
        $items = $this->pdo->prepare(
            "SELECT oi.product_id, oi.quantity, oi.boxes, oi.unit_price, oi.stock_mode, oi.purchase_batch_id,
                    pt.name AS product_type_name, p.variety, p.unit
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             LEFT JOIN product_types pt ON pt.id = p.product_type_id
             WHERE oi.order_id = ? ORDER BY oi.id ASC"
        );
        $items->execute([$orderId]);
        $order['items'] = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $order;
    }

    /** @param array<string,mixed> $order @return array<string,mixed> */
    private function buildOrderPayload(array $order, string $source): array
    {
        $street = trim((string)($order['street'] ?? ''));
        $pickup = $this->isPickupStreet($street);
        $deliveryDate = (string)($order['delivery_date'] ?? date('Y-m-d'));
        $timeFrom = substr((string)($order['time_from'] ?? ''), 0, 5);
        $timeTo = substr((string)($order['time_to'] ?? ''), 0, 5);
        $paymentStatus = $this->mapPaymentStatus((string)($order['payment_status'] ?? 'unpaid'));
        $paidAmount = $paymentStatus === 'paid'
            ? (float)($order['payment_amount'] ?? $order['total_amount'] ?? 0)
            : max(0.0, (float)($order['payment_amount'] ?? 0));

        $items = [];
        foreach ((array)($order['items'] ?? []) as $item) {
            $name = trim(implode(' ', array_filter([
                trim((string)($item['product_type_name'] ?? '')),
                trim((string)($item['variety'] ?? '')),
            ])));
            if ($name === '') {
                $name = 'Товар BerryGo #' . (int)($item['product_id'] ?? 0);
            }
            $quantity = (float)($item['boxes'] ?? 0);
            if ($quantity <= 0) {
                $quantity = (float)($item['quantity'] ?? 1);
            }
            $items[] = [
                'product_id' => 0,
                'external_product_id' => (string)($item['product_id'] ?? ''),
                'sku' => 'BERRYGO-' . (string)($item['product_id'] ?? ''),
                'name' => $name,
                'quantity' => max(0.001, $quantity),
                'unit' => (string)($item['unit'] ?? 'шт.'),
                'price' => max(0.0, (float)($item['unit_price'] ?? 0)),
                'discount_amount' => 0,
                'stock_mode' => (string)($item['stock_mode'] ?? ''),
                'purchase_batch_id' => isset($item['purchase_batch_id']) ? (int)$item['purchase_batch_id'] : null,
            ];
        }

        $discount = max(0.0, (float)($order['discount_applied'] ?? 0));
        $points = max(0.0, (float)($order['points_used'] ?? 0));
        $internalParts = [
            'Источник создания BerryGo: ' . $this->normalizeSource($source),
            'Группа заказов BerryGo: ' . ((int)($order['order_group_id'] ?? 0) ?: 'нет'),
            'Режим склада BerryGo: ' . (string)($order['order_mode'] ?? ''),
        ];
        if ($discount > 0) {
            $internalParts[] = 'Скидка BerryGo: ' . $discount . ' ₽';
        }
        if ($points > 0) {
            $internalParts[] = 'Оплачено клубничками: ' . $points . ' ₽';
        }
        if (trim((string)($order['coupon_code'] ?? '')) !== '') {
            $internalParts[] = 'Промокод: ' . trim((string)$order['coupon_code']);
        }
        if (trim((string)($order['comment'] ?? '')) !== '') {
            $internalParts[] = 'Комментарий заказа: ' . trim((string)$order['comment']);
        }

        $payload = [
            'source' => 'berrygo',
            'sales_channel' => 'site',
            'sales_point' => 'berrygo.ru',
            'external_order_id' => (string)$order['id'],
            'external_order_number' => (string)$order['id'],
            'external_order_group_id' => isset($order['order_group_id']) ? (string)$order['order_group_id'] : null,
            'external_created_at' => $this->toAtom((string)($order['created_at'] ?? '')),
            'source_context' => $this->normalizeSource($source),
            'customer' => [
                'name' => (string)($order['customer_name'] ?? ''),
                'phone' => (string)($order['customer_phone'] ?? ''),
                'email' => (string)($order['customer_email'] ?? ''),
            ],
            'recipient' => [
                'same_as_customer' => trim((string)($order['recipient_phone'] ?? '')) === ''
                    || trim((string)($order['recipient_phone'] ?? '')) === trim((string)($order['customer_phone'] ?? '')),
                'name' => trim((string)($order['recipient_name'] ?? '')) !== '' ? (string)$order['recipient_name'] : (string)($order['customer_name'] ?? ''),
                'phone' => trim((string)($order['recipient_phone'] ?? '')) !== '' ? (string)$order['recipient_phone'] : (string)($order['customer_phone'] ?? ''),
            ],
            'order_type' => $pickup ? 'pickup' : 'delivery',
            'delivery' => [
                'address' => $street,
                'street_house' => $street,
                'apartment' => (string)($order['apartment'] ?? ''),
                'date' => $deliveryDate,
                'time_from' => $timeFrom,
                'time_to' => $timeTo,
                'amount' => max(0.0, (float)($order['delivery_fee'] ?? 0)),
                'distance_km' => max(0.0, (float)($order['delivery_distance_km'] ?? 0)),
                'comment' => (string)($order['delivery_comment'] ?? ''),
            ],
            'pickup' => [
                'date' => $deliveryDate,
                'time' => $timeFrom,
                'address' => $street,
            ],
            'items' => $items,
            'subtotal_amount' => max(0.0, (float)($order['total_amount'] ?? 0) + $discount + $points - (float)($order['delivery_fee'] ?? 0)),
            'discount_amount' => $discount + $points,
            'delivery_amount' => max(0.0, (float)($order['delivery_fee'] ?? 0)),
            'total_amount' => max(0.0, (float)($order['total_amount'] ?? 0)),
            'discounts' => [
                ['type' => 'order_discount', 'amount' => $discount],
                ['type' => 'loyalty_points', 'amount' => $points],
            ],
            'payment' => [
                'status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'comment' => trim('BerryGo: ' . (string)($order['payment_method'] ?? '') . ' / ' . (string)($order['payment_provider'] ?? '')),
            ],
            'comments' => [
                'customer' => (string)($order['comment'] ?? ''),
                'courier' => (string)($order['delivery_comment'] ?? ''),
                'internal' => implode("\n", $internalParts),
            ],
            'external_actor' => [
                'id' => isset($order['created_by_user_id']) && (int)$order['created_by_user_id'] > 0 ? (string)$order['created_by_user_id'] : null,
                'name' => (string)($order['creator_name'] ?? ''),
                'role' => (string)($order['creator_role'] ?? ''),
            ],
            // Florix24 creates API orders in its system status "new" regardless of BerryGo reserved/new.
            'status' => 'new',
        ];

        return $payload;
    }

    /** @param array<string,mixed> $row @param array<string,string> $settings @return array{status_code:int,body:string,json:array<string,mixed>|null} */
    private function sendQueueRow(array $row, array $settings): array
    {
        $payload = json_decode((string)$row['payload_json'], true);
        if (!is_array($payload)) {
            throw new RuntimeException('Повреждён JSON события очереди.');
        }
        $baseUrl = $this->normalizeBaseUrl($settings['florix24_base_url']);
        $token = trim($settings['florix24_api_token']);
        if ((string)$row['event_type'] === 'order.created') {
            return $this->httpRequest('POST', $baseUrl . '/api/v1/orders', $token, $payload);
        }
        if ((string)$row['event_type'] === 'order.status_changed') {
            $externalId = rawurlencode((string)($payload['external_order_id'] ?? $row['entity_id'] ?? ''));
            return $this->httpRequest('PATCH', $baseUrl . '/api/v1/orders/external/' . $externalId . '/status', $token, $payload);
        }
        throw new RuntimeException('Неизвестный тип события: ' . (string)$row['event_type']);
    }

    /** @return array{status_code:int,body:string,json:array<string,mixed>|null} */
    private function httpRequest(string $method, string $url, string $token, ?array $payload): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('На сервере PHP не включено расширение cURL.');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Не удалось инициализировать cURL.');
        }
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ];
        $body = null;
        if ($payload !== null) {
            $body = $this->encodeJson($payload);
            $headers[] = 'Content-Type: application/json; charset=UTF-8';
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'BerryGo-Florix24/1.0',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($responseBody === false) {
            throw new RuntimeException($error !== '' ? $error : 'Ошибка HTTP-запроса.');
        }
        $decoded = json_decode((string)$responseBody, true);
        return [
            'status_code' => $statusCode,
            'body' => (string)$responseBody,
            'json' => is_array($decoded) ? $decoded : null,
        ];
    }

    private function claimQueueRow(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE integration_outbox SET status = 'processing', attempts = attempts + 1, last_attempt_at = " . $this->nowExpression() . "
             WHERE id = ? AND integration_code = ? AND status IN ('pending','error','processing')"
        );
        $stmt->execute([$id, self::CODE]);
        return $stmt->rowCount() > 0;
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $decoded */
    private function finishQueueSuccess(array $row, int $statusCode, string $body, array $decoded): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE integration_outbox
             SET status = 'sent', response_http_code = ?, response_body = ?, last_error = NULL,
                 next_attempt_at = NULL, sent_at = " . $this->nowExpression() . " WHERE id = ?"
        );
        $stmt->execute([$statusCode, $this->truncate($body, 65000), (int)$row['id']]);

        $orderId = (int)($row['entity_id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }
        if ((string)$row['event_type'] === 'order.created') {
            $remote = is_array($decoded['order'] ?? null) ? $decoded['order'] : [];
            $update = $this->pdo->prepare(
                "UPDATE florix24_order_links
                 SET florix_order_id = ?, florix_order_number = ?, sync_status = 'sent',
                     last_synced_at = " . $this->nowExpression() . ", last_error = NULL
                 WHERE order_id = ?"
            );
            $update->execute([
                isset($remote['id']) ? (int)$remote['id'] : null,
                isset($remote['public_number']) ? (string)$remote['public_number'] : null,
                $orderId,
            ]);
        } else {
            $this->markLink($orderId, 'sent', null);
        }
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $decoded */
    private function finishQueueConflict(array $row, int $statusCode, string $body, array $decoded): void
    {
        $message = $this->responseErrorMessage($decoded, $body, $statusCode);
        $stmt = $this->pdo->prepare(
            "UPDATE integration_outbox SET status = 'conflict', response_http_code = ?, response_body = ?,
                    last_error = ?, next_attempt_at = NULL WHERE id = ?"
        );
        $stmt->execute([$statusCode, $this->truncate($body, 65000), $message, (int)$row['id']]);
        $this->markLink((int)($row['entity_id'] ?? 0), 'conflict', $message);
    }

    /** @param array<string,mixed> $row */
    private function finishQueueError(array $row, int $statusCode, string $body, string $message, bool $autoRetry): void
    {
        $attempts = (int)($row['attempts'] ?? 0) + 1;
        $next = $autoRetry ? $this->retryAt($attempts) : null;
        $stmt = $this->pdo->prepare(
            "UPDATE integration_outbox SET status = 'error', response_http_code = ?, response_body = ?,
                    last_error = ?, next_attempt_at = ? WHERE id = ?"
        );
        $stmt->execute([
            $statusCode > 0 ? $statusCode : null,
            $body !== '' ? $this->truncate($body, 65000) : null,
            $this->truncate($message, 65000),
            $next,
            (int)$row['id'],
        ]);
        $this->markLink((int)($row['entity_id'] ?? 0), 'error', $message);
    }

    private function retryAt(int $attempt): ?string
    {
        $minutes = [1, 5, 15, 60, 180, 720];
        if ($attempt > count($minutes)) {
            return null;
        }
        $delay = $minutes[max(0, $attempt - 1)];
        return date('Y-m-d H:i:s', time() + ($delay * 60));
    }

    private function ensureOrderLink(int $orderId, string $externalOrderId): void
    {
        if ($this->driver() === 'sqlite') {
            $stmt = $this->pdo->prepare(
                "INSERT OR IGNORE INTO florix24_order_links
                    (order_id, external_order_id, sync_status, created_at, updated_at)
                 VALUES (?, ?, 'pending', datetime('now'), datetime('now'))"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO florix24_order_links (order_id, external_order_id, sync_status, created_at, updated_at)
                 VALUES (?, ?, 'pending', NOW(), NOW())
                 ON DUPLICATE KEY UPDATE external_order_id = VALUES(external_order_id), updated_at = NOW()"
            );
        }
        $stmt->execute([$orderId, $externalOrderId]);
    }

    private function markLink(int $orderId, string $status, ?string $error): void
    {
        if ($orderId <= 0 || !$this->tableExists('florix24_order_links')) {
            return;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE florix24_order_links SET sync_status = ?, last_error = ?, updated_at = " . $this->nowExpression() . " WHERE order_id = ?"
        );
        $stmt->execute([$status, $error !== null ? $this->truncate($error, 65000) : null, $orderId]);
    }

    private function hasOrderLink(int $orderId): bool
    {
        if (!$this->tableExists('florix24_order_links')) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM florix24_order_links WHERE order_id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        return (bool)$stmt->fetchColumn();
    }

    private function fetchOrderCreatedAt(int $orderId): ?string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT created_at FROM orders WHERE id = ? LIMIT 1');
            $stmt->execute([$orderId]);
            $value = $stmt->fetchColumn();
            return $value !== false ? (string)$value : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function orderCreatedAfterIntegrationEnabled(string $createdAt): bool
    {
        $enabledAt = trim($this->settings()['florix24_enabled_at'] ?? '');
        if ($enabledAt === '') {
            return false;
        }
        $createdTs = strtotime($createdAt);
        $enabledTs = strtotime($enabledAt);
        return $createdTs !== false && $enabledTs !== false && $createdTs >= $enabledTs;
    }

    private function integrationTablesExist(): bool
    {
        return $this->tableExists('integration_outbox') && $this->tableExists('florix24_order_links');
    }

    private function tableExists(string $table): bool
    {
        try {
            if ($this->driver() === 'sqlite') {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$table]);
                return (bool)$stmt->fetchColumn();
            }
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function driver(): string
    {
        return (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function nowExpression(): string
    {
        return $this->driver() === 'sqlite' ? "datetime('now')" : 'NOW()';
    }

    private function processingStaleExpression(): string
    {
        return $this->driver() === 'sqlite'
            ? "datetime('now', '-10 minutes')"
            : 'DATE_SUB(NOW(), INTERVAL 10 MINUTE)';
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $baseUrl)) {
            throw new RuntimeException('Некорректный адрес Florix24.');
        }
        return $baseUrl;
    }

    private function mapPaymentStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'paid', 'success', 'completed' => 'paid',
            'pending', 'waiting', 'processing' => 'waiting',
            'refund', 'refunded' => 'refund',
            'failed', 'error', 'cancelled' => 'payment_error',
            default => 'not_paid',
        };
    }

    private function normalizeSource(string $source): string
    {
        $source = strtolower(trim($source));
        return in_array($source, ['site', 'admin', 'telegram', 'client'], true) ? $source : 'site';
    }

    private function isPickupStreet(string $street): bool
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower($street, 'UTF-8') : strtolower($street);
        return str_starts_with(trim($value), 'самовывоз') || trim($value) === 'berrygo';
    }

    private function responseErrorMessage(array $json, string $body, int $statusCode): string
    {
        $message = $json['error']['message'] ?? $json['message'] ?? $json['error'] ?? null;
        if (is_scalar($message) && trim((string)$message) !== '') {
            return 'HTTP ' . $statusCode . ': ' . trim((string)$message);
        }
        $body = trim($body);
        return 'HTTP ' . $statusCode . ($body !== '' ? ': ' . $this->truncate($body, 500) : '');
    }

    private function toAtom(string $value): string
    {
        $ts = strtotime($value);
        return $ts !== false ? date(DATE_ATOM, $ts) : date(DATE_ATOM);
    }

    /** @param array<string,mixed> $value */
    private function encodeJson(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return $json;
    }

    private function truncate(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length, 'UTF-8');
        }
        return substr($value, 0, $length);
    }

    private function isDuplicateException(Throwable $e): bool
    {
        return str_contains($e->getMessage(), '1062')
            || str_contains(strtolower($e->getMessage()), 'unique constraint')
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
