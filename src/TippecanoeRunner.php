<?php

namespace App;

use RuntimeException;

class TippecanoeRunner
{
    public static function run(string $geojsonPath, string $mbtilesPath, bool $force = false): void
    {
        echo "⚙️ Running tippecanoe to generate {$mbtilesPath} ...\n";
        $cmd = sprintf(
            "tippecanoe %s -o %s %s 2>&1",
            $force ? "--force" : "",
            escapeshellarg($mbtilesPath),
            escapeshellarg($geojsonPath)
        );
        // Just to collapse double space in command if force flag is not used
        $cmd = preg_replace('/\s+/', ' ', $cmd);
        passthru($cmd, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException("tippecanoe exited with code {$exitCode}");
        }
        echo "OK: Tippecanoe finished. MBTiles written to {$mbtilesPath}\n";
    }
}
