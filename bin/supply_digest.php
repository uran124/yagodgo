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

$thresholdDays = max(1, (int)($argv[1] ?? 2));

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}

$sql = 'SELECT pb.id, pb.product_id, pb.purchased_at, pb.status,
               pb.boxes_total, pb.boxes_remaining, pb.boxes_written_off,
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

foreach ($rows as $row) {
    $purchasedAt = new DateTimeImmutable((string)$row['purchased_at']);
    $ageDays = (int)$purchasedAt->diff($now)->format('%a');

    $remaining = (float)$row['boxes_remaining'];
    $total = (float)$row['boxes_total'];
    $writtenOff = (float)$row['boxes_written_off'];

    if ($remaining > 0 && $ageDays >= $thresholdDays) {
        $stale[] = [
            'id' => (int)$row['id'],
            'title' => trim((string)$row['product_name'] . ' ' . (string)$row['variety']),
            'age_days' => $ageDays,
            'remaining' => $remaining,
            'status' => (string)$row['status'],
        ];
    }

    if ($remaining < 0 || $writtenOff > $total) {
        $anomalies[] = [
            'id' => (int)$row['id'],
            'title' => trim((string)$row['product_name'] . ' ' . (string)$row['variety']),
            'remaining' => $remaining,
            'written_off' => $writtenOff,
            'total' => $total,
        ];
    }
}

$result = [
    'generated_at' => $now->format(DATE_ATOM),
    'threshold_days' => $thresholdDays,
    'stale_batches' => $stale,
    'anomalies' => $anomalies,
];

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
