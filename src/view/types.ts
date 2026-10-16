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
	/**
	 * Its position among the file's <wpt> elements, counting from 0 and
	 * including any left off the map: how a block links it to a post.
	 */
	index: number;
	/** The Waypoint post the file itself links it to, or 0. */
	postId: number;
}

/** A waypoint post's photo, at the size a popup shows it. */
export interface WaypointPhoto {
	src: string;
	width: number;
	height: number;
	alt: string;
	srcset?: string;
}

/**
 * What a map shows for a waypoint linked to a Waypoint post: only what the
 * post changes about the waypoint in the GPX file.
 */
export interface WaypointPost {
	name?: string;
	desc?: string;
	photo?: WaypointPhoto;
	/** Its marker: a photo thumbnail or an icon, else the usual one. */
	marker?: 'image' | 'icon';
	/** The thumbnail of an image marker. */
	thumb?: string;
	/** The Dashicon of an icon marker, without the "dashicons-" prefix. */
	icon?: string;
	color?: string;
	background?: string;
}

export interface ParsedGpx {
	coords: Coord[];
	waypoints: Waypoint[];
	/** Coord index where each trkseg/trk/rte begins (first is always 0). */
	segmentStarts: number[];
	/**
	 * Whether each coordinate's elevation came from the file. One without
	 * reads 0, which the profile and the climb figures take as it is but the
	 * lowest point must not.
	 */
	hasEle: boolean[];
	/** True when the XML failed to parse at all. */
	invalid: boolean;
}
