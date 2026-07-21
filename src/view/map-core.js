/**
 * Framework-agnostic map helpers: GPX parsing, bounds, MapLibre style/markers.
 */

export const DEFAULT_TILE_URL =
	'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
export const DEFAULT_ATTRIBUTION =
	'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

export const TRACK_COLOR = '#2e7d32';
export const TRACK_CASING = '#1b3a1e';

/**
 * Parse a GPX document string into track coordinates and waypoints.
 *
 * @param {string} xmlText Raw GPX XML.
 * @return {{coords: Array<[number, number, number]>, waypoints: Array<Object>, invalid: boolean}} Parsed data.
 */
export function parseGPX( xmlText ) {
	const doc = new window.DOMParser().parseFromString(
		xmlText,
		'application/xml'
	);

	if ( doc.querySelector( 'parsererror' ) ) {
		return { coords: [], waypoints: [], invalid: true };
	}

	let points = doc.querySelectorAll( 'trkpt' );
	if ( ! points.length ) {
		points = doc.querySelectorAll( 'rtept' );
	}

	const coords = [];
	points.forEach( ( pt ) => {
		const lat = parseFloat( pt.getAttribute( 'lat' ) );
		const lon = parseFloat( pt.getAttribute( 'lon' ) );
		if ( Number.isNaN( lat ) || Number.isNaN( lon ) ) {
			return;
		}
		const eleEl = pt.querySelector( 'ele' );
		const ele = eleEl ? parseFloat( eleEl.textContent ) : 0;
		coords.push( [ lon, lat, Number.isNaN( ele ) ? 0 : ele ] );
	} );

	const waypoints = [];
	doc.querySelectorAll( 'wpt' ).forEach( ( w ) => {
		const lat = parseFloat( w.getAttribute( 'lat' ) );
		const lon = parseFloat( w.getAttribute( 'lon' ) );
		if ( Number.isNaN( lat ) || Number.isNaN( lon ) ) {
			return;
		}
		const nameEl = w.querySelector( 'name' );
		const descEl = w.querySelector( 'desc' );
		const typeEl = w.querySelector( 'type' );
		const linkEl = w.querySelector( 'link' );
		waypoints.push( {
			lon,
			lat,
			name: nameEl?.textContent || 'Waypoint',
			desc: descEl?.textContent || '',
			type: typeEl?.textContent || '',
			link: linkEl?.getAttribute( 'href' ) || '',
		} );
	} );

	return { coords, waypoints, invalid: false };
}

/**
 * Compute a bounding box from lon/lat points.
 *
 * @param {Array<{lon: number, lat: number}>} points Points.
 * @return {[[number, number], [number, number]]} Bounds.
 */
export function computeBounds( points ) {
	return points.reduce(
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
 * @param {Array<[number, number, number]>} coords Coordinates.
 * @return {[[number, number], [number, number]]} Bounds.
 */
export function computeBoundsFromCoords( coords ) {
	return computeBounds(
		coords.map( ( c ) => ( { lon: c[ 0 ], lat: c[ 1 ] } ) )
	);
}

/**
 * Build a MapLibre raster style for a tile template.
 *
 * @param {string} tileUrl     Tile URL template ({z}/{x}/{y}).
 * @param {string} attribution Attribution HTML.
 * @return {Object} MapLibre style spec.
 */
export function buildRasterStyle( tileUrl, attribution ) {
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

/**
 * Create a MapLibre map with the standard control set.
 *
 * @param {Object}      options            Options.
 * @param {Object}      options.maplibregl MapLibre GL module.
 * @param {HTMLElement} options.container  Map container element.
 * @param {Object}      options.style      Style spec.
 * @param {Array}       options.bounds     Initial bounds.
 * @param {number}      [options.maxZoom]  Max zoom for fitBounds.
 * @return {Object} MapLibre map instance.
 */
export function createMap( { maplibregl, container, style, bounds, maxZoom } ) {
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

// Default emoji/colour set for common GPX waypoint <type> values. Unknown types
// fall back to a neutral pin, so any GPX renders sensibly without configuration.
const WPT_ICONS = {
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
const WPT_DEFAULT = { icon: '📍', color: TRACK_COLOR };

/**
 * Look up the icon descriptor for a waypoint type (case-insensitive).
 *
 * @param {string} type Waypoint type.
 * @return {{icon: string, color: string}} Icon descriptor.
 */
function iconFor( type ) {
	if ( ! type ) {
		return WPT_DEFAULT;
	}
	return WPT_ICONS[ String( type ).toLowerCase() ] || WPT_DEFAULT;
}

/**
 * Validate a waypoint link URL, allowing only safe schemes.
 *
 * @param {string} url Raw URL from the GPX file.
 * @return {string} The original URL if safe, otherwise "".
 */
export function safeLinkUrl( url ) {
	if ( ! url ) {
		return '';
	}
	let parsed;
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
 * @param {HTMLElement} parent    Container.
 * @param {string}      className Class for the new element.
 * @param {string}      text      Text content.
 * @return {void}
 */
function appendPopupRow( parent, className, text ) {
	const row = document.createElement( 'div' );
	row.className = className;
	row.textContent = text;
	parent.appendChild( row );
}

/**
 * Add DOM markers with popups for each waypoint.
 *
 * @param {Object} maplibregl MapLibre GL module.
 * @param {Object} map        Map instance.
 * @param {Array}  waypoints  Parsed waypoints.
 * @param {string} bg         Map background colour used for the marker ring.
 * @return {void}
 */
export function addWaypointMarkers( maplibregl, map, waypoints, bg ) {
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
 * @param {number} lat1 Latitude 1.
 * @param {number} lon1 Longitude 1.
 * @param {number} lat2 Latitude 2.
 * @param {number} lon2 Longitude 2.
 * @return {number} Distance in km.
 */
export function haversine( lat1, lon1, lat2, lon2 ) {
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
