<?php
namespace App\Controllers;

use PDO;
use PDOException;

class PaymentsController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function robokassaResult(): void
    {
        $payload = $this->requestPayload();
        $invoiceId = (int)($payload['InvId'] ?? 0);
        $outSum = trim((string)($payload['OutSum'] ?? ''));
        $signature = trim((string)($payload['SignatureValue'] ?? ''));

        if ($invoiceId <= 0 || $outSum === '' || $signature === '') {
            $this->plainResponse('bad request', 400);
            return;
        }

        if (!$this->verifySignature($payload, 'robokassa_password2')) {
            $this->plainResponse('bad sign', 400);
            return;
        }

        $order = $this->findOrder($invoiceId);
        if (!$order) {
            $this->plainResponse('order not found', 404);
            return;
        }

        if (!$this->amountMatches((float)$outSum, (float)$order['total_amount'])) {
            $this->writePaymentComment($invoiceId, sprintf('Robokassa: сумма уведомления %s не совпала с суммой заказа %s', $outSum, (string)$order['total_amount']));
            $this->plainResponse('bad amount', 400);
            return;
        }

        $this->markOrderPaid($invoiceId, (float)$outSum, $payload);
        $this->plainResponse('OK' . $invoiceId);
    }

    public function robokassaSuccess(): void
    {
        $payload = $this->requestPayload();
        $invoiceId = (int)($payload['InvId'] ?? 0);
        $isValid = $invoiceId > 0 && $this->verifySignature($payload, 'robokassa_password1');

        if ($isValid) {
            $this->markOrderPaymentReturned($invoiceId, 'success');
        }

        $this->renderReturnPage(
            $isValid ? 'Оплата принята' : 'Не удалось проверить оплату',
            $isValid
                ? 'Спасибо! Мы получили возврат от Robokassa. Статус заказа обновится после серверного уведомления ResultURL.'
                : 'Подпись возврата не совпала. Если деньги списались, напишите в поддержку BerryGo.',
            $invoiceId,
            $isValid
        );
    }

    public function robokassaFail(): void
    {
        $payload = $this->requestPayload();
        $invoiceId = (int)($payload['InvId'] ?? 0);
        if ($invoiceId > 0) {
            $this->markOrderPaymentReturned($invoiceId, 'fail');
        }

        $this->renderReturnPage(
            'Оплата не завершена',
            'Платёж отменён или не прошёл. Вы можете вернуться к заказу и попробовать оплатить снова.',
            $invoiceId,
            false
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(): array
    {
        return array_merge($_GET, $_POST);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function verifySignature(array $payload, string $passwordSettingKey): bool
    {
        $password = $this->setting($passwordSettingKey, '');
        if ($password === '') {
            return false;
        }

        $outSum = (string)($payload['OutSum'] ?? '');
        $invoiceId = (string)($payload['InvId'] ?? '');
        $received = strtoupper((string)($payload['SignatureValue'] ?? ''));
        if ($outSum === '' || $invoiceId === '' || $received === '') {
            return false;
        }

        $signatureParts = [$outSum, $invoiceId, $password];
        foreach ($this->sortedShpParams($payload) as $key => $value) {
            $signatureParts[] = $key . '=' . $value;
        }

        $algorithm = strtolower($this->setting('robokassa_hash_algorithm', 'MD5'));
        if (!in_array($algorithm, ['md5', 'sha256', 'sha384', 'sha512'], true)) {
            $algorithm = 'md5';
        }

        $expected = strtoupper(hash($algorithm, implode(':', $signatureParts)));
        return hash_equals($expected, $received);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function sortedShpParams(array $payload): array
    {
        $params = [];
        foreach ($payload as $key => $value) {
            $key = (string)$key;
            if (strpos($key, 'Shp_') === 0 && is_scalar($value)) {
                $params[$key] = (string)$value;
            }
        }
        ksort($params, SORT_STRING);
        return $params;
    }

    private function setting(string $key, string $default): string
    {
        $stmt = $this->pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (string)$value : $default;
    }

    /**
     * @return array<string, mixed>|false
     */
    private function findOrder(int $orderId): array|false
    {
        $stmt = $this->pdo->prepare('SELECT id, total_amount, status FROM orders WHERE id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function amountMatches(float $paidAmount, float $orderAmount): bool
    {
        return abs($paidAmount - $orderAmount) < 0.01;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function markOrderPaid(int $orderId, float $amount, array $payload): void
    {
        $set = [];
        $params = [];

        if ($this->columnExists('orders', 'payment_status')) {
            $set[] = 'payment_status = ?';
            $params[] = 'paid';
        }
        if ($this->columnExists('orders', 'payment_provider')) {
            $set[] = 'payment_provider = ?';
            $params[] = 'robokassa';
        }
        if ($this->columnExists('orders', 'payment_invoice_id')) {
            $set[] = 'payment_invoice_id = ?';
            $params[] = $orderId;
        }
        if ($this->columnExists('orders', 'payment_amount')) {
            $set[] = 'payment_amount = ?';
            $params[] = $amount;
        }
        if ($this->columnExists('orders', 'paid_at')) {
            $set[] = 'paid_at = COALESCE(paid_at, ' . $this->currentTimestampExpression() . ')';
        }
        if ($this->columnExists('orders', 'payment_raw_response')) {
            $set[] = 'payment_raw_response = ?';
            $params[] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        // Order status is no longer promoted by payment callbacks; payment is available only after confirmation.

        if ($set === []) {
            return;
        }

        $params[] = $orderId;
        $stmt = $this->pdo->prepare('UPDATE orders SET ' . implode(', ', $set) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    private function markOrderPaymentReturned(int $orderId, string $returnStatus): void
    {
        if (!$this->columnExists('orders', 'payment_status')) {
            return;
        }

        $paymentStatus = $returnStatus === 'success' ? 'pending' : 'failed';
        $stmt = $this->pdo->prepare(
            "UPDATE orders
                SET payment_status = CASE WHEN payment_status = 'paid' THEN payment_status ELSE ? END,
                    payment_provider = COALESCE(payment_provider, 'robokassa')
              WHERE id = ?"
        );
        $stmt->execute([$paymentStatus, $orderId]);
    }

    private function writePaymentComment(int $orderId, string $message): void
    {
        if (!$this->columnExists('orders', 'comment')) {
            return;
        }

        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? "UPDATE orders SET comment = TRIM(COALESCE(comment, '') || '\n' || ?) WHERE id = ?"
            : "UPDATE orders SET comment = TRIM(CONCAT(COALESCE(comment, ''), '\n', ?)) WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$message, $orderId]);
    }

    private function renderReturnPage(string $title, string $message, int $orderId, bool $success): void
    {
        http_response_code($success ? 200 : 400);
        $orderLink = $orderId > 0 ? '/orders/' . $orderId : '/orders';
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeOrderLink = htmlspecialchars($orderLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $color = $success ? '#16a34a' : '#dc2626';

        echo <<<HTML
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$safeTitle}</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f8fafc; margin:0; padding:40px; color:#0f172a;">
  <main style="max-width:640px; margin:0 auto; background:#fff; border-radius:20px; padding:32px; box-shadow:0 16px 40px rgba(15,23,42,.12);">
    <div style="width:56px; height:56px; border-radius:18px; background:{$color}; color:#fff; display:flex; align-items:center; justify-content:center; font-size:28px;">✓</div>
    <h1>{$safeTitle}</h1>
    <p style="line-height:1.6; color:#475569;">{$safeMessage}</p>
    <a href="{$safeOrderLink}" style="display:inline-block; margin-top:16px; padding:12px 18px; border-radius:999px; background:#C86052; color:#fff; text-decoration:none;">Перейти к заказу</a>
  </main>
</body>
</html>
HTML;
    }

    private function currentTimestampExpression(): string
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        return $driver === 'sqlite' ? 'CURRENT_TIMESTAMP' : 'NOW()';
    }

    private function plainResponse(string $message, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare("PRAGMA table_info({$table})");
                $stmt->execute();
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    if (($row['name'] ?? '') === $column) {
                        return true;
                    }
                }
                return false;
            }

            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }
}
