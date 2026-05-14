#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/app.php';

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

$pdo->beginTransaction();
try {
    $select = $pdo->prepare(
        "SELECT id, requested_boxes
         FROM preorder_intents
         WHERE product_id = ? AND status = 'intent_created'
         ORDER BY created_at ASC, id ASC"
    );
    $select->execute([$productId]);
    $intents = $select->fetchAll(PDO::FETCH_ASSOC);

    $offeredCount = 0;
    $allocated = 0.0;
    $update = $pdo->prepare(
        "UPDATE preorder_intents
         SET status = 'offer_sent',
             offered_price_per_box = ?,
             offer_expires_at = DATE_ADD(NOW(), INTERVAL ? HOUR),
             updated_at = NOW()
         WHERE id = ?"
    );

    foreach ($intents as $intent) {
        $need = (float)$intent['requested_boxes'];
        if ($need <= 0) {
            continue;
        }
        if ($allocated + $need > $availableBoxes) {
            continue;
        }

        $update->execute([$pricePerBox, $ttlHours, (int)$intent['id']]);
        $allocated += $need;
        $offeredCount++;

        if ($allocated >= $availableBoxes) {
            break;
        }
    }

    $pdo->commit();
    echo "Offers sent: {$offeredCount}; allocated_boxes: {$allocated}; product_id: {$productId}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Failed to send offers: " . $e->getMessage() . "\n");
    exit(1);
}
