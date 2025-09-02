# Stops to tiles

Tool for creating stop tiles from netex data

Usage:

php src/index.php --input <input.zip> --output <output.gejson> --mbtiles <output.mbtiles>

Example:
php src/index.php --input Current_latest.zip --output latest.geojson --mbtiles stops.mbtiles

# Source data

Can be found on https://developer.entur.org/stops-and-timetable-data.
Use Current stops for spesific region or the one for Entire Norway.

- Entire norway https://storage.googleapis.com/marduk-production/tiamat/Current_latest.zip
- Troms https://storage.googleapis.com/marduk-production/tiamat/55_Troms_latest.zip

Files must be downloaded locally (for now)

# External dependencies

https://github.com/mapbox/tippecanoe
