<?php
namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

class Florix24InboundStatusService
{
    private PDO $pdo;
    private Florix24IntegrationService $integration;

    public function __construct(PDO $pdo, ?Florix24IntegrationService $integration = null)
    {
        $this->pdo = $pdo;
        $this->integration = $integration ?? new Florix24IntegrationService($pdo);
    }

    /** @param array<string,string> $headers @return array<string,mixed> */
    public function handle(string $rawBody, array $headers): array
    {
        if (!$this->integration->receivesStatuses()) {
            throw new Florix24WebhookException('integration_disabled', 'Получение статусов Florix24 отключено.', 503);
        }
        if (!$this->tableExists('integration_inbox_events')) {
            throw new Florix24WebhookException('migration_required', 'Не выполнена миграция интеграции Florix24.', 503);
        }

        $settings = $this->integration->settings();
        $timestamp = trim((string)($headers['x-florix-timestamp'] ?? ''));
        $signature = trim((string)($headers['x-florix-signature'] ?? ''));
        $this->verifySignature($rawBody, $timestamp, $signature, (string)$settings['florix24_webhook_secret']);

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            throw new Florix24WebhookException('invalid_json', 'Некорректный JSON webhook.', 422);
        }
        $eventId = trim((string)($data['event_id'] ?? $headers['x-florix-event-id'] ?? ''));
        $externalOrderId = trim((string)($data['external_order_id'] ?? ''));
        $status = trim((string)($data['system_status'] ?? $data['status'] ?? ''));
        if ($eventId === '' || $externalOrderId === '' || $status === '') {
            throw new Florix24WebhookException('validation_error', 'Нужны event_id, external_order_id и status.', 422);
        }
        if (!in_array($status, ['new', 'confirmed', 'completed', 'cancelled'], true)) {
            throw new Florix24WebhookException('status_not_mapped', 'Статус Florix24 не синхронизируется с BerryGo.', 422);
        }

        $existing = $this->findInboxEvent($eventId);
        if ($existing && (string)($existing['status'] ?? '') === 'processed') {
            return [
                'success' => true,
                'duplicate' => true,
                'event_id' => $eventId,
                'order_id' => isset($existing['entity_id']) ? (int)$existing['entity_id'] : null,
            ];
        }
        if ($existing && (string)($existing['status'] ?? '') === 'conflict') {
            throw new Florix24WebhookException(
                'status_conflict',
                (string)($existing['error_message'] ?? 'Конфликт статусов уже зафиксирован.'),
                409
            );
        }

        $orderId = $existing && (int)($existing['entity_id'] ?? 0) > 0
            ? (int)$existing['entity_id']
            : $this->resolveOrderId($externalOrderId);
        if ($orderId <= 0) {
            throw new Florix24WebhookException('order_not_found', 'Заказ BerryGo не найден.', 404);
        }

        if (!$existing) {
            $this->insertInbox($eventId, $orderId, $rawBody);
        }
        try {
            $actor = is_array($data['actor'] ?? null) ? $data['actor'] : [];
            $result = (new OrderStatusApplicationService($this->pdo))->applyFromIntegration(
                $orderId,
                $status,
                isset($actor['name']) ? (string)$actor['name'] : null,
                isset($actor['id']) ? (string)$actor['id'] : null
            );
            $this->finishInbox($eventId, 'processed', null);
            $this->markOrderLink($orderId, 'sent', null);
            return [
                'success' => true,
                'duplicate' => false,
                'event_id' => $eventId,
                'order_id' => $orderId,
                'status' => $result['current_main_status'],
                'local_status' => $result['current_status'],
                'changed' => $result['changed'],
            ];
        } catch (OrderStatusConflictException $e) {
            $this->finishInbox($eventId, 'conflict', $e->getMessage());
            $this->markOrderLink($orderId, 'conflict', $e->getMessage());
            throw new Florix24WebhookException('status_conflict', $e->getMessage(), 409);
        } catch (Throwable $e) {
            $this->finishInbox($eventId, 'error', $e->getMessage());
            $this->markOrderLink($orderId, 'error', $e->getMessage());
            throw new Florix24WebhookException('apply_failed', $e->getMessage(), 500);
        }
    }

    private function verifySignature(string $raw, string $timestamp, string $signature, string $secret): void
    {
        if ($timestamp === '' || $signature === '' || trim($secret) === '') {
            throw new Florix24WebhookException('signature_missing', 'Отсутствует подпись webhook.', 401);
        }
        $ts = ctype_digit($timestamp) ? (int)$timestamp : (int)(strtotime($timestamp) ?: 0);
        if ($ts <= 0 || abs(time() - $ts) > 300) {
            throw new Florix24WebhookException('timestamp_invalid', 'Webhook просрочен или имеет неверное время.', 401);
        }
        $provided = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;
        $expected = hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
        if (!hash_equals($expected, strtolower(trim($provided)))) {
            throw new Florix24WebhookException('signature_invalid', 'Неверная подпись webhook.', 401);
        }
    }

    /** @return array<string,mixed>|null */
    private function findInboxEvent(string $eventId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM integration_inbox_events WHERE integration_code = ? AND event_id = ? LIMIT 1');
        $stmt->execute([Florix24IntegrationService::CODE, $eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function resolveOrderId(string $externalOrderId): int
    {
        if ($this->tableExists('florix24_order_links')) {
            $stmt = $this->pdo->prepare('SELECT order_id FROM florix24_order_links WHERE external_order_id = ? LIMIT 1');
            $stmt->execute([$externalOrderId]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }
        if (ctype_digit($externalOrderId)) {
            $stmt = $this->pdo->prepare('SELECT id FROM orders WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$externalOrderId]);
            return (int)($stmt->fetchColumn() ?: 0);
        }
        return 0;
    }

    private function insertInbox(string $eventId, int $orderId, string $raw): void
    {
        $now = $this->nowExpression();
        $stmt = $this->pdo->prepare(
            "INSERT INTO integration_inbox_events
                (integration_code, event_id, event_type, entity_type, entity_id, payload_json, status, created_at)
             VALUES (?, ?, 'order.status_changed', 'order', ?, ?, 'received', {$now})"
        );
        try {
            $stmt->execute([Florix24IntegrationService::CODE, $eventId, $orderId, $raw]);
        } catch (Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'duplicate') || str_contains(strtolower($e->getMessage()), 'unique')) {
                return;
            }
            throw $e;
        }
    }

    private function finishInbox(string $eventId, string $status, ?string $error): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE integration_inbox_events SET status = ?, error_message = ?, processed_at = " . $this->nowExpression() . "
             WHERE integration_code = ? AND event_id = ?"
        );
        $stmt->execute([$status, $error, Florix24IntegrationService::CODE, $eventId]);
    }

    private function markOrderLink(int $orderId, string $status, ?string $error): void
    {
        if (!$this->tableExists('florix24_order_links')) {
            return;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE florix24_order_links SET sync_status = ?, last_error = ?, last_synced_at = " . $this->nowExpression() . ", updated_at = " . $this->nowExpression() . " WHERE order_id = ?"
        );
        $stmt->execute([$status, $error, $orderId]);
    }

    private function tableExists(string $table): bool
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function nowExpression(): string
    {
        return (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? "datetime('now')" : 'NOW()';
    }
}
