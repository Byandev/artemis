import {
    ComposableMap,
    createCoordinates,
    Geographies,
    Geography,
    ZoomableGroup,
} from '@vnedyalk0v/react19-simple-maps';
import { memo } from 'react';
import { NO_DATA_COLOR } from './color-scale';
import type { PhGeoJson } from './ph-geo';
import type { HeatRow } from './types';

interface Props {
    geoData: PhGeoJson;
    /** Keyed by GID_1. Regions are expanded to one entry per province polygon
     * before they get here, so every grain looks the same to the canvas. */
    rowsByGid: Map<string, HeatRow>;
    colorFor: (row: HeatRow) => string;
    isDark: boolean;
    zoom: number;
    center: [number, number];
    onMoveEnd: (position: {
        coordinates: [number, number];
        zoom: number;
    }) => void;
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
 * 4.6–21.1°N) so the country fills the viewBox with a small margin.
 */
function HeatMapCanvas({
    geoData,
    rowsByGid,
    colorFor,
    isDark,
    zoom,
    center,
    onMoveEnd,
    onEnter,
    onMove,
    onLeave,
}: Props) {
    const noData = isDark ? NO_DATA_COLOR.dark : NO_DATA_COLOR.light;

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
            <ZoomableGroup
                zoom={zoom}
                center={createCoordinates(center[0], center[1])}
                minZoom={1}
                maxZoom={12}
                onMoveEnd={(position) =>
                    onMoveEnd({
                        coordinates: [
                            position.coordinates[0],
                            position.coordinates[1],
                        ],
                        zoom: position.zoom,
                    })
                }
            >
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
                                        onEnter(
                                            row,
                                            event.clientX,
                                            event.clientY,
                                        )
                                    }
                                    onMouseMove={(event) =>
                                        onMove(event.clientX, event.clientY)
                                    }
                                    onMouseLeave={onLeave}
                                    style={{
                                        default: {
                                            fill,
                                            // Municipal borders are hidden so each
                                            // province reads as a single shape.
                                            stroke: fill,
                                            strokeWidth: 0.3,
                                            outline: 'none',
                                        },
                                        hover: {
                                            fill,
                                            stroke: isDark
                                                ? '#fafafa'
                                                : '#111827',
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
            </ZoomableGroup>
        </ComposableMap>
    );
}

export default memo(HeatMapCanvas);
