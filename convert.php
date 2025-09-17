#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use App\Converter;

$options = getopt("i:o:m:", ["input:", "output:", "mbtiles:"]);

if (!isset($options['i']) && !isset($options['input'])) {
    fwrite(STDERR, "ERROR: Missing required --input argument\n");
    exit(1);
}
if (!isset($options['o']) && !isset($options['output'])) {
    fwrite(STDERR, "ERROR: Missing required --output argument\n");
    exit(1);
}

$zipPath    = $options['i'] ?? $options['input'];
$outputPath = $options['o'] ?? $options['output'];
$mbtiles    = $options['m'] ?? ($options['mbtiles'] ?? null);

try {
    (new Converter($zipPath, $outputPath, $mbtiles))->run();
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
