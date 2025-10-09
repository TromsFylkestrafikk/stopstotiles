#!/usr/bin/env bash

#
# Default configuration. Override by copying .env-example to .env
#
# URL containing zipped stop places in NeTEx format.
# This instance is all active stops in Norway
ZIP_URL="https://storage.googleapis.com/marduk-production/tiamat/Current_latest.zip"

# Target of downloaded zip.
ZIP_FILE="./build/stops.zip"

# Target of generated geojson.
OUTPUT_GEOJSON="./build/stops.geojson"

# Target of final mbtiles file.
OUTPUT_MBTILES="./build/stops.mbtiles"

# Set to 1 to force overwrite existing mbtiles file.
FORCE_MB=1

# Set to 1 if downladed (zip) and temporarily generated files (geojson) files
# should be deleted after successful tile generation
DELETE_INTERIM=0

cd $(dirname $0)
set -e
if [[ -f ./.env ]]; then
    source ./.env
fi

PHP_CONVERTER="php ./convert.php"
download_file() {
    curl -L -o "$2" "$1" || { echo "Download failed!"; exit 1; }
}

run_converter() {
    local cmd="$PHP_CONVERTER --input $1 --output $2"
    if [ -n "$3" ]; then
        cmd="$cmd --mbtiles $3"
        if [[ $((FORVE_MB)) ]]; then
            cmd="$cmd --force"
        fi
    fi
    $cmd
}

download_file "$ZIP_URL" "$ZIP_FILE"
run_converter "$ZIP_FILE" "$OUTPUT_GEOJSON" "$OUTPUT_MBTILES"

if [[ $((DELETE_INTERIM)) > 0 ]]; then
    rm -f $ZIP_FILE
    rm -f $OUTPUT_GEOJSON
fi
