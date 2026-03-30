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

$ensureSchemaMigrations = static function (PDO $pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            checksum VARCHAR(64) NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            execution_time_ms INT UNSIGNED NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $columnsStmt = $pdo->query('SHOW COLUMNS FROM schema_migrations');
    $columns = $columnsStmt ? array_map(static fn (array $row): string => (string) $row['Field'], $columnsStmt->fetchAll(PDO::FETCH_ASSOC)) : [];

    if (!in_array('checksum', $columns, true)) {
        $pdo->exec('ALTER TABLE schema_migrations ADD COLUMN checksum VARCHAR(64) NULL AFTER filename');
    }

    if (!in_array('execution_time_ms', $columns, true)) {
        $pdo->exec('ALTER TABLE schema_migrations ADD COLUMN execution_time_ms INT UNSIGNED NULL AFTER applied_at');
    }
};

$getAppliedRows = static function (PDO $pdo): array {
    $stmt = $pdo->query('SELECT filename, checksum FROM schema_migrations ORDER BY filename ASC');
    if (!$stmt) {
        return [];
    }

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(string) $row['filename']] = $row['checksum'] !== null ? (string) $row['checksum'] : null;
    }

    return $map;
};

$ensureSchemaMigrations($pdo);

if ($command === 'status') {
    $appliedMap = $getAppliedRows($pdo);
    $applied = [];
    $pending = [];

    foreach ($files as $filename) {
        if (array_key_exists($filename, $appliedMap)) {
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
    $appliedMap = $getAppliedRows($pdo);
    $pending = array_values(array_filter($files, static fn (string $file): bool => !array_key_exists($file, $appliedMap)));

    fwrite(STDOUT, "[dry-run] pending migrations: " . count($pending) . "\n");
    foreach ($pending as $filename) {
        fwrite(STDOUT, "[dry-run] would apply {$filename}\n");
    }

    exit(0);
}

$appliedMap = $getAppliedRows($pdo);
$pending = array_values(array_filter($files, static fn (string $file): bool => !array_key_exists($file, $appliedMap)));

if ($pending === []) {
    fwrite(STDOUT, "No pending migrations.\n");
    exit(0);
}

foreach ($files as $filename) {
    if (!array_key_exists($filename, $appliedMap)) {
        continue;
    }

    $path = $migrationsDir . '/' . $filename;
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read migration file: {$filename}\n");
        exit(1);
    }

    $currentChecksum = hash('sha256', $sql);
    $storedChecksum = $appliedMap[$filename];

    if ($storedChecksum !== null && $storedChecksum !== $currentChecksum) {
        fwrite(STDERR, "Checksum mismatch for applied migration {$filename}.\n");
        exit(1);
    }
}

foreach ($pending as $filename) {
    $path = $migrationsDir . '/' . $filename;
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read migration file: {$filename}\n");
        exit(1);
    }

    $checksum = hash('sha256', $sql);

    try {
        $pdo->beginTransaction();
        $startedAt = microtime(true);

        $pdo->exec($sql);

        $executionTimeMs = (int) round((microtime(true) - $startedAt) * 1000);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (filename, checksum, execution_time_ms) VALUES (:filename, :checksum, :execution_time_ms)');
        $stmt->execute([
            'filename' => $filename,
            'checksum' => $checksum,
            'execution_time_ms' => $executionTimeMs,
        ]);

        $pdo->commit();
        fwrite(STDOUT, "Applied {$filename} ({$executionTimeMs} ms)\n");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "Failed to apply {$filename}: {$e->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, 'Done.' . "\n");
