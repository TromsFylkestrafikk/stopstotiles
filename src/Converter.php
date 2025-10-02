<?php

namespace App;

use RuntimeException;
use Throwable;

class Converter
{
    private string $zipPath;
    private string $outputPath;
    private ?string $mbtiles;
    private bool $force;

    public function __construct(string $zipPath, string $outputPath, ?string $mbtiles = null, bool $force = false)
    {
        $this->zipPath    = $zipPath;
        $this->outputPath = $outputPath;
        $this->mbtiles    = $mbtiles;
        $this->force      = $force;
    }

    public function run(): void
    {
        $xmlFiles = ZipExtractor::extractXML($this->zipPath);
        $parser   = new NetexParser();

        $allFeatures = [];
        $total = count($xmlFiles);
        $done  = 0;

        foreach ($xmlFiles as $fname => $xml) {
            $features = $parser->parse($xml);
            foreach ($features as $f) {
                $allFeatures[] = $f;
            }

            $done++;
            $progress = floor(($done / $total) * 100);
            echo "\rProcessed {$done}/{$total} XML files ({$progress}%)";
        }

        echo "\nParsed " . count($allFeatures) . " features total.\n";

        $geojson = ["type" => "FeatureCollection", "features" => $allFeatures];
        GeoJSONWriter::write($geojson, $this->outputPath);
        echo "GeoJSON written to {$this->outputPath}\n";

        if ($this->mbtiles) {
            TippecanoeRunner::run($this->outputPath, $this->mbtiles, $this->force);
        }
    }
}
