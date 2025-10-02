#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use App\Converter;
use App\ConfigLoader;

$options = getopt("i:o:m:c:f", ["input:", "output:", "mbtiles:", "config:", "force"]);

// If a config file is given, load settings from there
if (isset($options['c']) || isset($options['config'])) {
    $configFile = $options['c'] ?? $options['config'];
    $config = ConfigLoader::load($configFile);

    $retries    = (int)($config['retries'] ?? 3);
    $zipPath    = ConfigLoader::prepareInput($config['input'] ?? '', $retries);
    $outputPath = $config['output'] ?? 'output.geojson';
    $mbtiles    = $config['mbtiles'] ?? null;
    $force      = (bool)($config['force'] ?? false);
} else {
    // CLI fallback
    if (!isset($options['i']) && !isset($options['input'])) {
        fwrite(STDERR, "ERROR: Missing required --input argument\n");
        exit(1);
    }
    if (!isset($options['o']) && !isset($options['output'])) {
        fwrite(STDERR, "ERROR: Missing required --output argument\n");
        exit(1);
    }

    $retries    = 3;
    $zipPath    = ConfigLoader::prepareInput($options['i'] ?? $options['input'], $retries);
    $outputPath = $options['o'] ?? $options['output'];
    $mbtiles    = $options['m'] ?? ($options['mbtiles'] ?? null);
    $force      = false;
}

// CLI --force overrides config file
if (isset($options['f']) || isset($options['force'])) {
    $force = true;
}

try {
    (new Converter($zipPath, $outputPath, $mbtiles, $force))->run();
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
