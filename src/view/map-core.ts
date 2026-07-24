/**
 * Framework-agnostic map helpers: GPX parsing, bounds, MapLibre style/markers.
 */

import type * as MapLibreNs from 'maplibre-gl';

import type { Bounds, Coord, MapLibreGl, ParsedGpx, Waypoint } from './types';

type MapLibreMap = MapLibreNs.Map;
type StyleSpecification = MapLibreNs.StyleSpecification;
type LngLatBoundsLike = MapLibreNs.LngLatBoundsLike;

export const DEFAULT_TILE_URL =
	'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
export const DEFAULT_ATTRIBUTION =
	'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

export const TRACK_COLOR = '#2e7d32';
export const TRACK_CASING = '#1b3a1e';

/**
 * Parse a GPX document string into track coordinates and waypoints.
 *
 * @param xmlText Raw GPX XML.
 */
export function parseGPX( xmlText: string ): ParsedGpx {
	const doc = new window.DOMParser().parseFromString(
		xmlText,
		'application/xml'
	);

	if ( doc.querySelector( 'parsererror' ) ) {
		return { coords: [], waypoints: [], segmentStarts: [], invalid: true };
	}

	let points = doc.querySelectorAll( 'trkpt' );
	if ( ! points.length ) {
		points = doc.querySelectorAll( 'rtept' );
	}

	const coords: Coord[] = [];
	const segmentStarts: number[] = [];
	let lastSegment: Element | null = null;
	points.forEach( ( pt ) => {
		const lat = parseFloat( pt.getAttribute( 'lat' ) ?? '' );
		const lon = parseFloat( pt.getAttribute( 'lon' ) ?? '' );
		if ( Number.isNaN( lat ) || Number.isNaN( lon ) ) {
			return;
		}
		const segment = pt.closest( 'trkseg, trk, rte' );
		if ( ! coords.length || segment !== lastSegment ) {
			segmentStarts.push( coords.length );
		}
		lastSegment = segment;
		const eleEl = pt.querySelector( 'ele' );
		const ele = eleEl ? parseFloat( eleEl.textContent ?? '' ) : 0;
		coords.push( [ lon, lat, Number.isNaN( ele ) ? 0 : ele ] );
	} );

	const waypoints: Waypoint[] = [];
	doc.querySelectorAll( 'wpt' ).forEach( ( w ) => {
		const lat = parseFloat( w.getAttribute( 'lat' ) ?? '' );
		const lon = parseFloat( w.getAttribute( 'lon' ) ?? '' );
		if ( Number.isNaN( lat ) || Number.isNaN( lon ) ) {
			return;
		}
		waypoints.push( {
			lon,
			lat,
			name: w.querySelector( 'name' )?.textContent || 'Waypoint',
			desc: w.querySelector( 'desc' )?.textContent || '',
			type: w.querySelector( 'type' )?.textContent || '',
			link: w.querySelector( 'link' )?.getAttribute( 'href' ) || '',
		} );
	} );

	return { coords, waypoints, segmentStarts, invalid: false };
}

/**
 * Compute a bounding box from lon/lat points.
 *
 * @param points Points.
 */
export function computeBounds(
	points: Array< { lon: number; lat: number } >
): Bounds {
	return points.reduce< Bounds >(
		( b, p ) => {
			b[ 0 ][ 0 ] = Math.min( b[ 0 ][ 0 ], p.lon );
			b[ 0 ][ 1 ] = Math.min( b[ 0 ][ 1 ], p.lat );
			b[ 1 ][ 0 ] = Math.max( b[ 1 ][ 0 ], p.lon );
			b[ 1 ][ 1 ] = Math.max( b[ 1 ][ 1 ], p.lat );
			return b;
		},
		[
			[ Infinity, Infinity ],
			[ -Infinity, -Infinity ],
		]
	);
}

/**
 * Compute bounds from [lon, lat, ele] coordinate triples.
 *
 * @param coords Coordinates.
 */
