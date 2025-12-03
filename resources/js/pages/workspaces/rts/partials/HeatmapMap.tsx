import { useEffect, useRef } from "react";
import maplibregl from "maplibre-gl/dist/maplibre-gl.js";
import "maplibre-gl/dist/maplibre-gl.css";

export interface HeatPoint {
    coordinates: {
        lat: number;
        lng: number;
    };
    value: number;
};

interface HeatmapMapProps {
    points: HeatPoint[];
}

export default function HeatmapMap({ points }: HeatmapMapProps) {
    const mapRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const map = new maplibregl.Map({
            container: mapRef.current!,
            style: "https://demotiles.maplibre.org/style.json",
            center: [120.9842, 14.5995],
            zoom: 5,
        });

        map.on("load", () => {
            map.addSource("heat-data", {
                type: "geojson",
                data: {
                    type: "FeatureCollection",
                    features: points.map((p) => ({
                        type: "Feature",
                        geometry: { type: "Point", coordinates: [p.coordinates.lng, p.coordinates.lat] },
                        properties: { value: p.value },
                    })),
                },
            });

            map.addLayer({
                id: "heatmap",
                type: "heatmap",
                source: "heat-data",
                paint: {
                    "heatmap-weight": ["get", "value"],
                    "heatmap-intensity": 1.2,
                    "heatmap-radius": 30,
                    "heatmap-opacity": 0.8,
                },
            });
        });

        return () => map.remove();
    }, [points]);

    return <div ref={mapRef} style={{ height: "400px" }} />;
}
