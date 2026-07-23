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
$skipBackup = in_array('--skip-backup', $argv, true);

if ($command === null || in_array($command, ['-h', '--help'], true)) {
    fwrite(STDOUT, "Usage:\n");
    fwrite(STDOUT, "  php bin/migrate.php up [--dry-run] [--skip-backup]\n");
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

/** Create a consistent backup before a production schema change. */
$createBackup = static function (array $config, string $baseDir): string {
    $directory = getenv('MIGRATION_BACKUP_DIR') ?: $baseDir . '/backups/database';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException("Cannot create migration backup directory: {$directory}");
    }
    $target = rtrim($directory, '/') . '/' . date('Ymd-His') . '-' . $config['dbname'] . '.sql';
    $credentials = tempnam(sys_get_temp_dir(), 'berrygo-mysql-');
    if ($credentials === false) throw new RuntimeException('Cannot create temporary MySQL credentials file.');
    try {
        if (file_put_contents($credentials, "[client]\nhost={$config['host']}\nuser={$config['user']}\npassword={$config['password']}\n") === false) throw new RuntimeException('Cannot write temporary MySQL credentials file.');
        chmod($credentials, 0600);
        $command = sprintf('mysqldump --defaults-extra-file=%s --single-transaction --routines --events --default-character-set=%s %s > %s', escapeshellarg($credentials), escapeshellarg($config['charset']), escapeshellarg($config['dbname']), escapeshellarg($target));
        exec($command, $output, $status);
        if ($status !== 0 || !is_file($target) || filesize($target) === 0) { @unlink($target); throw new RuntimeException('mysqldump failed; no migration was applied.'); }
        chmod($target, 0600);
        return $target;
    } finally { @unlink($credentials); }
};

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

if (!$skipBackup) {
    try {
        $backupPath = $createBackup($dbConfig, $baseDir);
        fwrite(STDOUT, "Database backup created: {$backupPath}\n");
    } catch (Throwable $e) {
        fwrite(STDERR, "Migration backup failed: {$e->getMessage()}\n");
        fwrite(STDERR, "Use --skip-backup only after an externally verified backup.\n");
        exit(1);
    }
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

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
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
