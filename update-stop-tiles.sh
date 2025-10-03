#!/usr/bin/env bash

ZIP_URL="https://example.com/path/to/stops.xml.zip"
ZIP_FILE="/tmp/stops.zip"
OUTPUT_GEOJSON="stops.geojson"
OUTPUT_MBTILES="stops.mbtiles"
FORCE_MB=true   # set to false to skip --force
PHP_CONVERTER="php convert.php"

download_file() {
    curl -L -o "$2" "$1" || { echo "Download failed!"; exit 1; }
}

run_converter() {
    local cmd="$PHP_CONVERTER --input $1 --output $2"
    if [ -n "$3" ]; then
        cmd="$cmd --mbtiles $3"
        $FORCE_MB && cmd="$cmd --force"
    fi
    $cmd || { echo "Conversion failed!"; exit 1; }
}

download_file "$ZIP_URL" "$ZIP_FILE"
run_converter "$ZIP_FILE" "$OUTPUT_GEOJSON" "$OUTPUT_MBTILES"
#sudo service martin restart
