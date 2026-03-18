import 'maplibre-gl/dist/maplibre-gl.css';
import {
    ComposableMap,
    createCoordinates,
    Geographies,
    Geography,
    ZoomableGroup,
} from '@vnedyalk0v/react19-simple-maps';
import { useCallback, useEffect, useState } from 'react';
import _ from 'lodash';
import CityInformationDialog from './CityInformationDialog';

export interface HeatPoint {
    city_name: string | null;
    province_name: string | null;
    value: number | string | null;
}

interface HeatmapMapProps {
    points: HeatPoint[];
}

interface GadmMapping {
    pancake_province_name: string | null;
    pancake_district_name: string | null;
    gadm_province_name: string | null;
    gadm_district_name: string | null;
}

interface CityData {
    value: number;
    gadmCity: string;
    gadmProvince: string;
    pancakeCity: string;
    pancakeProvince: string;
}

export default function HeatmapMap({ points }: HeatmapMapProps) {
    const [geoData, setGeoData] = useState<any | null>(null);
    const [mappingData, setMappingData] = useState<GadmMapping[]>([]);
    const [cityDataMap, setCityDataMap] = useState<Map<string, CityData>>(
        new Map(),
    );

    const [open, setOpen] = useState(false);
    const [selectedCity, setSelectedCity] = useState<{
        city: string;
        province: string;
        value: number;
        hasData: boolean;
    } | null>(null);

    const normalizePancakeName = (name?: string | null): string =>
        (name ?? '')
            .toLowerCase()
            .replace(/-/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

    useEffect(() => {
        fetch('/ph_geojson.json')
            .then((res) => res.json())
            .then((data) => setGeoData(data))
            .catch((err) => console.error('Error loading geojson:', err));
    }, []);

    useEffect(() => {
        fetch('/pancake_to_gadm.json')
            .then((res) => res.json())
            .then(setMappingData)
            .catch((err) => console.error('Error loading GADM mapping:', err));
    }, []);

    const findPartialMatch = useCallback(
        (normalizedCity: string, normalizedProvince: string) => {
            return mappingData.find((m) => {
                const mappedCity = normalizePancakeName(m.pancake_district_name);
                const mappedProvince = normalizePancakeName(
                    m.pancake_province_name,
                );

                if (!mappedCity || !mappedProvince) {
                    return false;
                }

                const cityMatches =
                    mappedCity === normalizedCity ||
                    mappedCity.includes(normalizedCity) ||
                    normalizedCity.includes(mappedCity);

                let provinceMatches = mappedProvince === normalizedProvince;

                if (!provinceMatches) {
                    const variants = [
                        normalizedProvince.replace(/^metro\s+/, ''),
                        `metro ${normalizedProvince}`,
                    ];
                    provinceMatches = variants.some((v) => v === mappedProvince);
                }

                return cityMatches && provinceMatches;
            });
        },
        [mappingData],
    );

    useEffect(() => {
        if (mappingData.length === 0) return;

        const dataMap = new Map<string, CityData>();

        points.forEach((point) => {
            const numValue =
                typeof point.value === 'string'
                    ? parseFloat(point.value)
                    : Number(point.value);

            if (!Number.isFinite(numValue)) {
                return;
            }

            const normalizedCity = normalizePancakeName(point.city_name);
            const normalizedProvince = normalizePancakeName(point.province_name);

            if (!normalizedCity || !normalizedProvince) {
                console.log('Invalid point:', point);
                return;
            }

            let mapping = mappingData.find(
                (m) =>
                    normalizePancakeName(m.pancake_province_name) ===
                    normalizedProvince &&
                    normalizePancakeName(m.pancake_district_name) ===
                    normalizedCity,
            );

            if (!mapping) {
                mapping = findPartialMatch(normalizedCity, normalizedProvince);
            }

            const gadmCity = normalizePancakeName(mapping?.gadm_district_name);
            const gadmProvince = normalizePancakeName(
                mapping?.gadm_province_name,
            );

            if (mapping && gadmCity && gadmProvince) {
                const key = `${mapping.gadm_district_name}_${mapping.gadm_province_name}`;

                dataMap.set(key, {
                    value: numValue,
                    gadmCity: mapping.gadm_district_name!,
                    gadmProvince: mapping.gadm_province_name!,
                    pancakeCity: point.city_name!,
                    pancakeProvince: point.province_name!,
                });
            } else {
                console.log(
                    `Unmatched point: ${point.city_name ?? 'N/A'}, ${
                        point.province_name ?? 'N/A'
                    }`,
                );
            }
        });

        setCityDataMap(dataMap);
    }, [points, mappingData, findPartialMatch]);

    const getCityData = (
        gadmCityName?: string | null,
        gadmProvinceName?: string | null,
    ) => {
        if (!gadmCityName || !gadmProvinceName) {
            return null;
        }

        const key = `${gadmCityName}_${gadmProvinceName}`;
        return cityDataMap.get(key) || null;
    };

    const getFillColor = (
        gadmCityName?: string | null,
        gadmProvinceName?: string | null,
    ): string => {
        const data = getCityData(gadmCityName, gadmProvinceName);

        if (!data) return '#d1d5db';
        if (data.value < 18) return '#22c55e';

        return '#ef4444';
    };

    return (
        <>
            {geoData ? (
                <ComposableMap
                    projection="geoMercator"
                    projectionConfig={{
                        scale: 4200,
                        center: createCoordinates(122, 12),
                    }}
                >
                    <ZoomableGroup
                        center={createCoordinates(122, 15)}
                        zoom={0.8}
                    >
                        <Geographies geography={geoData}>
                            {({ geographies }) =>
                                geographies.map((geo, index) => {
                                    const city = geo?.properties?.NAME_2 ?? '';
                                    const province =
                                        geo?.properties?.NAME_1 ?? '';
                                    const color = getFillColor(city, province);
                                    const cityData = getCityData(city, province);

                                    return (
                                        <Geography
                                            key={`${province}_${city}_${index}`}
                                            geography={geo}
                                            onClick={() => {
                                                if (cityData) {
                                                    setSelectedCity({
                                                        city: _.startCase(
                                                            _.toLower(
                                                                cityData.pancakeCity,
                                                            ),
                                                        ),
                                                        province: _.startCase(
                                                            _.toLower(
                                                                cityData.pancakeProvince,
                                                            ),
                                                        ),
                                                        value: cityData.value,
                                                        hasData: true,
                                                    });
                                                } else {
                                                    setSelectedCity({
                                                        city: city || 'Unknown',
                                                        province:
                                                            province ||
                                                            'Unknown',
                                                        value: -1,
                                                        hasData: false,
                                                    });
                                                }

                                                setOpen(true);
                                            }}
                                            style={{
                                                default: {
                                                    fill: color,
                                                    stroke: '#374151',
                                                    strokeWidth: 0.4,
                                                },
                                                hover: {
                                                    fill: '#60a5fa',
                                                    cursor: 'pointer',
                                                },
                                                pressed: {
                                                    fill: '#2563eb',
                                                },
                                            }}
                                        />
                                    );
                                })
                            }
                        </Geographies>
                    </ZoomableGroup>
                </ComposableMap>
            ) : (
                <div className="flex h-[500px] items-center justify-center text-sm text-gray-500">
                    Loading map...
                </div>
            )}

            <CityInformationDialog
                open={open}
                onOpenChange={setOpen}
                selectedCity={selectedCity}
            />
        </>
    );
}
