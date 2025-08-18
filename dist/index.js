"use strict";
var __assign = (this && this.__assign) || function () {
    __assign = Object.assign || function(t) {
        for (var s, i = 1, n = arguments.length; i < n; i++) {
            s = arguments[i];
            for (var p in s) if (Object.prototype.hasOwnProperty.call(s, p))
                t[p] = s[p];
        }
        return t;
    };
    return __assign.apply(this, arguments);
};
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
var fs_1 = __importDefault(require("fs"));
var adm_zip_1 = __importDefault(require("adm-zip"));
var fast_xml_parser_1 = require("fast-xml-parser");
var yargs_1 = __importDefault(require("yargs"));
var helpers_1 = require("yargs/helpers");
// CLI setup
var argv = (0, yargs_1.default)((0, helpers_1.hideBin)(process.argv))
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
    .help().argv;
var zipPath = argv.input;
var outputPath = argv.output;
function extractXMLFromZip(zipFilePath) {
    var zip = new adm_zip_1.default(zipFilePath);
    var zipEntries = zip.getEntries();
    var xmlEntry = zipEntries.find(function (entry) { return entry.entryName.endsWith(".xml"); });
    if (!xmlEntry) {
        throw new Error("No XML file found in the ZIP archive.");
    }
    return zip.readAsText(xmlEntry);
}
function convertXMLToGeoJSON(xml) {
    var _a, _b, _c, _d;
    var parser = new fast_xml_parser_1.XMLParser({
        ignoreAttributes: false,
        attributeNamePrefix: "",
    });
    var parsedXml = parser.parse(xml);
    var stopPlaces = (_d = (_c = (_b = (_a = parsedXml === null || parsedXml === void 0 ? void 0 : parsedXml.PublicationDelivery) === null || _a === void 0 ? void 0 : _a.dataObjects) === null || _b === void 0 ? void 0 : _b.SiteFrame) === null || _c === void 0 ? void 0 : _c.stopPlaces) === null || _d === void 0 ? void 0 : _d.StopPlace;
    if (!stopPlaces) {
        throw new Error("No StopPlace elements found.");
    }
    var features = (Array.isArray(stopPlaces) ? stopPlaces : [stopPlaces])
        .map(function (place) {
        var _a, _b, _c, _d, _e;
        var id = place.id;
        var name = ((_a = place.Name) === null || _a === void 0 ? void 0 : _a.$t) || place.Name;
        var lat = parseFloat(((_c = (_b = place.Centroid) === null || _b === void 0 ? void 0 : _b.Location) === null || _c === void 0 ? void 0 : _c.Latitude) || 0);
        var lon = parseFloat(((_e = (_d = place.Centroid) === null || _d === void 0 ? void 0 : _d.Location) === null || _e === void 0 ? void 0 : _e.Longitude) || 0);
        if (!lat || !lon)
            return null; // skip incomplete data
        return {
            type: "Feature",
            geometry: {
                type: "Point",
                coordinates: [lon, lat],
            },
            properties: __assign({ id: id, name: name }, place),
        };
    })
        .filter(Boolean);
    return {
        type: "FeatureCollection",
        features: features,
    };
}
function writeGeoJSONToFile(data, outputPath) {
    fs_1.default.writeFileSync(outputPath, JSON.stringify(data, null, 2), "utf-8");
}
function main() {
    try {
        var xml = extractXMLFromZip(zipPath);
        var geojson = convertXMLToGeoJSON(xml);
        writeGeoJSONToFile(geojson, outputPath);
        console.log("GeoJSON written to ".concat(outputPath));
    }
    catch (error) {
        console.error("Error:", error.message);
    }
}
main();
