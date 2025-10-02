<?php

namespace App;

use RuntimeException;

class ConfigLoader
{
    public static function load(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Config file not found: $path");
        }

        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'json') {
            throw new RuntimeException("Only JSON config files are supported.");
        }

        $contents = file_get_contents($path);
        $config = json_decode($contents, true);

        if (!is_array($config)) {
            throw new RuntimeException("Invalid JSON config file: $path");
        }

        return $config;
    }

    public static function prepareInput(string $input, int $retries = 3): string
    {
        if (preg_match('/^https?:\/\//i', $input)) {
            return Downloader::download($input, $retries);
        }

        if (!file_exists($input)) {
            throw new RuntimeException("Input file not found: $input");
        }

        return $input;
    }
}
