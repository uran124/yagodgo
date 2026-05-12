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

$checks = [];
$failed = false;

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(2);
}

$requiredTables = [
    'purchase_batches',
    'stock_movements',
    'purchase_batch_photos',
];

foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    $ok = (bool)$stmt->fetchColumn();
    $checks[] = ['check' => 'table_exists:' . $table, 'ok' => $ok];
    if (!$ok) {
        $failed = true;
    }
}

$requiredSettings = [
    'pricing_preorder_margin_percent',
    'pricing_instant_margin_percent',
    'pricing_discount_stock_markup_fixed',
    'pricing_rounding_step',
];

foreach ($requiredSettings as $key) {
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    $ok = $value !== false && $value !== null && $value !== '';
    $checks[] = ['check' => 'setting_exists:' . $key, 'ok' => $ok, 'value' => $ok ? (string)$value : null];
    if (!$ok) {
        $failed = true;
    }
}

$batchCount = (int)$pdo->query('SELECT COUNT(*) FROM purchase_batches')->fetchColumn();
$checks[] = ['check' => 'purchase_batches_count', 'ok' => true, 'value' => $batchCount];

$anomalySql = 'SELECT COUNT(*)
               FROM purchase_batches
               WHERE boxes_remaining < 0
                  OR boxes_written_off < 0
                  OR ABS(boxes_remaining - (boxes_total - boxes_sold - boxes_written_off)) > 0.01';
$anomalyCount = (int)$pdo->query($anomalySql)->fetchColumn();
$anomalyOk = $anomalyCount === 0;
$checks[] = ['check' => 'batch_invariant_anomalies', 'ok' => $anomalyOk, 'value' => $anomalyCount];
if (!$anomalyOk) {
    $failed = true;
}

$result = [
    'generated_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'ok' => !$failed,
    'checks' => $checks,
];

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($failed ? 1 : 0);
