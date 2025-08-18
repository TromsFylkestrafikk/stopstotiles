import fs from "fs";
import path from "path";
import AdmZip from "adm-zip";
import { XMLParser } from "fast-xml-parser";
import yargs from "yargs";
import { hideBin } from "yargs/helpers";

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
  });
  const parsedXml = parser.parse(xml);

  const stopPlaces =
    parsedXml?.PublicationDelivery?.dataObjects?.SiteFrame?.stopPlaces
      ?.StopPlace;

  if (!stopPlaces) {
    throw new Error("No StopPlace elements found.");
  }

  const features = (Array.isArray(stopPlaces) ? stopPlaces : [stopPlaces])
    .map((place: any) => {
      const id = place.id;
      const name = place.Name?.$t || place.Name;
      const lat = parseFloat(place.Centroid?.Location?.Latitude || 0);
      const lon = parseFloat(place.Centroid?.Location?.Longitude || 0);

      if (!lat || !lon) return null; // skip incomplete data

      return {
        type: "Feature",
        geometry: {
          type: "Point",
          coordinates: [lon, lat],
        },
        properties: {
          id,
          name,
          ...place,
        },
      };
    })
    .filter(Boolean);

  return {
    type: "FeatureCollection",
    features,
  };
}

function writeGeoJSONToFile(data: any, outputPath: string) {
  fs.writeFileSync(outputPath, JSON.stringify(data, null, 2), "utf-8");
}

function main() {
  try {
    const xml = extractXMLFromZip(zipPath);
    const geojson = convertXMLToGeoJSON(xml);
    writeGeoJSONToFile(geojson, outputPath);
    console.log(`GeoJSON written to ${outputPath}`);
  } catch (error) {
    console.error("Error:", (error as Error).message);
  }
}

main();
