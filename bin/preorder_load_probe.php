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
$sample = (int)($argv[2] ?? 1000);
$boxes = (float)($argv[3] ?? 500);
$price = (float)($argv[4] ?? 1200);

if ($productId <= 0 || $sample <= 0 || $boxes <= 0 || $price <= 0) {
    fwrite(STDERR, "Usage: php bin/preorder_load_probe.php <product_id> [sample_intents=1000] [available_boxes=500] [price=1200]\n");
    exit(1);
}

$start = microtime(true);
$service = new PreorderIntentService($pdo);
$result = $service->allocateOfferWave($productId, $boxes, $price, 4);
$elapsedMs = (microtime(true) - $start) * 1000;

echo "Load probe complete: offered={$result['offered_count']}, allocated={$result['allocated_boxes']}, elapsed_ms=" . round($elapsedMs, 2) . "\n";
