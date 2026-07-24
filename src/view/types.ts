/**
 * Shared domain types for the map view stack.
 */

import type * as MapLibreNs from 'maplibre-gl';

/** The MapLibre GL module object as resolved by the lazy loader. */
export type MapLibreGl = typeof MapLibreNs;

/** A track/route coordinate as [lon, lat, ele]. */
export type Coord = [ number, number, number ];

/** [[minLon, minLat], [maxLon, maxLat]] bounding box. */
export type Bounds = [ [ number, number ], [ number, number ] ];

export interface Waypoint {
	lon: number;
	lat: number;
	name: string;
	desc: string;
	type: string;
	link: string;
}

export interface ParsedGpx {
	coords: Coord[];
	waypoints: Waypoint[];
	/** Coord index where each trkseg/trk/rte begins (first is always 0). */
	segmentStarts: number[];
	/** True when the XML failed to parse at all. */
	invalid: boolean;
}
