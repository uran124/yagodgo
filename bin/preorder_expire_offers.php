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

$service = new PreorderIntentService($pdo);
$affected = $service->expireOffers();

echo "Expired offers updated: {$affected}\n";
