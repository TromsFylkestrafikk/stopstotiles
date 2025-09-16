#!/usr/bin/env php
<?php

/**
 * Usage:
 *   php convert.php --input file.zip --output file.geojson [--mbtiles file.mbtiles]
 */

class ZipExtractor
{
    public static function extractXML(string $zipFilePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            throw new RuntimeException("Cannot open ZIP: $zipFilePath");
        }

        // Not sure if there can be multiple XML files in a NeTEx ZIP,
        // All zips for Norway seems to contain just one.
        $xmlFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with(strtolower($name), ".xml")) {
                $xmlFiles[$name] = $zip->getFromIndex($i);
            }
        }
        $zip->close();

        if (empty($xmlFiles)) {
            throw new RuntimeException("No XML files found in ZIP.");
        }
        return $xmlFiles;
    }
}

class NetexParser
{
    private array $ns = ["n" => "http://www.netex.org.uk/netex"];
    private array $categories = [];

    public function __construct()
    {
        // We use this instead of layering different stop types in vectorfiles
        // to avoid cluttering the map with too many layers.
        $grouped = [
            "train"      => ["railStation", "onstreetTram", "metroStation"],
            "bus"        => ["onstreetBus", "busStation"],
            "water"      => ["harbourPort", "ferryStop"],
            "liftStation" => ["liftStation"],
            "airport"    => ["airport"],
            "taxiStand"  => ["taxiStand"]
        ];

        $this->categories = array_merge(
            ...array_map(
                fn($category, $items) => array_fill_keys($items, $category),
                array_keys($grouped),
                $grouped
            )
        );
    }

    public function parse(string $xml): array
    {
        $root = simplexml_load_string($xml);
        if (!$root) {
            throw new RuntimeException("Failed to parse XML.");
        }

        $siteFrame = $root->children($this->ns['n'])
            ->dataObjects->children($this->ns['n'])
            ->SiteFrame;

        if (!$siteFrame) return [];

        $stopPlaces = $siteFrame->children($this->ns['n'])
            ->stopPlaces->children($this->ns['n']);

        if (!$stopPlaces) return [];

        $features = [];
        foreach ($stopPlaces->StopPlace as $place) {
            foreach ($this->parseStopPlace($place) as $f) {
                $features[] = $f;
            }
        }

        return $features;
    }

    private function parseStopPlace(SimpleXMLElement $place): array
    {
        $features = [];
        $attrs = $place->attributes();
        $id = (string)($attrs['id'] ?? '');

        // Check IS_PARENT_STOP_PLACE
        $isParent = false;
        $keyList = $place->children($this->ns['n'])->keyList ?? null;
        if ($keyList) {
            foreach ($keyList->KeyValue as $kv) {
                if (
                    (string)($kv->Key ?? '') === "IS_PARENT_STOP_PLACE" &&
                    strtolower((string)($kv->Value ?? '')) === "true"
                ) {
                    $isParent = true;
                }
            }
        }
        if ($isParent) return [];

        // Main StopPlace
        $centroid = $place->children($this->ns['n'])->Centroid->children($this->ns['n'])->Location;
        $lat = (float)($centroid->children($this->ns['n'])->Latitude ?? 0);
        $lon = (float)($centroid->children($this->ns['n'])->Longitude ?? 0);
        $type = (string)$place->children($this->ns['n'])->StopPlaceType;
        $stopPlaceCategory = $this->categories[$type] ?? "other";

        if ($lat && $lon) {
            $minzoom = ($type === "onstreetBus") ? 12 : 8;
            $features[] = [
                "type" => "Feature",
                "geometry" => ["type" => "Point", "coordinates" => [$lon, $lat]],
                "tippecanoe" => ["layer" => "stops", "minzoom" => $minzoom],
                "properties" => [
                    "type" => "StopPlace",
                    "id"   => $id,
                    "name" => (string)$place->children($this->ns['n'])->Name,
                    "stopPlaceType" => $type,
                    "stopPlaceCategory" => $stopPlaceCategory,
                ],
            ];
        }

        // Quays
        $quaysNode = $place->children($this->ns['n'])->quays ?? null;
        if ($quaysNode) {
            foreach ($quaysNode->children($this->ns['n'])->Quay ?? [] as $quay) {
                foreach ($this->parseQuay($quay, $id) as $qf) {
                    $features[] = $qf;
                }
            }
        }

        return $features;
    }

    private function parseQuay(SimpleXMLElement $quay, string $parentId): array
    {
        $attrs = $quay->attributes();
        $id = (string)($attrs['id'] ?? '');

        $centroid = $quay->children($this->ns['n'])->Centroid->children($this->ns['n'])->Location;
        $lat = (float)($centroid->children($this->ns['n'])->Latitude ?? 0);
        $lon = (float)($centroid->children($this->ns['n'])->Longitude ?? 0);

        // Private code should probably be removed, not meant for public display
        // but it's the only "name-like" field available in Quay.
        // Though we have decied to not show name/number of quay as far as I know
        //$pvcode = (string)($quay->children($this->ns['n'])->PrivateCode ?? "0");

        if (!$lat || !$lon) return [];

        return [[
            "type" => "Feature",
            "geometry" => ["type" => "Point", "coordinates" => [$lon, $lat]],
            "tippecanoe" => ["layer" => "quays", "minzoom" => 14],
            "properties" => [
                "type" => "Quay",
                "id"   => $id,
                //      "name" => $pvcode,  // Using PrivateCode as name, but should it be shown? No..
                "parentStopPlaceId" => $parentId,
            ],
        ]];
    }
}

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

class TippecanoeRunner
{
    //General Tippecanoe options could be added here if needed (especially --force to overwrite an existing mbtiles file)
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

            // Probably useless. Looks like nothing gets printed until the script ends.
            // Might be because we only parse one xml file, even though the functionality
            // supports multiple xml-files in zip.
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

// ---- CLI Entrypoint ----
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
