import "maplibre-gl/dist/maplibre-gl.css";
import { ComposableMap, Geographies, Geography, ZoomableGroup } from "react-simple-maps";
import { useEffect, useState, useCallback } from "react";

export interface HeatPoint {
    city_name: string;
    province_name: string;
    value: number;
};

interface HeatmapMapProps {
    points: HeatPoint[];
}

interface GadmMapping {
    pancake_province_name: string;
    pancake_district_name: string;
    gadm_province_name: string;
    gadm_district_name: string;
}

export default function HeatmapMap({ points }: HeatmapMapProps) {
    const geoUrl = "/ph_geojson.json"; // accurate city/municipality boundaries
    const [mappingData, setMappingData] = useState<GadmMapping[]>([]);
    const [cityDataMap, setCityDataMap] = useState<Map<string, { value: number; gadmCity: string; gadmProvince: string; pancakeCity: string; pancakeProvince: string }>>(new Map());

    // Normalize pancake names (convert hyphens to spaces for matching)
    const normalizePancakeName = (name: string): string => {
        return name.toLowerCase().replace(/-/g, ' ').trim();
    };

    // Perform partial matching - useful for "Metro-manila" matching "Manila"
    const findPartialMatch = useCallback((normalizedCity: string, normalizedProvince: string) => {
        return mappingData.find(m => {
            const mappedCity = m.pancake_district_name.toLowerCase();
            const mappedProvince = m.pancake_province_name.toLowerCase();

            // Check if city names match (exact or partial)
            const cityMatches = mappedCity === normalizedCity ||
                mappedCity.includes(normalizedCity) ||
                normalizedCity.includes(mappedCity);

            // Check if province names match (exact or partial)
            // Handle "metro manila" vs "manila" case
            let provinceMatches = mappedProvince === normalizedProvince;
            if (!provinceMatches) {
                // Check if "metro" prefix is the only difference
                const provinceVariants = [
                    normalizedProvince.replace(/^metro\s+/, ''),
                    'metro ' + normalizedProvince
                ];
                provinceMatches = provinceVariants.some(variant => variant === mappedProvince);
            }

            return cityMatches && provinceMatches;
        });
    }, [mappingData]);

    // Load the pancake to GADM mapping
    useEffect(() => {
        fetch('/pancake_to_gadm.json')
            .then(res => res.json())
            .then(data => {
                setMappingData(data);
            })
            .catch(err => console.error('Error loading GADM mapping:', err));
    }, []);

    // Create a map using GADM names from the pancake API data
    useEffect(() => {
        if (mappingData.length === 0) return;

        const dataMap = new Map<string, { value: number; gadmCity: string; gadmProvince: string; pancakeCity: string; pancakeProvince: string }>();

        points.forEach(point => {
            if (point.value !== null && point.value !== undefined) {
                const numValue = typeof point.value === 'string' ? parseFloat(point.value) : point.value;
                if (!isNaN(numValue)) {
                    // Normalize the pancake names for matching
                    const normalizedCity = normalizePancakeName(point.city_name);
                    const normalizedProvince = normalizePancakeName(point.province_name);

                    // Try exact match first, then fall back to partial match
                    let mapping = mappingData.find(m =>
                        m.pancake_province_name.toLowerCase() === normalizedProvince &&
                        m.pancake_district_name.toLowerCase() === normalizedCity
                    );

                    // If no exact match, try partial/fuzzy matching
                    if (!mapping) {
                        mapping = findPartialMatch(normalizedCity, normalizedProvince);
                    }

                    if (mapping) {
                        // Use GADM names as the key
                        const key = `${mapping.gadm_district_name}_${mapping.gadm_province_name}`;
                        dataMap.set(key, {
                            value: numValue,
                            gadmCity: mapping.gadm_district_name,
                            gadmProvince: mapping.gadm_province_name,
                            pancakeCity: point.city_name,
                            pancakeProvince: point.province_name
                        });
                    }
                }
            }
        });

        setCityDataMap(dataMap);
    }, [points, mappingData, findPartialMatch]);

    // Function to get city data using GADM names
    const getCityData = (gadmCityName: string, gadmProvinceName: string) => {
        const key = `${gadmCityName}_${gadmProvinceName}`;
        return cityDataMap.get(key) || null;
    };

    // Function to get fill color based on GADM city and province
    const getFillColor = (gadmCityName: string, gadmProvinceName: string): string => {
        const data = getCityData(gadmCityName, gadmProvinceName);

        if (!data) {
            return "#d1d5db"; // default gray for cities without data
        }

        return data.value < 18 ? "#22c55e" : "#ef4444"; // green if < 18, red if >= 18
    };

    return (
        <>
            <ComposableMap projection="geoMercator" projectionConfig={{ scale: 4200, center: [122, 11] }}>
                <ZoomableGroup center={[122, 15]} zoom={0.8}>
                    <Geographies geography={geoUrl}>
                        {({ geographies }) =>
                            geographies.map((geo) => {
                                const gadmCityName = geo.properties.NAME_2; // GADM field for city/municipality name
                                const gadmProvinceName = geo.properties.NAME_1; // GADM field for province name
                                const fillColor = getFillColor(gadmCityName, gadmProvinceName);
                                const cityData = getCityData(gadmCityName, gadmProvinceName);

                                return (
                                    <Geography
                                        key={geo.rsmKey}
                                        geography={geo}
                                        onClick={() => {
                                            if (cityData) {
                                                alert(
                                                    `City: ${cityData.gadmCity}\n` +
                                                    `Province: ${cityData.gadmProvince}\n` +
                                                    `Pancake City: ${cityData.pancakeCity}\n` +
                                                    `Pancake Province: ${cityData.pancakeProvince}\n` +
                                                    `RTS Rate: ${cityData.value} %`
                                                );
                                            } else {
                                                alert(`${gadmCityName}, ${gadmProvinceName}\nNo data available`);
                                            }
                                        }}
                                        style={{
                                            default: {
                                                fill: fillColor,
                                                stroke: "#374151",
                                                strokeWidth: 0.4,
                                            },
                                            hover: {
                                                fill: "#60a5fa",
                                                cursor: "pointer",
                                            },
                                            pressed: {
                                                fill: "#2563eb",
                                            },
                                        }}
                                    />
                                );
                            })
                        }
                    </Geographies>

                </ZoomableGroup>
            </ComposableMap>
        </>
    );
}
