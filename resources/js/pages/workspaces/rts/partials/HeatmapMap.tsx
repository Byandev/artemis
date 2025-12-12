import "maplibre-gl/dist/maplibre-gl.css";
import { ComposableMap, Geographies, Geography, ZoomableGroup } from "react-simple-maps";
import { useEffect, useState, useCallback } from "react";
import _ from "lodash";

import CityInformationDialog from "./CityInformationDialog";

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
    const geoUrl = "/ph_geojson.json";

    const [mappingData, setMappingData] = useState<GadmMapping[]>([]);
    const [cityDataMap, setCityDataMap] = useState<
        Map<string, { value: number; gadmCity: string; gadmProvince: string; pancakeCity: string; pancakeProvince: string }>
    >(new Map());

    // Dialog State
    const [open, setOpen] = useState(false);
    const [selectedCity, setSelectedCity] = useState<{
        city: string;
        province: string;
        value: number;
        hasData: boolean;
    } | null>(null);

    // Normalize pancake names
    const normalizePancakeName = (name: string): string =>
        name.toLowerCase().replace(/-/g, " ").trim();

    const findPartialMatch = useCallback(
        (normalizedCity: string, normalizedProvince: string) => {
            return mappingData.find((m) => {
                const mappedCity = m.pancake_district_name.toLowerCase();
                const mappedProvince = m.pancake_province_name.toLowerCase();

                const cityMatches =
                    mappedCity === normalizedCity ||
                    mappedCity.includes(normalizedCity) ||
                    normalizedCity.includes(mappedCity);

                let provinceMatches = mappedProvince === normalizedProvince;
                if (!provinceMatches) {
                    const variants = [
                        normalizedProvince.replace(/^metro\s+/, ""),
                        "metro " + normalizedProvince,
                    ];
                    provinceMatches = variants.some((v) => v === mappedProvince);
                }

                return cityMatches && provinceMatches;
            });
        },
        [mappingData]
    );

    // Load mapping file
    useEffect(() => {
        fetch("/pancake_to_gadm.json")
            .then((res) => res.json())
            .then(setMappingData)
            .catch((err) => console.error("Error loading GADM mapping:", err));
    }, []);

    // Build data map
    useEffect(() => {
        if (mappingData.length === 0) return;

        const dataMap = new Map();

        //let matchedCount = 0;
        //let unmatchedCount = 0;

        points.forEach((point) => {
            const numValue =
                typeof point.value === "string" ? parseFloat(point.value) : point.value;

            if (!isNaN(numValue)) {
                const normalizedCity = normalizePancakeName(point.city_name);
                const normalizedProvince = normalizePancakeName(point.province_name);

                let mapping = mappingData.find(
                    (m) =>
                        m.pancake_province_name.toLowerCase() === normalizedProvince &&
                        m.pancake_district_name.toLowerCase() === normalizedCity
                );

                if (!mapping) {
                    mapping = findPartialMatch(normalizedCity, normalizedProvince);
                }

                if (mapping) {
                    const key = `${mapping.gadm_district_name}_${mapping.gadm_province_name}`;
                    dataMap.set(key, {
                        value: numValue,
                        gadmCity: mapping.gadm_district_name,
                        gadmProvince: mapping.gadm_province_name,
                        pancakeCity: point.city_name,
                        pancakeProvince: point.province_name,
                    });
                    //matchedCount++;
                } else {
                    //unmatchedCount++;
                    console.log(
                        `Unmatched point: ${point.city_name}, ${point.province_name}`
                    );
                }
            }
        });

        setCityDataMap(dataMap);

        //Logs to help view matching accuracy
        //console.log(`Matched points: ${matchedCount}`);
        //console.log(`Unmatched points: ${unmatchedCount}`);
    }, [points, mappingData, findPartialMatch]);


    const getCityData = (gadmCityName: string, gadmProvinceName: string) => {
        const key = `${gadmCityName}_${gadmProvinceName}`;
        return cityDataMap.get(key) || null;
    };

    const getFillColor = (gadmCityName: string, gadmProvinceName: string): string => {
        const data = getCityData(gadmCityName, gadmProvinceName);
        return !data ? "#d1d5db" : data.value < 18 ? "#22c55e" : "#ef4444";
    };

    return (
        <>
            <ComposableMap projection="geoMercator" projectionConfig={{ scale: 4200, center: [122, 11] }}>
                <ZoomableGroup center={[122, 15]} zoom={0.8}>
                    <Geographies geography={geoUrl}>
                        {({ geographies }) =>
                            geographies.map((geo) => {
                                const city = geo.properties.NAME_2;
                                const province = geo.properties.NAME_1;
                                const color = getFillColor(city, province);
                                const cityData = getCityData(city, province);

                                return (
                                    <Geography
                                        key={geo.rsmKey}
                                        geography={geo}
                                        onClick={() => {
                                            if (cityData) {
                                                setSelectedCity({
                                                    city: _.startCase(_.toLower(cityData.pancakeCity)),
                                                    province: _.startCase(_.toLower(cityData.pancakeProvince)),
                                                    value: cityData.value,
                                                    hasData: true,
                                                });
                                            } else {
                                                setSelectedCity({
                                                    city,
                                                    province,
                                                    value: -1,
                                                    hasData: false,
                                                });
                                            }

                                            setOpen(true);
                                        }}
                                        style={{
                                            default: {
                                                fill: color,
                                                stroke: "#374151",
                                                strokeWidth: 0.4,
                                            },
                                            hover: { fill: "#60a5fa", cursor: "pointer" },
                                            pressed: { fill: "#2563eb" },
                                        }}
                                    />
                                );
                            })
                        }
                    </Geographies>
                </ZoomableGroup>
            </ComposableMap>

            <CityInformationDialog
                open={open}
                onOpenChange={setOpen}
                selectedCity={selectedCity}
            />
        </>
    );
}
