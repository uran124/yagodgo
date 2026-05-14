#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/app.php';

$pdo = db();
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "DB connection is not available\n");
    exit(1);
}

$stmt = $pdo->prepare(
    "UPDATE preorder_intents
     SET status = 'expired', updated_at = NOW()
     WHERE status = 'offer_sent'
       AND offer_expires_at IS NOT NULL
       AND offer_expires_at < NOW()"
);
$stmt->execute();
$affected = $stmt->rowCount();

echo "Expired offers updated: {$affected}\n";
