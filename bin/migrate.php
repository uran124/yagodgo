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

/**
 * Florix24 migrations are schema-aware so a manually repaired/partially
 * deployed database can converge safely instead of failing on duplicate DDL.
 */
$applyManagedMigration = static function (PDO $pdo, string $filename): bool {
    if (!in_array($filename, ['20260723_florix24_inbound_api.sql', '20260724_florix24_hardening.sql'], true)) return false;
    $tableExists = static function (string $table) use ($pdo): bool { $s=$pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);return (int)$s->fetchColumn()>0; };
    $columnExists = static function (string $table, string $column) use ($pdo): bool { $s=$pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$column]);return (int)$s->fetchColumn()>0; };
    $indexExists = static function (string $table, string $index) use ($pdo): bool { $s=$pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');$s->execute([$table,$index]);return (int)$s->fetchColumn()>0; };
    if ($filename === '20260723_florix24_inbound_api.sql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS integration_clients (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,source VARCHAR(50) NOT NULL,token_hash VARCHAR(255) NOT NULL,permissions JSON NULL,is_active TINYINT(1) NOT NULL DEFAULT 1,allowed_ips TEXT NULL,rate_limit_per_minute INT UNSIGNED NOT NULL DEFAULT 60,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_integration_clients_source (source)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS integration_request_logs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,integration_client_id BIGINT UNSIGNED NULL,source VARCHAR(50) NOT NULL,endpoint VARCHAR(255) NOT NULL,request_payload JSON NULL,response_payload JSON NULL,http_status SMALLINT UNSIGNED NOT NULL,external_order_id VARCHAR(128) NULL,partner_user_id INT UNSIGNED NULL,points_used INT NOT NULL DEFAULT 0,error_code VARCHAR(64) NULL,correlation_id VARCHAR(64) NOT NULL,processing_ms INT UNSIGNED NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_integration_request_logs_source_created (source,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $orderColumns=['integration_source'=>'VARCHAR(50) NULL','external_order_id'=>'VARCHAR(128) NULL','partner_user_id'=>'INT UNSIGNED NULL','partner_source'=>'VARCHAR(50) NULL','external_partner_id'=>'VARCHAR(128) NULL','external_partner_name'=>'VARCHAR(255) NULL','subtotal_before_points'=>'DECIMAL(10,2) NULL','points_discount_amount'=>'DECIMAL(10,2) NOT NULL DEFAULT 0','total_after_points'=>'DECIMAL(10,2) NULL'];foreach($orderColumns as $name=>$ddl)if(!$columnExists('orders',$name))$pdo->exec("ALTER TABLE orders ADD COLUMN {$name} {$ddl}");
        foreach(['source'=>'VARCHAR(64) NULL','external_order_id'=>'VARCHAR(128) NULL','related_transaction_id'=>'INT UNSIGNED NULL'] as $name=>$ddl)if(!$columnExists('points_transactions',$name))$pdo->exec("ALTER TABLE points_transactions ADD COLUMN {$name} {$ddl}");
        foreach(['external_catalog_enabled'=>'TINYINT(1) NOT NULL DEFAULT 0','external_name'=>'VARCHAR(255) NULL','external_description'=>'TEXT NULL','external_sku'=>'VARCHAR(128) NULL','external_image_path'=>'VARCHAR(255) NULL'] as $name=>$ddl)if(!$columnExists('products',$name))$pdo->exec("ALTER TABLE products ADD COLUMN {$name} {$ddl}");
        if(!$indexExists('orders','uq_orders_integration_external'))$pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_integration_external (integration_source,external_order_id)');
    } else {
        foreach(['token_prefix'=>'VARCHAR(32) NOT NULL DEFAULT \'\'','ip_check_enabled'=>'TINYINT(1) NOT NULL DEFAULT 0','trusted_proxy_mode'=>'TINYINT(1) NOT NULL DEFAULT 0','last_used_at'=>'DATETIME NULL','expires_at'=>'DATETIME NULL','revoked_at'=>'DATETIME NULL'] as $name=>$ddl)if(!$columnExists('integration_clients',$name))$pdo->exec("ALTER TABLE integration_clients ADD COLUMN {$name} {$ddl}");
        if(!$columnExists('users','integration_partner_enabled'))$pdo->exec('ALTER TABLE users ADD COLUMN integration_partner_enabled TINYINT(1) NOT NULL DEFAULT 0');
        if(!$columnExists('products','external_updated_at'))$pdo->exec('ALTER TABLE products ADD COLUMN external_updated_at DATETIME NULL');
        $type=$pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='points_transactions' AND COLUMN_NAME='transaction_type'")->fetchColumn();
        if (is_string($type) && (!str_contains($type, "'partner_reward'") || !str_contains($type, "'refund'"))) $pdo->exec("ALTER TABLE points_transactions MODIFY COLUMN transaction_type ENUM('accrual','usage','payout','refund','partner_reward','partner_reward_reversal') NOT NULL");
        $pdo->exec("CREATE TABLE IF NOT EXISTS integration_rate_limit_windows (integration_client_id BIGINT UNSIGNED NOT NULL,window_started_at DATETIME NOT NULL,request_count INT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY (integration_client_id,window_started_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS catalog_feed_state (id TINYINT UNSIGNED NOT NULL PRIMARY KEY,is_dirty TINYINT(1) NOT NULL DEFAULT 1,generated_at DATETIME NULL,last_error TEXT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec('INSERT IGNORE INTO catalog_feed_state (id,is_dirty) VALUES (1,1)');
    }
    return true;
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

        if (!$applyManagedMigration($pdo, $filename)) {
            $pdo->exec($sql);
        }

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
