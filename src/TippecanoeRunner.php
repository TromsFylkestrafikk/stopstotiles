<?php

namespace App;

use RuntimeException;

class TippecanoeRunner
{
    public static function run(string $geojsonPath, string $mbtilesPath): void
    {
        echo "⚙️ Running tippecanoe to generate {$mbtilesPath} ...\n";
        $cmd = sprintf(
            "tippecanoe -o %s %s 2>&1",
            escapeshellarg($mbtilesPath),
            escapeshellarg($geojsonPath)
        );
        passthru($cmd, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException("tippecanoe exited with code {$exitCode}");
        }
        echo "OK: Tippecanoe finished. MBTiles written to {$mbtilesPath}\n";
    }
}
