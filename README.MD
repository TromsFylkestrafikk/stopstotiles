# NeTEx stops and quay data to GeoJSON/MBTiles converter

Tool for converting **NeTEx stop data** to **GeoJSON** and **MBTiles**. Supports
local files and remote URLs

## Install:

- Install dependencies
- composer install
- composer dump-autoload

## Usage:

### With CLI arguments

```bash
php convert.php --input <input.zip> --output <output.gejson> --mbtiles <output.mbtiles>
```

**Example**

```bash
php convert.php --input https://storage.googleapis.com/marduk-production/tiamat/Current_latest.zip --output latest.geojson --mbtiles stops.mbtiles --force
```

### With config file

Create a `config.json`

```json
{
  "input": "https://example.com/netex/stops.zip",
  "output": "stops.geojson",
  "mbtiles": "stops.mbtiles",
  "force": true,
  "retries": 5
}
```

Then run

```bash
./convert.php --config config.json
```

## Configuration options

| Key       | Type   | Default          | Description                                                 |
| --------- | ------ | ---------------- | ----------------------------------------------------------- |
| `input`   | string | —                | Path to a local `.zip` file or an HTTP/HTTPS URL            |
| `output`  | string | `output.geojson` | Path to write the generated GeoJSON                         |
| `mbtiles` | string | `null`           | Optional path for generated MBTiles (requires `tippecanoe`) |
| `force`   | bool   | `false`          | Overwrite existing MBTiles file when true                   |
| `retries` | int    | `3`              | Number of times to retry downloading when `input` is a URL  |

---

## Source data

Can be found on https://developer.entur.org/stops-and-timetable-data.
Use Current stops for spesific region or the one for Entire Norway.

- Entire norway https://storage.googleapis.com/marduk-production/tiamat/Current_latest.zip
- Troms https://storage.googleapis.com/marduk-production/tiamat/55_Troms_latest.zip

## Example output

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "geometry": {
        "type": "Point",
        "coordinates": [10.7461, 59.9127]
      },
      "tippecanoe": {
        "layer": "stops",
        "minzoom": 12
      },
      "properties": {
        "type": "StopPlace",
        "id": "NSR:StopPlace:1234",
        "name": "Jernbanetorget",
        "stopPlaceType": "onstreetBus",
        "stopPlaceCategory": "bus"
      }
    },
    {
      "type": "Feature",
      "geometry": {
        "type": "Point",
        "coordinates": [10.747, 59.9129]
      },
      "tippecanoe": {
        "layer": "quays",
        "minzoom": 14
      },
      "properties": {
        "type": "Quay",
        "id": "NSR:Quay:5678",
        "parentStopPlaceId": "NSR:StopPlace:1234"
      }
    }
  ]
}
```

## Requirements

- PHP 8.1+ with `zip` and `curl` extensions enabled
- [tippecanoe](https://github.com/mapbox/tippecanoe) (if generating MBTiles)
