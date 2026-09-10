import {
    ComposableMap,
    createCoordinates,
    createGraticuleStep,
    Geographies,
    Geography,
    Graticule,
} from '@vnedyalk0v/react19-simple-maps';
import { memo } from 'react';
import { BORDER_COLOR, NO_DATA_COLOR } from './color-scale';
import type { PhGeoJson } from './ph-geo';
import type { HeatRow } from './types';

interface Props {
    geoData: PhGeoJson;
    /** Keyed by GID_1. Regions are expanded to one entry per province polygon
     * before they get here, so every grain looks the same to the canvas. */
    rowsByGid: Map<string, HeatRow>;
    colorFor: (row: HeatRow) => string;
    isDark: boolean;
    /** Latitude/longitude grid, as a printed map would carry. */
    graticule?: boolean;
    onEnter: (row: HeatRow | null, x: number, y: number) => void;
    onMove: (x: number, y: number) => void;
    onLeave: () => void;
}

/**
 * The country renders as ~1,650 SVG paths, so this is memoised and every prop it
 * takes is a stable reference: hovering updates the tooltip in the parent without
 * re-rendering a single polygon. The hover highlight itself is handled by the
 * library's `style.hover`, which never touches React state.
 *
 * Projection is tuned to the Philippine bounding box (roughly 117–127°E,
 * 4.6–21.1°N) so the country fills the viewBox with a small margin. The map is
 * fixed — no zoom, no pan — so what everyone sees is the same framing.
 */
function HeatMapCanvas({
    geoData,
    rowsByGid,
    colorFor,
    isDark,
    graticule = true,
    onEnter,
    onMove,
    onLeave,
}: Props) {
    const noData = isDark ? NO_DATA_COLOR.dark : NO_DATA_COLOR.light;
    const noDataEdge = isDark ? BORDER_COLOR.dark : BORDER_COLOR.light;
    const gridLine = isDark ? '#3f3f46' : '#dcdce3';
    const gridInk = isDark ? '#8b8b94' : '#8f8a85';

    return (
        <ComposableMap
            width={680}
            height={900}
            projection="geoMercator"
            projectionConfig={{
                scale: 2950,
                center: createCoordinates(121.75, 13),
            }}
            className="h-full w-full"
        >
            {graticule && (
                <Graticule
                    step={createGraticuleStep(5, 5)}
                    fill="none"
                    stroke={gridLine}
                    strokeWidth={0.5}
                />
            )}

            <Geographies geography={geoData as never}>
                {({ geographies }) =>
                    geographies.map((geo) => {
                        const props = geo.properties as {
                            GID_1: string;
                            GID_2: string;
                        };
                        const row = rowsByGid.get(props.GID_1) ?? null;
                        const fill = row ? colorFor(row) : noData;

                        return (
                            <Geography
                                key={props.GID_2}
                                geography={geo}
                                onMouseEnter={(event) =>
                                    onEnter(row, event.clientX, event.clientY)
                                }
                                onMouseMove={(event) =>
                                    onMove(event.clientX, event.clientY)
                                }
                                onMouseLeave={onLeave}
                                style={{
                                    default: {
                                        fill,
                                        // Shaded provinces merge into one shape;
                                        // unshaded ones keep their borders, so the
                                        // untracked area reads as a field of
                                        // provinces rather than one grey blob.
                                        stroke: row ? fill : noDataEdge,
                                        strokeWidth: 0.3,
                                        outline: 'none',
                                    },
                                    hover: {
                                        fill,
                                        stroke: isDark ? '#fafafa' : '#111827',
                                        strokeWidth: 0.8,
                                        outline: 'none',
                                        cursor: 'pointer',
                                    },
                                    pressed: {
                                        fill,
                                        outline: 'none',
                                    },
                                }}
                            />
                        );
                    })
                }
            </Geographies>

            {graticule && (
                <Geographies geography={geoData as never}>
                    {({ projection }) => (
                        <g
                            pointerEvents="none"
                            fill={gridInk}
                            fontSize={7.5}
                            fontWeight={500}
                        >
                            {[115, 120, 125, 130].map((lon) => {
                                const at = projection([lon, 4.2]);
                                if (!at) return null;

                                return (
                                    <text
                                        key={`lon-${lon}`}
                                        x={at[0]}
                                        y={at[1]}
                                        textAnchor="middle"
                                    >
                                        {lon}°E
                                    </text>
                                );
                            })}
                            {[5, 10, 15, 20].map((lat) => {
                                const at = projection([116.2, lat]);
                                if (!at) return null;

                                return (
                                    <text
                                        key={`lat-${lat}`}
                                        x={at[0]}
                                        y={at[1]}
                                        textAnchor="end"
                                        dominantBaseline="middle"
                                    >
                                        {lat}°N
                                    </text>
                                );
                            })}
                        </g>
                    )}
                </Geographies>
            )}
        </ComposableMap>
    );
}

export default memo(HeatMapCanvas);
