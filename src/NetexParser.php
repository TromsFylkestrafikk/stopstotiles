<?php

namespace App;

use RuntimeException;
use SimpleXMLElement;

class NetexParser
{
    private array $ns = ["n" => "http://www.netex.org.uk/netex"];
    private array $categories = [];
    private array $layers = [];
    private array $layerMinZoom = [
        "major" => 8,
        "medium" => 10,
        "minor" => 12,
        "quays" => 14
    ];

    public function __construct()
    {
        $grouped = [
            "train"         => ["railStation", "onstreetTram", "metroStation"],
            "bus"           => ["onstreetBus", "busStation"],
            "water"         => ["harbourPort", "ferryStop"],
            "liftStation"   => ["liftStation"],
            "airport"       => ["airport"],
            "taxiStand"     => ["taxiStand"]
        ];

        $groupedLayers = [
            "major"     => ["harbourPort", "airport"],
            "medium"    => ["railStation", "liftStation", "ferryStop", "metroStation", "busStation", "taxiStand"],
            "minor"     => ["onstreetTram", "onstreetBus", "taxiStand"]
        ];

        $this->categories = array_merge(
            ...array_map(
                fn($category, $items) => array_fill_keys($items, $category),
                array_keys($grouped),
                $grouped
            )
        );

        $this->layers = array_merge(
            ...array_map(
                fn($layer, $items) => array_fill_keys($items, $layer),
                array_keys($groupedLayers),
                $groupedLayers
            )
        );
    }

    public function parse(string $xml): array
    {
        $root = simplexml_load_string($xml);
        if (!$root) {
            throw new RuntimeException("Failed to parse XML.");
        }

        $siteFrame = $root->children($this->ns['n'])
            ->dataObjects->children($this->ns['n'])
            ->SiteFrame;

        if (!$siteFrame) {
            return [];
        }

        $stopPlaces = $siteFrame->children($this->ns['n'])
            ->stopPlaces->children($this->ns['n']);

        if (!$stopPlaces) {
            return [];
        }

        $features = [];
        foreach ($stopPlaces->StopPlace as $place) {
            foreach ($this->parseStopPlace($place) as $f) {
                $features[] = $f;
            }
        }

        return $features;
    }

    private function parseStopPlace(SimpleXMLElement $place): array
    {
        $features = [];
        $attrs = $place->attributes();
        $id = (string)($attrs['id'] ?? '');

        $isParent = false;
        $keyList = $place->children($this->ns['n'])->keyList ?? null;
        if ($keyList) {
            foreach ($keyList->KeyValue as $kv) {
                if (
                    (string)($kv->Key ?? '') === "IS_PARENT_STOP_PLACE" &&
                    strtolower((string)($kv->Value ?? '')) === "true"
                ) {
                    $isParent = true;
                }
            }
        }
        if ($isParent) {
            return [];
        }

        $centroid = $place->children($this->ns['n'])->Centroid->children($this->ns['n'])->Location;
        $lat = (float)($centroid->children($this->ns['n'])->Latitude ?? 0);
        $lon = (float)($centroid->children($this->ns['n'])->Longitude ?? 0);
        $type = (string)$place->children($this->ns['n'])->StopPlaceType;
        $stopPlaceCategory = $this->categories[$type] ?? "other";
        $stopPlaceLayer = $this->layers[$type] ?? "minor";
        $transportMode = (string)$place->children($this->ns['n'])->TransportMode;

        if ($lat && $lon) {
            // Magic numer 12 = default zoom
            $minzoom = $this->layerMinZoom[$stopPlaceLayer] ?? 12;
            $features[] = [
                "type" => "Feature",
                "geometry" => ["type" => "Point", "coordinates" => [$lon, $lat]],
                "tippecanoe" => ["layer" => $stopPlaceLayer, "minzoom" => $minzoom],
                "properties" => [
                    "type" => "StopPlace",
                    "id"   => $id,
                    "name" => (string)$place->children($this->ns['n'])->Name,
                    "transportMode" => $transportMode,
                    "stopPlaceType" => $type,
                    "stopPlaceCategory" => $stopPlaceCategory,
                ],
            ];
        }

        $quaysNode = $place->children($this->ns['n'])->quays ?? null;
        if ($quaysNode) {
            foreach ($quaysNode->children($this->ns['n'])->Quay ?? [] as $quay) {
                foreach ($this->parseQuay($quay, $id) as $qf) {
                    $features[] = $qf;
                }
            }
        }

        return $features;
    }

    private function parseQuay(SimpleXMLElement $quay, string $parentId): array
    {
        $attrs = $quay->attributes();
        $id = (string)($attrs['id'] ?? '');

        $centroid = $quay->children($this->ns['n'])->Centroid->children($this->ns['n'])->Location;
        $lat = (float)($centroid->children($this->ns['n'])->Latitude ?? 0);
        $lon = (float)($centroid->children($this->ns['n'])->Longitude ?? 0);

        if (!$lat || !$lon) {
            return [];
        }

        return [[
            "type" => "Feature",
            "geometry" => ["type" => "Point", "coordinates" => [$lon, $lat]],
            "tippecanoe" => ["layer" => "quays", "minzoom" => $this->layerMinZoom['quays']],
            "properties" => [
                "type" => "Quay",
                "id"   => $id,
                "parentStopPlaceId" => $parentId,
            ],
        ]];
    }
}
