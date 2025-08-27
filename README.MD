# Stops to tiles

Tool for creating stop tiles from netex data

Usage:

php src/index.php --input <input.zip> --output <geojsonfile> --mbtiles <outputmbtilesfile>

Example:
php src/index.php --input Current_latest.zip --output latest.geojson --mbtiles stops.mbtiles

# Source data

Can be found on https://developer.entur.org/stops-and-timetable-data.
Use Current stops for spesific region or the one for Entire Norway.

# External dependencies

https://github.com/mapbox/tippecanoe
