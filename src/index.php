#!/usr/bin/env php
<?php
/**
 * Usage:
 *   php convert.php --input file.zip --output file.geojson [--mbtiles file.mbtiles]
 */

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

/**
 * Extract all XML files from ZIP and return as [filename => xml string]
 */
function extractAllXMLFromZip(string $zipFilePath): array
{
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath) !== true) {
        throw new RuntimeException("Cannot open ZIP: $zipFilePath");
    }

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

/**
 * Convert XML to GeoJSON features (array of Features)
 */
function convertXMLToFeatures(string $xml): array
{
    $ns = ["n" => "http://www.netex.org.uk/netex"];
    $root = simplexml_load_string($xml);
    if (!$root) {
        throw new RuntimeException("Failed to parse XML.");
    }

    $siteFrame = $root->children($ns['n'])
        ->dataObjects->children($ns['n'])
        ->SiteFrame;

    if (!$siteFrame) {
        return [];
    }

    $stopPlaces = $siteFrame->children($ns['n'])->stopPlaces->children($ns['n']);
    if (!$stopPlaces) {
        return [];
    }

    $features = [];
    foreach ($stopPlaces->StopPlace as $place) {
        $attrs = $place->attributes();
        $id = (string)($attrs['id'] ?? '');

        $isParent = null;
        $keyList = $place->children($ns['n'])->keyList ?? null;
        if ($keyList) {
            foreach ($keyList->KeyValue as $kv) {
                $keyName = (string)($kv->Key ?? '');
                $val     = strtolower((string)($kv->Value ?? ''));
                if ($keyName === "IS_PARENT_STOP_PLACE") {
                    $isParent = ($val === "true");
                }
            }
        }

        // Skip if IS_PARENT_STOP_PLACE is true
        if ($isParent === true) {
            continue;
        }

        $centroid = $place->children($ns['n'])->Centroid->children($ns['n'])->Location;
        $stopLat = (float)($centroid->children($ns['n'])->Latitude ?? 0);
        $stopLon = (float)($centroid->children($ns['n'])->Longitude ?? 0);
        $stopPlaceType = (string)$place->children($ns['n'])->StopPlaceType;
        if ($stopLat && $stopLon) {
            $minzoom = ($stopPlaceType === "onstreetBus") ? 12 : 8;

            $features[] = [
                "type" => "Feature",
                "geometry" => [
                    "type" => "Point",
                    "coordinates" => [$stopLon, $stopLat],
                ],
                "tippecanoe" => [
                    "layer" => "stops",
                    "minzoom" => $minzoom,
                ],
                "properties" => [
                    "type" => "StopPlace",
                    "id"   => $id,
                    "name" => (string)$place->children($ns['n'])->Name,
                    "stopPlaceType" => $stopPlaceType,
                ],
            ];
        }

        // --- Quays ---
        $quaysNode = $place->children($ns['n'])->quays ?? null;
        if ($quaysNode) {
            $quayList = $quaysNode->children($ns['n'])->Quay ?? [];
            foreach ($quayList as $quay) {
                $quayAttrs = $quay->attributes();
                $quayId = (string)($quayAttrs['id'] ?? '');

                $qcentroid = $quay->children($ns['n'])->Centroid->children($ns['n'])->Location;
                $quayLat = (float)($qcentroid->children($ns['n'])->Latitude ?? 0);
                $quayLon = (float)($qcentroid->children($ns['n'])->Longitude ?? 0);

                $pvcode = "0";
                if (isset($quay->children($ns['n'])->PrivateCode)) {
                    $pvcode = (string)$quay->children($ns['n'])->PrivateCode;
                }

                if ($quayLat && $quayLon) {
                    $features[] = [
                        "type" => "Feature",
                        "geometry" => [
                            "type" => "Point",
                            "coordinates" => [$quayLon, $quayLat],
                        ],
                        "tippecanoe" => [
                            "layer" => "quays",
                            "minzoom" => 14,
                        ],
                        "properties" => [
                            "type" => "Quay",
                            "id"   => $quayId,
                            "name" => $pvcode,
                            "parentStopPlaceId" => $id,
                        ],
                    ];
                }
            }
        }
    }

    return $features;
}

/**
 * Write GeoJSON to file
 */
function writeGeoJSONToFile(array $data, string $outputPath): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException("Failed to encode GeoJSON.");
    }
    file_put_contents($outputPath, $json);
}

/**
 * Run tippecanoe if mbtiles specified
 */
function runTippecanoe(string $geojsonPath, string $mbtilesPath): void
{
    echo "⚙️ Running tippecanoe to generate {$mbtilesPath} ...\n";
    $cmd = sprintf("tippecanoe -o %s %s 2>&1", escapeshellarg($mbtilesPath), escapeshellarg($geojsonPath));
    passthru($cmd, $exitCode);
    if ($exitCode === 0) {
        echo "OK: Tippecanoe finished. MBTiles written to {$mbtilesPath}\n";
    } else {
        throw new RuntimeException("tippecanoe exited with code {$exitCode}");
    }
}

// ---- Main ----
try {
    $xmlFiles = extractAllXMLFromZip($zipPath);

    $allFeatures = [];
    $fileCount = count($xmlFiles);
    $processed = 0;

    foreach ($xmlFiles as $fname => $xml) {
        $features = convertXMLToFeatures($xml);
        $allFeatures = array_merge($allFeatures, $features);

        $processed++;
        $progress = floor(($processed / $fileCount) * 100);
        echo "\rProcessed {$processed}/{$fileCount} XML files ({$progress}%)";
    }

    echo "\nOK: Parsed " . count($allFeatures) . " features total.\n";

    $geojson = [
        "type" => "FeatureCollection",
        "features" => $allFeatures,
    ];

    writeGeoJSONToFile($geojson, $outputPath);
    echo "OK: GeoJSON written to {$outputPath}\n";

    if ($mbtiles) {
        runTippecanoe($outputPath, $mbtiles);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: Error: " . $e->getMessage() . "\n");
    exit(1);
}
