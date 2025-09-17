<?php

namespace App;

use RuntimeException;
use ZipArchive;

class ZipExtractor
{
    public static function extractXML(string $zipFilePath): array
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
}
