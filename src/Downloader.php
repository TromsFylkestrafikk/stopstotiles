<?php

namespace App;

use RuntimeException;

class Downloader
{
    /**
     * Download a file from URL with retry support.
     *
     * @param string $url     The URL to download.
     * @param int    $retries Number of attempts before failing.
     * @param int    $delay   Delay in seconds between retries.
     *
     * @return string Path to the downloaded file.
     * @throws RuntimeException if download fails after retries.
     */
    public static function download(string $url, int $retries = 3, int $delay = 2): string
    {
        $attempt = 0;

        while ($attempt < $retries) {
            $attempt++;
            echo "Downloading input from $url (attempt $attempt/$retries)...\n";

            $tmpFile = tempnam(sys_get_temp_dir(), "netex_") . ".zip";
            $fp = fopen($tmpFile, 'w+');
            if (!$fp) {
                throw new RuntimeException("Failed to create temp file for download.");
            }

            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException("Failed to initialize cURL.");
            }

            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,             // Write directly to file
                CURLOPT_FOLLOWLOCATION => true,  // Follow redirects
                CURLOPT_FAILONERROR => true,     // Fail on HTTP >= 400
                CURLOPT_TIMEOUT => 60,           // Timeout in seconds
                CURLOPT_USERAGENT => "NetexConverter/1.0",
            ]);

            $success = curl_exec($ch);
            $error   = curl_error($ch);
            $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fp);

            if ($success && $status < 400) {
                echo "OK: Downloaded to $tmpFile\n";
                return $tmpFile;
            }

            // Cleanup failed attempt
            unlink($tmpFile);

            echo "Download failed (HTTP $status - $error)\n";

            if ($attempt < $retries) {
                echo "Retrying in {$delay}s...\n";
                sleep($delay);
            }
        }

        throw new RuntimeException("Failed to download after {$retries} attempts: $url");
    }
}
