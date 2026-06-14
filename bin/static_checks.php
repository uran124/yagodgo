<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$relativePath = static function (string $path) use ($root): string {
    return str_replace($root . '/', '', $path);
};

// Guard against duplicate literal route signatures in routeExact()/routeRegex().
$seenRoutes = [];
foreach (glob($root . '/routes/*.php') ?: [] as $file) {
    if (basename($file) === 'helpers.php') {
        continue;
    }

    $contents = (string) file_get_contents($file);
    preg_match_all(
        "/\\b(routeExact|routeRegex)\\(\\s*'([^']+)'\\s*,\\s*'([^']+)'/",
        $contents,
        $matches,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );

    foreach ($matches as $match) {
        $signature = $match[1][0] . ' ' . $match[2][0] . ' ' . $match[3][0];
        $line = substr_count(substr($contents, 0, $match[0][1]), "\n") + 1;
        $location = $relativePath($file) . ':' . $line;

        if (isset($seenRoutes[$signature])) {
            $failures[] = '[routes] duplicate ' . $signature . ' at ' . $seenRoutes[$signature] . ' and ' . $location;
            continue;
        }

        $seenRoutes[$signature] = $location;
    }
}

// Guard that server-rendered POST forms include a CSRF field/token.
$viewsDir = $root . '/src/Views';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir));
/** @var SplFileInfo $file */
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $contents = (string) file_get_contents($file->getPathname());
    if (!preg_match_all('/<form\b.*?<\/form>/is', $contents, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($matches[0] as [$form, $offset]) {
        if (!preg_match('/method\s*=\s*(["\'])?post\1?/i', $form)) {
            continue;
        }

        if (strpos($form, 'csrf_field') === false && strpos($form, 'csrf_token') === false) {
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
            $failures[] = '[csrf] POST form without CSRF token at ' . $relativePath($file->getPathname()) . ':' . $line;
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Static checks passed.\n");
