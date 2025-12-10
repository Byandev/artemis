import "maplibre-gl/dist/maplibre-gl.css";
import { ComposableMap, Geographies, Geography, ZoomableGroup } from "react-simple-maps";

export interface HeatPoint {
    city_name: string;
    province_name: string;
    value: number;
};

interface HeatmapMapProps {
    points: HeatPoint[];
}

export default function HeatmapMap({ points }: HeatmapMapProps) {
    const geoUrl = "/ph_geojson.json"; // accurate city/municipality boundaries

    // Normalize name for matching (remove spaces and special characters)
    const normalize = (name: string): string => {
        return name.toLowerCase()
            .replace(/\s+/g, '')  // remove spaces
            .replace(/[^a-z0-9]/g, ''); // remove special characters
    };

    // Create a map of city to their data for quick lookup
    const cityDataMap = new Map<string, { value: number; province: string; cityName: string; provinceName: string }>();
    points.forEach(point => {
        if (point.value !== null && point.value !== undefined) {
            const numValue = typeof point.value === 'string' ? parseFloat(point.value) : point.value;
            if (!isNaN(numValue)) {
                const normalizedCity = normalize(point.city_name);
                const normalizedProvince = normalize(point.province_name);
                cityDataMap.set(normalizedCity, {
                    value: numValue,
                    province: normalizedProvince,
                    cityName: point.city_name,
                    provinceName: point.province_name
                });
            }
        }
    });

    // Function to get city data based on city and province
    const getCityData = (cityName: string, provinceName: string) => {
        const normalizedCity = normalize(cityName);
        const normalizedProvince = normalize(provinceName);

        // First, try exact match
        const exactData = cityDataMap.get(normalizedCity);
        if (exactData && exactData.province === normalizedProvince) {
            return exactData;
        }

        // Second, try partial match (e.g., "manila" in "metropolitanmanila")
        if (exactData && normalizedProvince.includes(exactData.province)) {
            return exactData;
        }

        // Third, try reverse partial match (e.g., data has "metropolitanmanila", geo has "manila")
        if (exactData && exactData.province.includes(normalizedProvince)) {
            return exactData;
        }

        return null;
    };

    // Function to get fill color based on city and province value
    const getFillColor = (cityName: string, provinceName: string): string => {
        const data = getCityData(cityName, provinceName);

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
                                const cityName = geo.properties.NAME_2; // GADM field for city/municipality name
                                const provinceName = geo.properties.NAME_1; // GADM field for province name
                                const fillColor = getFillColor(cityName, provinceName);
                                const cityData = getCityData(cityName, provinceName);

                                return (
                                    <Geography
                                        key={geo.rsmKey}
                                        geography={geo}
                                        onClick={() => {
                                            if (cityData) {
                                                alert(
                                                    `City: ${cityData.cityName}\n` +
                                                    `Province: ${cityData.provinceName}\n` +
                                                    `RTS Rate: ${cityData.value} %`
                                                );
                                            } else {
                                                alert(`${cityName}, ${provinceName}\nNo data available`);
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
    )
}
