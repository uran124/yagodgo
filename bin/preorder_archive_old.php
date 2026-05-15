#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap/app.php';

$pdo = db();
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "DB connection is not available\n");
    exit(1);
}

$retentionDays = (int)($argv[1] ?? 120);
if ($retentionDays <= 0) {
    fwrite(STDERR, "Usage: php bin/preorder_archive_old.php [retention_days=120]\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS preorder_intents_archive LIKE preorder_intents"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS preorder_intent_events_archive LIKE preorder_intent_events"
    );

    $selectIds = $pdo->prepare(
        "SELECT id FROM preorder_intents
         WHERE status IN ('checkout_completed','declined','expired')
           AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
    );
    $selectIds->execute([$retentionDays]);
    $ids = $selectIds->fetchAll(PDO::FETCH_COLUMN);

    if (!$ids) {
        $pdo->commit();
        echo "Archive complete: nothing to archive\n";
        exit(0);
    }

    $in = implode(',', array_fill(0, count($ids), '?'));

    $insertIntents = $pdo->prepare(
        "INSERT INTO preorder_intents_archive SELECT * FROM preorder_intents WHERE id IN ($in)"
    );
    $insertIntents->execute($ids);
    $archivedIntents = $insertIntents->rowCount();

    $insertEvents = $pdo->prepare(
        "INSERT INTO preorder_intent_events_archive
         SELECT * FROM preorder_intent_events
         WHERE preorder_intent_id IN ($in)"
    );
    $insertEvents->execute($ids);
    $archivedEvents = $insertEvents->rowCount();

    $deleteEvents = $pdo->prepare(
        "DELETE FROM preorder_intent_events WHERE preorder_intent_id IN ($in)"
    );
    $deleteEvents->execute($ids);

    $deleteIntents = $pdo->prepare(
        "DELETE FROM preorder_intents WHERE id IN ($in)"
    );
    $deleteIntents->execute($ids);

    $pdo->commit();
    echo "Archive complete: intents={$archivedIntents}, events={$archivedEvents}, retention_days={$retentionDays}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Archive failed: " . $e->getMessage() . "\n");
    exit(1);
}
