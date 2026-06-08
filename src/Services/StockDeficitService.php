<?php
namespace App\Services;

use App\Helpers\TelegramSender;
use PDO;
use Throwable;

class StockDeficitService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDeficitRows(): array
    {
        $sql = "SELECT
                    d.product_id,
                    d.product_name,
                    d.variety,
                    SUM(d.instant_deficit_boxes) AS instant_deficit_boxes,
                    SUM(d.preorder_deficit_boxes) AS preorder_deficit_boxes,
                    SUM(d.instant_deficit_boxes + d.preorder_deficit_boxes) AS total_deficit_boxes,
                    COUNT(DISTINCT d.purchase_batch_id) AS batches_count,
                    MIN(d.purchased_at) AS nearest_purchase_at
                FROM (
                    SELECT
                        p.id AS product_id,
                        t.name AS product_name,
                        p.variety,
                        pb.id AS purchase_batch_id,
                        pb.purchased_at,
                        CASE WHEN COALESCE(pb.boxes_free, 0) < 0 THEN ABS(pb.boxes_free) ELSE 0 END AS instant_deficit_boxes,
                        0 AS preorder_deficit_boxes
                    FROM purchase_batches pb
                    JOIN products p ON p.id = pb.product_id
                    JOIN product_types t ON t.id = p.product_type_id
                    WHERE pb.status IN ('active', 'purchased', 'arrived')
                      AND COALESCE(pb.boxes_free, 0) < 0

                    UNION ALL

                    SELECT
                        p.id AS product_id,
                        t.name AS product_name,
                        p.variety,
                        pb.id AS purchase_batch_id,
                        pb.purchased_at,
                        0 AS instant_deficit_boxes,
                        CASE
                            WHEN COALESCE(pb.boxes_total, 0) > 0
                                THEN CASE WHEN COALESCE(pb.boxes_reserved, 0) > COALESCE(pb.boxes_total, 0)
                                          THEN COALESCE(pb.boxes_reserved, 0) - COALESCE(pb.boxes_total, 0)
                                          ELSE 0 END
                            ELSE COALESCE(pb.boxes_reserved, 0)
                        END AS preorder_deficit_boxes
                    FROM purchase_batches pb
                    JOIN products p ON p.id = pb.product_id
                    JOIN product_types t ON t.id = p.product_type_id
                    WHERE pb.status = 'planned'
                      AND COALESCE(pb.boxes_reserved, 0) > 0
                ) d
                GROUP BY d.product_id, d.product_name, d.variety
                HAVING SUM(d.instant_deficit_boxes + d.preorder_deficit_boxes) > 0
                ORDER BY total_deficit_boxes DESC, d.product_name, d.variety";

        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['product_id'] = (int)($row['product_id'] ?? 0);
            $row['instant_deficit_boxes'] = (float)($row['instant_deficit_boxes'] ?? 0);
            $row['preorder_deficit_boxes'] = (float)($row['preorder_deficit_boxes'] ?? 0);
            $row['total_deficit_boxes'] = (float)($row['total_deficit_boxes'] ?? 0);
            $row['batches_count'] = (int)($row['batches_count'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    /** @return array{total_deficit_boxes:float,products_count:int,rows:array<int,array<string,mixed>>,signature:string} */
    public function getSummary(): array
    {
        $rows = $this->getDeficitRows();
        $total = 0.0;
        $signaturePayload = [];
        foreach ($rows as $row) {
            $deficit = round((float)($row['total_deficit_boxes'] ?? 0), 3);
            $total += $deficit;
            $signaturePayload[] = [
                'product_id' => (int)($row['product_id'] ?? 0),
                'deficit' => $deficit,
            ];
        }

        return [
            'total_deficit_boxes' => $total,
            'products_count' => count($rows),
            'rows' => $rows,
            'signature' => sha1(json_encode($signaturePayload, JSON_UNESCAPED_UNICODE) ?: ''),
        ];
    }

    public function notifyAdminsIfChanged(string $reason = ''): bool
    {
        $summary = $this->getSummary();
        if ($summary['total_deficit_boxes'] <= 0 || empty($summary['rows'])) {
            $this->saveLastSignature('');
            return false;
        }

        $signature = (string)$summary['signature'];
        if ($signature === $this->getLastSignature()) {
            return false;
        }

        $configPath = dirname(__DIR__, 2) . '/config/telegram.php';
        $config = is_file($configPath) ? (require $configPath) : [];
        $botToken = trim((string)($config['bot_token'] ?? ''));
        $chatIds = $this->normalizeChatIds($config['admin_chat_id'] ?? '');
        if ($botToken === '' || !$chatIds) {
            return false;
        }

        $message = $this->buildTelegramMessage($summary, $reason);
        $sender = new TelegramSender(
            $botToken,
            $config['relay_url'] ?? null,
            $config['relay_secret'] ?? null
        );
        $topicId = isset($config['admin_topic_id']) && $config['admin_topic_id'] !== null
            ? (int)$config['admin_topic_id']
            : null;

        $sent = false;
        foreach ($chatIds as $chatId) {
            $sent = $sender->send($chatId, $message, $topicId) || $sent;
        }

        if ($sent) {
            $this->saveLastSignature($signature);
        }

        return $sent;
    }

    /** @param mixed $chatIdConfig @return array<int, int|string> */
    private function normalizeChatIds(mixed $chatIdConfig): array
    {
        if (is_array($chatIdConfig)) {
            return array_values(array_filter($chatIdConfig, static fn($id): bool => trim((string)$id) !== ''));
        }
        $raw = trim((string)$chatIdConfig);
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn(string $id): bool => $id !== ''));
    }

    /** @param array{total_deficit_boxes:float,products_count:int,rows:array<int,array<string,mixed>>,signature:string} $summary */
    private function buildTelegramMessage(array $summary, string $reason): string
    {
        $lines = [
            '⚠️ Дефицит товара: -' . $this->formatBoxes((float)$summary['total_deficit_boxes']) . ' ящ.',
        ];
        if ($reason !== '') {
            $lines[] = 'Причина: ' . $reason;
        }
        $lines[] = 'Раздел: /admin/purchases#stock-deficit';
        $lines[] = '';

        foreach (array_slice($summary['rows'], 0, 8) as $row) {
            $name = trim((string)($row['product_name'] ?? '') . ' ' . (string)($row['variety'] ?? ''));
            $parts = [];
            if ((float)($row['instant_deficit_boxes'] ?? 0) > 0) {
                $parts[] = 'в наличии -' . $this->formatBoxes((float)$row['instant_deficit_boxes']);
            }
            if ((float)($row['preorder_deficit_boxes'] ?? 0) > 0) {
                $parts[] = 'бронь -' . $this->formatBoxes((float)$row['preorder_deficit_boxes']);
            }
            $lines[] = '• ' . $name . ': -' . $this->formatBoxes((float)$row['total_deficit_boxes']) . ' ящ. (' . implode(', ', $parts) . ')';
        }

        return implode(PHP_EOL, $lines);
    }

    public function formatBoxes(float $value): string
    {
        $rounded = round($value, 1);
        if (abs($rounded - round($rounded)) < 0.0001) {
            return (string)(int)round($rounded);
        }
        return rtrim(rtrim(number_format($rounded, 1, '.', ' '), '0'), '.');
    }

    private function getLastSignature(): string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'stock_deficit_last_notification_signature' LIMIT 1");
            $stmt->execute();
            return (string)($stmt->fetchColumn() ?: '');
        } catch (Throwable $e) {
            return '';
        }
    }

    private function saveLastSignature(string $signature): void
    {
        try {
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $this->pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES ('stock_deficit_last_notification_signature', ?, CURRENT_TIMESTAMP)")
                    ->execute([$signature]);
                return;
            }
            $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('stock_deficit_last_notification_signature', ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()")
                ->execute([$signature]);
        } catch (Throwable $e) {
            // Дефицит должен отображаться даже если таблица настроек недоступна.
        }
    }
}
