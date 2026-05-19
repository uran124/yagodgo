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

$dryRun = in_array('--dry-run', $argv, true);

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(2);
}

$sql = 'UPDATE products p
LEFT JOIN (
    SELECT
        product_id,
        COALESCE(SUM(boxes_free), 0) AS free_boxes,
        COALESCE(SUM(boxes_reserved), 0) AS reserved_boxes,
        COALESCE(SUM(boxes_discount), 0) AS discount_boxes,
        COALESCE(SUM(boxes_sold), 0) AS sold_boxes,
        COALESCE(SUM(boxes_written_off), 0) AS written_off_boxes
    FROM purchase_batches
    WHERE status IN ("active", "arrived", "purchased")
    GROUP BY product_id
) agg ON agg.product_id = p.id
SET p.free_stock_boxes = COALESCE(agg.free_boxes, 0),
    p.reserved_stock_boxes = COALESCE(agg.reserved_boxes, 0),
    p.discount_stock_boxes = COALESCE(agg.discount_boxes, 0),
    p.sold_stock_boxes = COALESCE(agg.sold_boxes, 0),
    p.written_off_stock_boxes = COALESCE(agg.written_off_boxes, 0),
    p.stock_status = CASE
        WHEN COALESCE(agg.free_boxes, 0) > 0 THEN "in_stock"
        WHEN COALESCE(agg.reserved_boxes, 0) > 0 THEN "preorder"
        ELSE "sold_out"
    END';

if ($dryRun) {
    $countSql = 'SELECT COUNT(*)
FROM products p
LEFT JOIN (
    SELECT
        product_id,
        COALESCE(SUM(boxes_free), 0) AS free_boxes,
        COALESCE(SUM(boxes_reserved), 0) AS reserved_boxes,
        COALESCE(SUM(boxes_discount), 0) AS discount_boxes,
        COALESCE(SUM(boxes_sold), 0) AS sold_boxes,
        COALESCE(SUM(boxes_written_off), 0) AS written_off_boxes
    FROM purchase_batches
    WHERE status IN ("active", "arrived", "purchased")
    GROUP BY product_id
) agg ON agg.product_id = p.id
WHERE ABS(COALESCE(p.free_stock_boxes, 0) - COALESCE(agg.free_boxes, 0)) > 0.01
   OR ABS(COALESCE(p.reserved_stock_boxes, 0) - COALESCE(agg.reserved_boxes, 0)) > 0.01
   OR ABS(COALESCE(p.discount_stock_boxes, 0) - COALESCE(agg.discount_boxes, 0)) > 0.01
   OR ABS(COALESCE(p.sold_stock_boxes, 0) - COALESCE(agg.sold_boxes, 0)) > 0.01
   OR ABS(COALESCE(p.written_off_stock_boxes, 0) - COALESCE(agg.written_off_boxes, 0)) > 0.01';
    $count = (int)$pdo->query($countSql)->fetchColumn();
    fwrite(STDOUT, json_encode(['dry_run' => true, 'products_to_fix' => $count], JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(0);
}

$affected = $pdo->exec($sql);
fwrite(STDOUT, json_encode(['dry_run' => false, 'affected_rows' => (int)$affected], JSON_UNESCAPED_UNICODE) . PHP_EOL);
