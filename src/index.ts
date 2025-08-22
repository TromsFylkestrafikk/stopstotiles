import fs from "fs";
import AdmZip from "adm-zip";
import { XMLParser } from "fast-xml-parser";
import yargs from "yargs";
import { hideBin } from "yargs/helpers";
import { spawn } from "child_process";

// CLI setup
const argv = yargs(hideBin(process.argv))
  .option("input", {
    alias: "i",
    describe: "Path to the input ZIP file",
    demandOption: true,
    type: "string",
  })
  .option("output", {
    alias: "o",
    describe: "Path to the output GeoJSON file",
    demandOption: true,
    type: "string",
  })
  .option("mbtiles", {
    alias: "m",
    describe: "Optional output .mbtiles filename (runs tippecanoe)",
    type: "string",
  })
  .help().argv as any;

const zipPath = argv.input;
const outputPath = argv.output;

function extractXMLFromZip(zipFilePath: string): string {
  const zip = new AdmZip(zipFilePath);
  const zipEntries = zip.getEntries();

  const xmlEntry = zipEntries.find((entry) => entry.entryName.endsWith(".xml"));

  if (!xmlEntry) {
    throw new Error("No XML file found in the ZIP archive.");
  }

  return zip.readAsText(xmlEntry);
}

function convertXMLToGeoJSON(xml: string): any {
  const parser = new XMLParser({
    ignoreAttributes: false,
    attributeNamePrefix: "",
    removeNSPrefix: true, // ✅ strip namespaces like ns2:
  });
  const parsedXml = parser.parse(xml);

  const stopPlaces =
    parsedXml?.PublicationDelivery?.dataObjects?.SiteFrame?.stopPlaces
      ?.StopPlace;

  if (!stopPlaces) {
    throw new Error("No StopPlace elements found in XML.");
  }

  const features: any[] = [];

  (Array.isArray(stopPlaces) ? stopPlaces : [stopPlaces]).forEach(
    (place: any) => {
      // --- StopPlace feature ---
      const stopLat = parseFloat(place.Centroid?.Location?.Latitude ?? 0);
      const stopLon = parseFloat(place.Centroid?.Location?.Longitude ?? 0);

      if (stopLat && stopLon) {
        const stopPlaceType = place.StopPlaceType; // e.g. "onstreetBus"
        const minzoom = stopPlaceType === "onstreetBus" ? 12 : 8;

        features.push({
          type: "Feature",
          geometry: {
            type: "Point",
            coordinates: [stopLon, stopLat],
          },
          tippecanoe: {
            layer: "stops",
            minzoom,
          },
          properties: {
            type: "StopPlace",
            id: place.id,
            name: place.Name["#text"],
            stopPlaceType,
          },
        });
      }

      // --- Quay features ---
      const quays = place.quays?.Quay;
      if (quays) {
        (Array.isArray(quays) ? quays : [quays]).forEach((quay: any) => {
          const quayLat = parseFloat(quay.Centroid?.Location?.Latitude ?? 0);
          const quayLon = parseFloat(quay.Centroid?.Location?.Longitude ?? 0);

          if (quayLat && quayLon) {
            features.push({
              type: "Feature",
              geometry: {
                type: "Point",
                coordinates: [quayLon, quayLat],
              },
              tippecanoe: {
                layer: "quays",
                minzoom: 13,
              },
              properties: {
                type: "Quay",
                id: quay.id,
                name:
                  typeof quay.Name === "string" ? quay.Name : quay.Name?.value,
                parentStopPlaceId: place.id,
                parentStopPlaceName:
                  typeof place.Name === "string"
                    ? place.Name
                    : place.Name?.value,
              },
            });
          }
        });
      }
    }
  );

  return {
    type: "FeatureCollection",
    features,
  };
}

function writeGeoJSONToFile(data: any, outputPath: string) {
  fs.writeFileSync(outputPath, JSON.stringify(data, null, 2), "utf-8");
}

function runTippecanoe(
  geojsonPath: string,
  mbtilesPath: string
): Promise<void> {
  return new Promise((resolve, reject) => {
    console.log(`⚙️ Running tippecanoe to generate ${mbtilesPath} ...`);

    const proc = spawn("tippecanoe", ["-o", mbtilesPath, geojsonPath], {
      stdio: "inherit", // pipe output to console
    });

    proc.on("error", (err) => {
      reject(new Error(`Failed to start tippecanoe: ${err.message}`));
    });

    proc.on("close", (code) => {
      if (code === 0) {
        console.log(
          `✅ Tippecanoe finished. MBTiles written to ${mbtilesPath}`
        );
        resolve();
      } else {
        reject(new Error(`tippecanoe exited with code ${code}`));
      }
    });
  });
}

async function main() {
  try {
    const xml = extractXMLFromZip(zipPath);
    const geojson = convertXMLToGeoJSON(xml);
    writeGeoJSONToFile(geojson, outputPath);
    console.log(`✅ GeoJSON written to ${outputPath}`);

    if (argv.mbtiles) {
      await runTippecanoe(outputPath, argv.mbtiles);
    }
  } catch (error) {
    console.error("❌ Error:", (error as Error).message);
  }
}

main();
