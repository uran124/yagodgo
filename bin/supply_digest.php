<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);

$dbConfig = require $baseDir . '/config/database.php';
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $dbConfig['host'],
    $dbConfig['dbname'],
    $dbConfig['charset']
);

$thresholdDays = 2;
$sendTelegram = false;
$maxItems = 5;

for ($i = 1; $i < count($argv); $i++) {
    $arg = (string)$argv[$i];

    if ($arg === '--telegram') {
        $sendTelegram = true;
        continue;
    }

    if (str_starts_with($arg, '--threshold=')) {
        $thresholdDays = (int)substr($arg, strlen('--threshold='));
        continue;
    }

    if (str_starts_with($arg, '--max-items=')) {
        $maxItems = (int)substr($arg, strlen('--max-items='));
        continue;
    }

    if (preg_match('/^\d+$/', $arg) === 1) {
        $thresholdDays = (int)$arg;
    }
}

$thresholdDays = max(1, $thresholdDays);
$maxItems = max(1, $maxItems);

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}

$sql = 'SELECT pb.id, pb.product_id, pb.purchased_at, pb.status,
               pb.boxes_total, pb.boxes_remaining, pb.boxes_sold, pb.boxes_written_off,
               p.variety, t.name AS product_name
        FROM purchase_batches pb
        JOIN products p ON p.id = pb.product_id
        JOIN product_types t ON t.id = p.product_type_id
        WHERE pb.status IN ("active", "arrived", "purchased")
        ORDER BY pb.purchased_at ASC';

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$now = new DateTimeImmutable('now');
$stale = [];
$anomalies = [];
$statusCounts = [];

foreach ($rows as $row) {
    $purchasedAt = new DateTimeImmutable((string)$row['purchased_at']);
    $ageDays = (int)$purchasedAt->diff($now)->format('%a');

    $remaining = (float)$row['boxes_remaining'];
    $total = (float)$row['boxes_total'];
    $writtenOff = (float)$row['boxes_written_off'];
    $sold = (float)($row['boxes_sold'] ?? 0);
    $status = (string)$row['status'];
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

    if ($remaining > 0 && $ageDays >= $thresholdDays) {
        $stale[] = [
            'id' => (int)$row['id'],
            'title' => trim((string)$row['product_name'] . ' ' . (string)$row['variety']),
            'age_days' => $ageDays,
            'remaining' => $remaining,
            'status' => (string)$row['status'],
        ];
    }

    $expectedRemaining = $total - $sold - $writtenOff;
    $remainingMismatch = abs($remaining - $expectedRemaining) > 0.01;

    if ($remaining < 0 || $writtenOff > $total || $remainingMismatch) {
        $anomalies[] = [
            'id' => (int)$row['id'],
            'title' => trim((string)$row['product_name'] . ' ' . (string)$row['variety']),
            'remaining' => $remaining,
            'written_off' => $writtenOff,
            'total' => $total,
            'expected_remaining' => $expectedRemaining,
            'remaining_mismatch' => $remainingMismatch,
        ];
    }
}

$result = [
    'generated_at' => $now->format(DATE_ATOM),
    'threshold_days' => $thresholdDays,
    'stale_batches' => $stale,
    'anomalies' => $anomalies,
    'status_counts' => $statusCounts,
];

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);

if ($sendTelegram) {
    require_once $baseDir . '/src/Helpers/TelegramSender.php';

    $tgConfig = require $baseDir . '/config/telegram.php';
    $botToken = (string)($tgConfig['bot_token'] ?? '');
    $chatId = (string)($tgConfig['admin_chat_id'] ?? '');

    if ($botToken === '' || $chatId === '') {
        fwrite(STDERR, "Telegram is not configured: TELEGRAM_BOT_TOKEN and TELEGRAM_ADMIN_CHAT_ID are required.\n");
        exit(2);
    }

    $summaryLines = [
        '📦 Supply digest',
        'Порог: ' . $thresholdDays . ' дн.',
        'Зависшие партии: ' . count($stale),
        'Аномалии: ' . count($anomalies),
        'Статусы: ' . json_encode($statusCounts, JSON_UNESCAPED_UNICODE),
    ];

    if ($stale !== []) {
        $summaryLines[] = '---';
        $summaryLines[] = 'Топ зависших:';
        foreach (array_slice($stale, 0, $maxItems) as $item) {
            $summaryLines[] = sprintf(
                '#%d %s | остаток: %s | %d дн.',
                (int)$item['id'],
                (string)$item['title'],
                (string)$item['remaining'],
                (int)$item['age_days']
            );
        }
    }

    $sender = new \App\Helpers\TelegramSender(
        $botToken,
        (string)($tgConfig['relay_url'] ?? ''),
        (string)($tgConfig['relay_secret'] ?? '')
    );

    $topicId = isset($tgConfig['admin_topic_id']) ? (int)$tgConfig['admin_topic_id'] : null;
    $ok = $sender->send($chatId, implode("\n", $summaryLines), $topicId);
    if (!$ok) {
        fwrite(STDERR, "Telegram send failed.\n");
        exit(3);
    }
}
