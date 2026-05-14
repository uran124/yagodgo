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
$availableBoxes = (float)($argv[2] ?? 0);
$pricePerBox = (float)($argv[3] ?? 0);
$ttlHours = (int)($argv[4] ?? 4);

if ($productId <= 0 || $availableBoxes <= 0 || $pricePerBox <= 0 || $ttlHours <= 0) {
    fwrite(STDERR, "Usage: php bin/preorder_send_offers.php <product_id> <available_boxes> <price_per_box> [ttl_hours=4]\n");
    exit(1);
}

try {
    $service = new PreorderIntentService($pdo);
    $result = $service->allocateOfferWave($productId, $availableBoxes, $pricePerBox, $ttlHours);
    echo "Offers sent: {$result['offered_count']}; allocated_boxes: {$result['allocated_boxes']}; product_id: {$productId}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Failed to send offers: " . $e->getMessage() . "\n");
    exit(1);
}
