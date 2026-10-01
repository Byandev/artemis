/**
 * GADM level-2 boundaries for the Philippines (public/ph_geojson.json).
 *
 * The file is ~9MB, so it is fetched once per page load and shared: switching
 * filters, metric or granularity re-shades the polygons already in memory
 * rather than downloading them again.
 */
export interface PhGeoProperties {
    /** Province id, e.g. "PHL.47_1". */
    GID_1: string;
    /** City/municipality id, e.g. "PHL.47.6_1". */
    GID_2: string;
    NAME_1: string;
    NAME_2: string;
}

export interface PhGeoJson {
    type: 'FeatureCollection';
    features: {
        type: 'Feature';
        properties: PhGeoProperties;
        geometry: unknown;
    }[];
}

let pending: Promise<PhGeoJson> | null = null;

export function loadPhGeoJson(): Promise<PhGeoJson> {
    pending ??= fetch('/ph_geojson.json')
        .then((res) => {
            if (!res.ok) {
                throw new Error(
                    `Failed to load map boundaries (${res.status})`,
                );
            }
            return res.json() as Promise<PhGeoJson>;
        })
        .catch((error) => {
            // Let the next mount retry rather than caching the failure forever.
            pending = null;
            throw error;
        });

    return pending;
}