export function computeBoundsFromCoords( coords: Coord[] ): Bounds {
	return computeBounds(
		coords.map( ( c ) => ( { lon: c[ 0 ], lat: c[ 1 ] } ) )
	);
}

/**
 * Build a MapLibre raster style for a tile template.
 *
 * @param tileUrl     Tile URL template ({z}/{x}/{y}).
 * @param attribution Attribution HTML.
 */
export function buildRasterStyle(
	tileUrl: string,
	attribution: string
): StyleSpecification {
	return {
		version: 8,
		sources: {
			osm: {
				type: 'raster',
				tiles: [ tileUrl || DEFAULT_TILE_URL ],
				tileSize: 256,
				attribution: attribution || DEFAULT_ATTRIBUTION,
			},
		},
		layers: [ { id: 'osm', type: 'raster', source: 'osm' } ],
	};
}

export interface CreateMapOptions {
	maplibregl: MapLibreGl;
	container: HTMLElement;
	style: StyleSpecification;
	bounds: LngLatBoundsLike;
	maxZoom?: number;
}

/**
 * Create a MapLibre map with the standard control set.
 *
 * @param options            Options.
 * @param options.maplibregl MapLibre GL module.
 * @param options.container  Map container element.
 * @param options.style      Style spec.
 * @param options.bounds     Initial bounds.
 * @param options.maxZoom    Max zoom for fitBounds.
 */
export function createMap( {
	maplibregl,
	container,
	style,
	bounds,
	maxZoom,
}: CreateMapOptions ): MapLibreMap {
	const map = new maplibregl.Map( {
		container,
		style,
		bounds,
		fitBoundsOptions: {
			padding: 48,
			...( Number.isFinite( maxZoom ) && { maxZoom } ),
		},
		pitch: 0,
		attributionControl: { compact: true },
	} );

	map.addControl( new maplibregl.NavigationControl(), 'top-right' );
	map.addControl( new maplibregl.ScaleControl(), 'bottom-left' );
	map.addControl( new maplibregl.FullscreenControl(), 'top-right' );
	map.addControl(
		new maplibregl.GeolocateControl( {
			positionOptions: { enableHighAccuracy: true },
			trackUserLocation: true,
		} ),
		'top-right'
	);

	return map;
}

interface WaypointIcon {
	icon: string;
	color: string;
}

const WPT_ICONS: Record< string, WaypointIcon > = {
	summit: { icon: '⛰', color: '#57534e' },
	viewpoint: { icon: '👁', color: '#15803d' },
	water: { icon: '💦', color: '#0369a1' },
	fountain: { icon: '💦', color: '#0369a1' },
	food: { icon: '🍽', color: '#e65100' },
	restaurant: { icon: '🍽', color: '#e65100' },
	cafe: { icon: '☕️', color: '#b45309' },
	bar: { icon: '🍺', color: '#9f1239' },
	lodging: { icon: '🏠', color: '#1565c0' },
	accommodation: { icon: '🏠', color: '#1565c0' },
	hotel: { icon: '🏨', color: '#1565c0' },
	camp: { icon: '⛺️', color: '#15803d' },
	church: { icon: '⛪️', color: '#4d7c0f' },
	monument: { icon: '🏛', color: '#4d7c0f' },
	museum: { icon: '🏛', color: '#b45309' },
	pharmacy: { icon: '💊', color: '#b91c1c' },
	shop: { icon: '🛒', color: '#0f766e' },
	parking: { icon: '🅿️', color: '#4338ca' },
	danger: { icon: '⚠️', color: '#b91c1c' },
	photo: { icon: '📷', color: '#7b1fa2' },
};
const WPT_DEFAULT: WaypointIcon = { icon: '📍', color: TRACK_COLOR };

/**
 * Look up the icon descriptor for a waypoint type (case-insensitive).
 *
 * @param type Waypoint type.
 */
