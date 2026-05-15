#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../src/Services/PreorderIntentService.php';

use App\Services\PreorderIntentService;

$pdo = db();
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "DB connection is not available\n");
    exit(1);
}

$productId = (int)($argv[1] ?? 0);
$freedBoxes = (float)($argv[2] ?? 0);
$ttlHours = (int)($argv[3] ?? 4);

if ($productId <= 0 || $freedBoxes <= 0 || $ttlHours <= 0) {
    fwrite(STDERR, "Usage: php bin/preorder_reallocate.php <product_id> <freed_boxes> [ttl_hours=4]\n");
    exit(1);
}

$priceStmt = $pdo->prepare(
    "SELECT offered_price_per_box
     FROM preorder_intents
     WHERE product_id = ?
       AND offered_price_per_box IS NOT NULL
     ORDER BY updated_at DESC, id DESC
     LIMIT 1"
);
$priceStmt->execute([$productId]);
$pricePerBox = (float)($priceStmt->fetchColumn() ?: 0);
if ($pricePerBox <= 0) {
    fwrite(STDERR, "Failed to resolve offer price for product_id={$productId}.\n");
    exit(1);
}

$service = new PreorderIntentService($pdo);
$result = $service->reallocateForProduct($productId, $freedBoxes, $pricePerBox, $ttlHours);

echo "Reallocated offers: {$result['offered_count']}; allocated_boxes: {$result['allocated_boxes']}; product_id: {$productId}\n";
