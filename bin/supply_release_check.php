<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);

$commands = [
    ['label' => 'migration_status', 'cmd' => 'php bin/migrate.php status'],
    ['label' => 'supply_smoke', 'cmd' => 'php bin/supply_smoke.php'],
    ['label' => 'supply_digest', 'cmd' => 'php bin/supply_digest.php --threshold=2'],
];

$results = [];
$failed = false;

foreach ($commands as $item) {
    $label = $item['label'];
    $cmd = $item['cmd'];

    $output = [];
    $code = 0;
    exec('cd ' . escapeshellarg($baseDir) . ' && ' . $cmd . ' 2>&1', $output, $code);

    $results[] = [
        'check' => $label,
        'command' => $cmd,
        'exit_code' => $code,
        'output' => $output,
    ];

    if ($label === 'migration_status') {
        $pending = null;
        foreach ($output as $line) {
            if (preg_match('/^Pending:\s+(\d+)/', $line, $m) === 1) {
                $pending = (int)$m[1];
                break;
            }
        }
        if ($pending === null || $pending > 0) {
            $failed = true;
        }
        continue;
    }

    if ($code !== 0) {
        $failed = true;
    }
}

$payload = [
    'generated_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'ok' => !$failed,
    'results' => $results,
];

fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
exit($failed ? 1 : 0);