function iconFor( type: string ): WaypointIcon {
	if ( ! type ) {
		return WPT_DEFAULT;
	}
	return WPT_ICONS[ String( type ).toLowerCase() ] || WPT_DEFAULT;
}

/**
 * Validate a waypoint link URL, allowing only safe schemes.
 *
 * @param url Raw URL from the GPX file.
 * @return The original URL if safe, otherwise "".
 */
export function safeLinkUrl( url: string ): string {
	if ( ! url ) {
		return '';
	}
	let parsed: URL;
	try {
		parsed = new URL( url, window.location.href );
	} catch {
		return '';
	}
	return [ 'http:', 'https:', 'mailto:' ].includes( parsed.protocol )
		? url
		: '';
}

/**
 * Append a <div> with text content to a popup container.
 *
 * @param parent    Container.
 * @param className Class for the new element.
 * @param text      Text content.
 */
function appendPopupRow(
	parent: HTMLElement,
	className: string,
	text: string
): void {
	const row = document.createElement( 'div' );
	row.className = className;
	row.textContent = text;
	parent.appendChild( row );
}

/**
 * Add DOM markers with popups for each waypoint.
 *
 * @param maplibregl MapLibre GL module.
 * @param map        Map instance.
 * @param waypoints  Parsed waypoints.
 * @param bg         Map background colour used for the marker ring.
 */
export function addWaypointMarkers(
	maplibregl: MapLibreGl,
	map: MapLibreMap,
	waypoints: Waypoint[],
	bg: string
): void {
	waypoints.forEach( ( wpt ) => {
		const cfg = iconFor( wpt.type );

		const el = document.createElement( 'div' );
		const dot = document.createElement( 'div' );
		dot.className = 'gpxrm-marker';
		dot.style.background = cfg.color;
		dot.style.borderColor = bg || '#ffffff';
		dot.textContent = cfg.icon;
		el.appendChild( dot );

		// Popup content is built with DOM APIs (never HTML strings) so
		// attacker-controlled GPX fields can only ever become text nodes.
		const content = document.createElement( 'div' );
		appendPopupRow( content, 'gpxrm-popup-name', wpt.name );
		if ( wpt.desc ) {
			appendPopupRow( content, 'gpxrm-popup-desc', wpt.desc );
		}
		if ( wpt.type ) {
			appendPopupRow( content, 'gpxrm-popup-type', wpt.type );
		}

		const linkUrl = safeLinkUrl( wpt.link );
		if ( linkUrl ) {
			const anchor = document.createElement( 'a' );
			anchor.className = 'gpxrm-popup-link';
			anchor.setAttribute( 'href', linkUrl );
			anchor.target = '_blank';
			anchor.rel = 'noopener noreferrer';
			anchor.textContent = linkUrl;
			content.appendChild( anchor );
		}

		const popup = new maplibregl.Popup( {
			offset: 16,
			closeButton: true,
		} ).setDOMContent( content );

		new maplibregl.Marker( { element: el } )
			.setLngLat( [ wpt.lon, wpt.lat ] )
			.setPopup( popup )
			.addTo( map );
	} );
}

/**
 * Great-circle distance between two points, in kilometres.
 *
 * @param lat1 Latitude 1.
 * @param lon1 Longitude 1.
 * @param lat2 Latitude 2.
 * @param lon2 Longitude 2.
 */
export function haversine(
	lat1: number,
	lon1: number,
	lat2: number,
	lon2: number
): number {
	const R = 6371;
	const dLat = ( ( lat2 - lat1 ) * Math.PI ) / 180;
	const dLon = ( ( lon2 - lon1 ) * Math.PI ) / 180;
	const a =
		Math.sin( dLat / 2 ) ** 2 +
		Math.cos( ( lat1 * Math.PI ) / 180 ) *
			Math.cos( ( lat2 * Math.PI ) / 180 ) *
			Math.sin( dLon / 2 ) ** 2;
	return R * 2 * Math.atan2( Math.sqrt( a ), Math.sqrt( 1 - a ) );
}
