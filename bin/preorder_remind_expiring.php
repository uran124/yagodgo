#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/app.php';

$pdo = db();
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "DB connection is not available\n");
    exit(1);
}

$sql = "SELECT pi.id, pi.user_id, pi.product_id, pi.offer_expires_at, u.phone
        FROM preorder_intents pi
        JOIN users u ON u.id = pi.user_id
        WHERE pi.status = 'offer_sent'
          AND pi.offer_expires_at IS NOT NULL
          AND pi.offer_expires_at > NOW()
          AND pi.offer_expires_at <= DATE_ADD(NOW(), INTERVAL 60 MINUTE)";

$stmt = $pdo->query($sql);
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$sent = 0;
foreach ($rows as $row) {
    // Placeholder: здесь будет реальная отправка SMS/Telegram.
    // На этапе внедрения считаем запись кандидатом на напоминание.
    $sent++;
}

echo "Expiring-offer reminders candidates: {$sent}\n";
