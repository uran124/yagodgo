#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/app.php';

$pdo = db();
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "DB connection is not available\n");
    exit(1);
}

$from = (string)($argv[1] ?? date('Y-m-d', strtotime('-7 days')));
$to = (string)($argv[2] ?? date('Y-m-d'));

$countsStmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS c
     FROM preorder_intents
     WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY status"
);
$countsStmt->execute([$from, $to]);
$rows = $countsStmt->fetchAll(PDO::FETCH_ASSOC);

$counts = [
    'intent_created' => 0,
    'offer_sent' => 0,
    'confirmed' => 0,
    'declined' => 0,
    'expired' => 0,
    'checkout_completed' => 0,
];
foreach ($rows as $r) {
    $s = (string)$r['status'];
    if (array_key_exists($s, $counts)) {
        $counts[$s] = (int)$r['c'];
    }
}

$intentToOffer = $counts['intent_created'] > 0 ? round(($counts['offer_sent'] / $counts['intent_created']) * 100, 2) : 0.0;
$offerToConfirmed = $counts['offer_sent'] > 0 ? round(($counts['confirmed'] / $counts['offer_sent']) * 100, 2) : 0.0;
$confirmedToCheckout = $counts['confirmed'] > 0 ? round(($counts['checkout_completed'] / $counts['confirmed']) * 100, 2) : 0.0;

echo "preorder_metrics_report from={$from} to={$to}\n";
echo "intent_created={$counts['intent_created']}\n";
echo "offer_sent={$counts['offer_sent']}\n";
echo "confirmed={$counts['confirmed']}\n";
echo "declined={$counts['declined']}\n";
echo "expired={$counts['expired']}\n";
echo "checkout_completed={$counts['checkout_completed']}\n";
echo "conversion_intent_to_offer_percent={$intentToOffer}\n";
echo "conversion_offer_to_confirmed_percent={$offerToConfirmed}\n";
echo "conversion_confirmed_to_checkout_percent={$confirmedToCheckout}\n";
