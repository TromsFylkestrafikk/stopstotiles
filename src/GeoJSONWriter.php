<?php

namespace App;

use RuntimeException;

class GeoJSONWriter
{
    public static function write(array $data, string $outputPath): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException("Failed to encode GeoJSON.");
        }
        file_put_contents($outputPath, $json);
    }
}
