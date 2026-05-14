#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../src/Helpers/SmsRu.php';

use App\Helpers\SmsRu;

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

$config = require __DIR__ . '/../config/sms.php';
$apiId = (string)($config['api_id'] ?? '');
$sms = $apiId !== '' ? new SmsRu($apiId) : null;

$sent = 0;
$failed = 0;
foreach ($rows as $row) {
    $phone = trim((string)($row['phone'] ?? ''));
    if ($phone === '') {
        $failed++;
        continue;
    }
    $text = 'Напоминание: ваш предзаказ скоро истечёт. Подтвердите в течение часа.';
    if ($sms === null) {
        $failed++;
        continue;
    }
    if ($sms->send($phone, $text)) {
        $sent++;
        continue;
    }
    $failed++;
}

echo "Expiring-offer reminders sent: {$sent}; failed: {$failed}\n";
