<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$feed = $root . '/feeds/catalog.yml';
$errors = [];

if (!is_file($feed)) {
    $errors[] = 'YML file is missing: run php bin/generate_catalog_feed.php --force.';
} else {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($feed);
    if ($xml === false || $xml->getName() !== 'yml_catalog') {
        $errors[] = 'YML file is invalid.';
    } elseif (!isset($xml->shop->offers)) {
        $errors[] = 'YML file does not contain offers.';
    }
}

if (is_file($root . '/feeds/catalog.yml.tmp')) {
    $errors[] = 'Unexpected catalog.yml.tmp is present; inspect generation failure before release.';
}

if ($errors !== []) {
    foreach ($errors as $error) fwrite(STDERR, "[fail] {$error}\n");
    exit(1);
}

fwrite(STDOUT, "[ok] Florix24 release checks passed.\n");
