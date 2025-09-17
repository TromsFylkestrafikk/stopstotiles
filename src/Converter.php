<?php

namespace App;

class Converter
{
    private string $zipPath;
    private string $outputPath;
    private ?string $mbtiles;

    public function __construct(string $zipPath, string $outputPath, ?string $mbtiles)
    {
        $this->zipPath = $zipPath;
        $this->outputPath = $outputPath;
        $this->mbtiles = $mbtiles;
    }

    public function run(): void
    {
        $xmlFiles = ZipExtractor::extractXML($this->zipPath);
        $parser = new NetexParser();

        $allFeatures = [];
        $total = count($xmlFiles);
        $done = 0;

        foreach ($xmlFiles as $fname => $xml) {
            $features = $parser->parse($xml);
            foreach ($features as $f) {
                $allFeatures[] = $f;
            }

            $done++;
            $progress = floor(($done / $total) * 100);
            echo "\rProcessed {$done}/{$total} XML files ({$progress}%)";
        }

        echo "\nOK: Parsed " . count($allFeatures) . " features total.\n";

        $geojson = ["type" => "FeatureCollection", "features" => $allFeatures];
        GeoJSONWriter::write($geojson, $this->outputPath);
        echo "OK: GeoJSON written to {$this->outputPath}\n";

        if ($this->mbtiles) {
            TippecanoeRunner::run($this->outputPath, $this->mbtiles);
        }
    }
}
