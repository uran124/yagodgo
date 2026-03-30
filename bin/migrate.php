<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);

$dbConfig = require $baseDir . '/config/database.php';
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $dbConfig['host'],
    $dbConfig['dbname'],
    $dbConfig['charset']
);

$command = $argv[1] ?? null;
$dryRun = in_array('--dry-run', $argv, true);

if ($command === null || in_array($command, ['-h', '--help'], true)) {
    fwrite(STDOUT, "Usage:\n");
    fwrite(STDOUT, "  php bin/migrate.php up [--dry-run]\n");
    fwrite(STDOUT, "  php bin/migrate.php status\n");
    exit(0);
}

if (!in_array($command, ['up', 'status'], true)) {
    fwrite(STDERR, "Unknown command: {$command}\n");
    exit(1);
}

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: {$e->getMessage()}\n");
    exit(1);
}

$migrationsDir = $baseDir . '/database';
$files = glob($migrationsDir . '/*.sql') ?: [];
$files = array_values(array_filter(array_map('basename', $files), static fn (string $file): bool => $file !== ''));
sort($files, SORT_STRING);

if ($dryRun && $command === 'status') {
    fwrite(STDERR, "The --dry-run flag is supported only for the 'up' command.\n");
    exit(1);
}

$tableExists = static function (PDO $pdo): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE 'schema_migrations'");
    return (bool)$stmt?->fetchColumn();
};

$ensureSchemaMigrations = static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
};

$getAppliedMap = static function (PDO $pdo): array {
    $stmt = $pdo->query('SELECT filename FROM schema_migrations ORDER BY filename ASC');
    $applied = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    return array_fill_keys($applied, true);
};

if ($command === 'status') {
    if (!$tableExists($pdo)) {
        fwrite(STDOUT, "Applied: 0\n");
        fwrite(STDOUT, "Pending: " . count($files) . "\n");
        foreach ($files as $filename) {
            fwrite(STDOUT, "  [pending] {$filename}\n");
        }
        exit(0);
    }

    $appliedMap = $getAppliedMap($pdo);
    $applied = [];
    $pending = [];

    foreach ($files as $filename) {
        if (isset($appliedMap[$filename])) {
            $applied[] = $filename;
        } else {
            $pending[] = $filename;
        }
    }

    fwrite(STDOUT, "Applied: " . count($applied) . "\n");
    foreach ($applied as $filename) {
        fwrite(STDOUT, "  [applied] {$filename}\n");
    }

    fwrite(STDOUT, "Pending: " . count($pending) . "\n");
    foreach ($pending as $filename) {
        fwrite(STDOUT, "  [pending] {$filename}\n");
    }

    exit(0);
}

if ($dryRun) {
    if (!$tableExists($pdo)) {
        fwrite(STDOUT, "[dry-run] would create table schema_migrations\n");
    }

    $appliedMap = $tableExists($pdo) ? $getAppliedMap($pdo) : [];
    $pending = array_values(array_filter($files, static fn (string $file): bool => !isset($appliedMap[$file])));

    fwrite(STDOUT, "[dry-run] pending migrations: " . count($pending) . "\n");
    foreach ($pending as $filename) {
        fwrite(STDOUT, "[dry-run] would apply {$filename}\n");
    }

    exit(0);
}

$ensureSchemaMigrations($pdo);
$appliedMap = $getAppliedMap($pdo);
$pending = array_values(array_filter($files, static fn (string $file): bool => !isset($appliedMap[$file])));

if ($pending === []) {
    fwrite(STDOUT, "No pending migrations.\n");
    exit(0);
}

foreach ($pending as $filename) {
    $path = $migrationsDir . '/' . $filename;
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read migration file: {$filename}\n");
        exit(1);
    }

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);

        $stmt = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (:filename)');
        $stmt->execute(['filename' => $filename]);

        $pdo->commit();
        fwrite(STDOUT, "Applied {$filename}\n");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "Failed to apply {$filename}: {$e->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, 'Done.' . "\n");
