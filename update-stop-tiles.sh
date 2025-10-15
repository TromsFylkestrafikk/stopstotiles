#!/usr/bin/env bash

#
# Default configuration. Override by copying .env-example to .env and adjust
# values there.  See .env-example for explanation

ZIP_URL="https://storage.googleapis.com/marduk-production/tiamat/Current_latest.zip"
ZIP_FILE="./build/stops.zip"
OUTPUT_GEOJSON="./build/stops.geojson"
OUTPUT_MBTILES="./build/stops.mbtiles"
FORCE_MB=1
TARGET_MBTILE=""
DELETE_INTERIM=0

cd $(dirname $0)
set -e
if [[ -f ./.env ]]; then
    source ./.env
fi

PHP_CONVERTER="./convert.php"
download_file() {
    curl -L -o "$2" "$1" || { echo "Download failed!"; exit 1; }
}

run_converter() {
    local cmd="$PHP_CONVERTER --input $1 --output $2"
    if [ -n "$3" ]; then
        cmd="$cmd --mbtiles $3"
        if (( FORCE_MB > 0 )); then
            cmd="$cmd --force"
        fi
    fi
    $cmd
}

download_file "$ZIP_URL" "$ZIP_FILE"
run_converter "$ZIP_FILE" "$OUTPUT_GEOJSON" "$OUTPUT_MBTILES"

if (( DELETE_INTERIM > 0 )); then
    rm -fv $ZIP_FILE
    rm -fv $OUTPUT_GEOJSON
fi

CP_OPTS="-v"
if (( FORCE_MB > 0 )); then
    CP_OPTS="$CP_OPTS -f"
else
    CP_OPTS="$CP_OPTS --update=none"
fi

if [[ -n $TARGET_MBTILE ]]; then
    cp $CP_OPTS $OUTPUT_MBTILES $TARGET_MBTILE
    if (( DELETE_INTERIM > 0 )); then
        rm -fv $OUTPUT_MBTILES
    fi
fi
