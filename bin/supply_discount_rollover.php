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

$minAgeDays = 1;
$dryRun = false;
$limit = 50;

for ($i = 1; $i < count($argv); $i++) {
    $arg = (string)$argv[$i];
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }
    if (str_starts_with($arg, '--min-age-days=')) {
        $minAgeDays = (int)substr($arg, strlen('--min-age-days='));
        continue;
    }
    if (str_starts_with($arg, '--limit=')) {
        $limit = (int)substr($arg, strlen('--limit='));
        continue;
    }
}

$minAgeDays = max(1, $minAgeDays);
$limit = max(1, $limit);

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}


$settingsStmt = $pdo->query(
    "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('pricing_discount_stock_rollover_min_age_days', 'pricing_discount_stock_rollover_limit')"
);
$settings = [];
foreach ($settingsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
}

if (isset($settings['pricing_discount_stock_rollover_min_age_days']) && $settings['pricing_discount_stock_rollover_min_age_days'] !== '') {
    $minAgeDays = max(1, (int)$settings['pricing_discount_stock_rollover_min_age_days']);
}
if (isset($settings['pricing_discount_stock_rollover_limit']) && $settings['pricing_discount_stock_rollover_limit'] !== '') {
    $limit = max(1, (int)$settings['pricing_discount_stock_rollover_limit']);
}

$lockStmt = $pdo->query("SELECT GET_LOCK('supply_discount_rollover_lock', 1)");
$lockAcquired = (int)$lockStmt->fetchColumn() === 1;
if (!$lockAcquired) {
    fwrite(STDOUT, json_encode([
        'generated_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        'dry_run' => $dryRun,
        'locked' => false,
        'message' => 'Another rollover process is running; skipped.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(0);
}

$sql = 'SELECT id, product_id, purchased_at, boxes_free, status
        FROM purchase_batches
        WHERE status IN ("active", "arrived", "purchased")
          AND boxes_free > 0
          AND TIMESTAMPDIFF(DAY, purchased_at, NOW()) >= ?
        ORDER BY purchased_at ASC, id ASC
        LIMIT ' . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute([$minAgeDays]);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$processed = [];

foreach ($batches as $batch) {
    $batchId = (int)$batch['id'];
    $productId = (int)$batch['product_id'];
    $boxes = (float)$batch['boxes_free'];
    if ($boxes <= 0) {
        continue;
    }

    if ($dryRun) {
        $processed[] = [
            'batch_id' => $batchId,
            'product_id' => $productId,
            'moved_boxes' => $boxes,
            'mode' => 'dry-run',
        ];
        continue;
    }

    try {
        $pdo->beginTransaction();

        $upd = $pdo->prepare(
            'UPDATE purchase_batches
             SET boxes_free = boxes_free - :boxes,
                 boxes_discount = boxes_discount + :boxes
             WHERE id = :id'
        );
        $upd->execute([
            'boxes' => $boxes,
            'id' => $batchId,
        ]);

        $movement = $pdo->prepare(
            'INSERT INTO stock_movements
                (purchase_batch_id, product_id, movement_type, stock_mode, boxes_delta, comment)
             VALUES
                (:purchase_batch_id, :product_id, :movement_type, :stock_mode, :boxes_delta, :comment)'
        );
        $movement->execute([
            'purchase_batch_id' => $batchId,
            'product_id' => $productId,
            'movement_type' => 'move_to_discount',
            'stock_mode' => 'discount_stock',
            'boxes_delta' => $boxes,
            'comment' => 'Auto rollover to discount stock by age policy',
        ]);

        $sync = $pdo->prepare(
            'UPDATE products
             SET free_stock_boxes = (
                    SELECT COALESCE(SUM(boxes_free), 0) FROM purchase_batches WHERE product_id = :product_id_1 AND status IN ("active", "arrived", "purchased")
                 ),
                 reserved_stock_boxes = (
                    SELECT COALESCE(SUM(boxes_reserved), 0) FROM purchase_batches WHERE product_id = :product_id_2 AND status IN ("active", "arrived", "purchased")
                 ),
                 discount_stock_boxes = (
                    SELECT COALESCE(SUM(boxes_discount), 0) FROM purchase_batches WHERE product_id = :product_id_3 AND status IN ("active", "arrived", "purchased")
                 ),
                 stock_status = CASE
                    WHEN (
                        SELECT COALESCE(SUM(boxes_free), 0) FROM purchase_batches WHERE product_id = :product_id_4 AND status IN ("active", "arrived", "purchased")
                    ) > 0 THEN "in_stock"
                    WHEN (
                        SELECT COALESCE(SUM(boxes_reserved), 0) FROM purchase_batches WHERE product_id = :product_id_5 AND status IN ("active", "arrived", "purchased")
                    ) > 0 THEN "preorder"
                    ELSE "sold_out"
                 END
             WHERE id = :product_id_6'
        );
        $sync->execute([
            'product_id_1' => $productId,
            'product_id_2' => $productId,
            'product_id_3' => $productId,
            'product_id_4' => $productId,
            'product_id_5' => $productId,
            'product_id_6' => $productId,
        ]);

        $pdo->commit();

        $processed[] = [
            'batch_id' => $batchId,
            'product_id' => $productId,
            'moved_boxes' => $boxes,
            'mode' => 'applied',
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$result = [
    'generated_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'dry_run' => $dryRun,
    'min_age_days' => $minAgeDays,
    'limit' => $limit,
    'processed_count' => count($processed),
    'processed' => $processed,
    'locked' => true,
    'settings_applied' => [
        'pricing_discount_stock_rollover_min_age_days' => $settings['pricing_discount_stock_rollover_min_age_days'] ?? null,
        'pricing_discount_stock_rollover_limit' => $settings['pricing_discount_stock_rollover_limit'] ?? null,
    ],
];

$pdo->query("SELECT RELEASE_LOCK('supply_discount_rollover_lock')");

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
