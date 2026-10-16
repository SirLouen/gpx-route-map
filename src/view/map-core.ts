/**
 * Framework-agnostic map helpers: GPX parsing, bounds, MapLibre style/markers.
 */

import type * as MapLibreNs from 'maplibre-gl';

import type {
	Bounds,
	Coord,
	MapLibreGl,
	ParsedGpx,
	Waypoint,
	WaypointPost,
} from './types';
import { keyboardMarker } from './marker-keyboard';
import { readWaypointPosts } from './waypoint-posts';
import { appendDescription } from './description';
import { safeLinkUrl } from './safe-url';

export { safeLinkUrl };

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
 * The namespace of the element a GPX file links a waypoint to a Waypoint post
 * with, inside the waypoint's <extensions>. Mirrors Waypoints::GPX_NAMESPACE.
 */
export const WAYPOINT_NAMESPACE =
	'https://wordpress.org/plugins/gpx-route-map/';

/**
 * Whether a point can go on the map: MapLibre throws on a latitude past a
 * pole, and fitting the view to an infinite or absurd longitude overflows.
 * Longitudes past ±180 are kept, as some tools write them for a track that
 * crosses the antimeridian; the bound is far beyond any such track.
 *
 * @param lat Latitude.
 * @param lon Longitude.
 */
function onMap( lat: number, lon: number ): boolean {
	// False for NaN as well.
	return Math.abs( lat ) <= 90 && Math.abs( lon ) <= 1e6;
}

/**
 * The points of a track or route that can go on the map, and where each of
 * its segments starts.
 *
 * @param points Its trkpt or rtept elements.
 */
function readPoints(
	points: NodeListOf< Element >
): Pick< ParsedGpx, 'coords' | 'segmentStarts' | 'hasEle' > {
	const coords: Coord[] = [];
	const segmentStarts: number[] = [];
	const hasEle: boolean[] = [];
	let lastSegment: Element | null = null;
	points.forEach( ( pt ) => {
		const lat = parseFloat( pt.getAttribute( 'lat' ) ?? '' );
		const lon = parseFloat( pt.getAttribute( 'lon' ) ?? '' );
		if ( ! onMap( lat, lon ) ) {
			return;
		}
		const segment = pt.closest( 'trkseg, trk, rte' );
		if ( ! coords.length || segment !== lastSegment ) {
			segmentStarts.push( coords.length );
		}
		lastSegment = segment;
		const eleEl = pt.querySelector( 'ele' );
		const ele = eleEl ? parseFloat( eleEl.textContent ?? '' ) : NaN;
		coords.push( [ lon, lat, Number.isNaN( ele ) ? 0 : ele ] );
		hasEle.push( Number.isFinite( ele ) );
	} );
	return { coords, segmentStarts, hasEle };
}

/**
 * The text of a waypoint's own child element, such as its <name>.
 *
 * Only a direct child: a <link> holds a <type> of its own, the MIME type of
 * the page it points to, and <extensions> can hold anything.
 *
 * @param parent    The <wpt> element.
 * @param localName The child's name, in whatever namespace the file uses.
 */
function childText( parent: Element, localName: string ): string {
	for ( const child of Array.from( parent.children ) ) {
		if ( child.localName === localName ) {
			return child.textContent ?? '';
		}
	}
	return '';
}

/**
 * The Waypoint post a waypoint's <extensions> link it to, or 0.
 *
 * @param wpt The <wpt> element.
 */
function linkedPostId( wpt: Element ): number {
	// The first that holds an ID, as the server reads it.
	for ( const element of Array.from(
		wpt.getElementsByTagNameNS( WAYPOINT_NAMESPACE, 'waypoint' )
	) ) {
		const text = element.textContent?.trim() ?? '';
		const id = /^[0-9]{1,15}$/.test( text ) ? Number( text ) : 0;
		if ( id > 0 ) {
			return id;
		}
	}
	return 0;
}

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
		return {
			coords: [],
			waypoints: [],
			segmentStarts: [],
			hasEle: [],
			invalid: true,
		};
	}

	// The track's points, or the route's when no track point can be drawn.
	const track = readPoints( doc.querySelectorAll( 'trkpt' ) );
	const { coords, segmentStarts, hasEle } = track.coords.length
		? track
		: readPoints( doc.querySelectorAll( 'rtept' ) );

	const waypoints: Waypoint[] = [];
	// The index counts every <wpt>, those left off the map too, so it is the
	// position the server finds in the file.
	doc.querySelectorAll( 'wpt' ).forEach( ( w, index ) => {
		const lat = parseFloat( w.getAttribute( 'lat' ) ?? '' );
		const lon = parseFloat( w.getAttribute( 'lon' ) ?? '' );
		if ( ! onMap( lat, lon ) ) {
			return;
		}
		const link = Array.from( w.children ).find(
			( child ) => 'link' === child.localName
		);
		waypoints.push( {
			lon,
			lat,
			// Trimmed: a whitespace-only name would otherwise count as one, and as
			// the marker's accessible name it would announce as nothing at all.
			name: childText( w, 'name' ).trim() || 'Waypoint',
			desc: childText( w, 'desc' ),
			type: childText( w, 'type' ),
			link: link?.getAttribute( 'href' ) || '',
			index,
			postId: linkedPostId( w ),
		} );
	} );

	return { coords, waypoints, segmentStarts, hasEle, invalid: false };
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
 * @param tileMaxZoom The last tile level the provider has, if known: past it
 *                    MapLibre draws those tiles larger instead of asking for
 *                    tiles that do not exist.
 */
export function buildRasterStyle(
	tileUrl: string,
	attribution: string,
	tileMaxZoom?: number
): StyleSpecification {
	return {
		version: 8,
		sources: {
			osm: {
				type: 'raster',
				tiles: [ tileUrl || DEFAULT_TILE_URL ],
				tileSize: 256,
				attribution: attribution || DEFAULT_ATTRIBUTION,
				...( Number.isFinite( tileMaxZoom ) && {
					maxzoom: tileMaxZoom,
				} ),
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
	tileMaxZoom?: number;
	locale?: Record< string, string >;
	preview?: boolean;
}

/**
 * Create a MapLibre map with the standard control set.
 *
 * @param options             Options.
 * @param options.maplibregl  MapLibre GL module.
 * @param options.container   Map container element.
 * @param options.style       Style spec.
 * @param options.bounds      Initial bounds.
 * @param options.maxZoom     How close the map opens: max zoom for fitBounds.
 * @param options.tileMaxZoom The last tile level the provider has, if known.
 *                            The 256 pixel tiles of that level show at their
 *                            own size one map zoom earlier, so that is as far
 *                            as visitors can zoom in.
 * @param options.locale      Translated MapLibre UI strings (see readMapUi).
 * @param options.preview     Whether it is the block editor's preview.
 */
export function createMap( {
	maplibregl,
	container,
	style,
	bounds,
	maxZoom,
	tileMaxZoom,
	locale,
	preview = false,
}: CreateMapOptions ): MapLibreMap {
	const map = new maplibregl.Map( {
		container,
		style,
		bounds,
		fitBoundsOptions: {
			padding: 48,
			...( Number.isFinite( maxZoom ) && { maxZoom } ),
		},
		...( Number.isFinite( tileMaxZoom ) && {
			maxZoom: Number( tileMaxZoom ) - 1,
		} ),
		pitch: 0,
		attributionControl: { compact: true },
		// Only accepted here: MapLibre has no way to change it afterwards.
		locale,
	} );

	map.addControl( new maplibregl.NavigationControl(), 'top-right' );
	map.addControl( new maplibregl.ScaleControl(), 'bottom-left' );
	// Neither means anything while laying out a post in the editor.
	if ( ! preview ) {
		map.addControl( new maplibregl.FullscreenControl(), 'top-right' );
		map.addControl(
			new maplibregl.GeolocateControl( {
				positionOptions: { enableHighAccuracy: true },
				trackUserLocation: true,
			} ),
			'top-right'
		);
	}

	return map;
}

interface WaypointIcon {
	icon: string;
	color: string;
}

const WPT_ICONS: Record< string, WaypointIcon > = {
	summit: { icon: '⛰️', color: '#57534e' },
	viewpoint: { icon: '👁️', color: '#15803d' },
	water: { icon: '💦', color: '#0369a1' },
	fountain: { icon: '💦', color: '#0369a1' },
	food: { icon: '🍽️', color: '#e65100' },
	restaurant: { icon: '🍽️', color: '#e65100' },
	cafe: { icon: '☕️', color: '#b45309' },
	bar: { icon: '🍺', color: '#9f1239' },
	lodging: { icon: '🏠', color: '#1565c0' },
	accommodation: { icon: '🏠', color: '#1565c0' },
	hotel: { icon: '🏨', color: '#1565c0' },
	camp: { icon: '⛺️', color: '#15803d' },
	church: { icon: '⛪️', color: '#4d7c0f' },
	monument: { icon: '🏛️', color: '#4d7c0f' },
	museum: { icon: '🏛️', color: '#b45309' },
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
 * The element a waypoint's marker is drawn with.
 *
 * @param wpt  The waypoint.
 * @param post What its Waypoint post changes about it, if anything.
 * @param bg   Colour of the marker's ring.
 */
function markerElement(
	wpt: Waypoint,
	post: WaypointPost | undefined,
	bg: string
): HTMLElement {
	const el = document.createElement( 'div' );
	el.className = 'gpxrm-waypoint';

	if ( 'image' === post?.marker && post.thumb ) {
		el.classList.add( 'gpxrm-waypoint-image' );
		const frame = document.createElement( 'div' );
		frame.className = 'gpxrm-marker-image';
		const img = document.createElement( 'img' );
		img.src = post.thumb;
		// The marker is named for screen readers already.
		img.alt = '';
		img.decoding = 'async';
		img.draggable = false;
		frame.appendChild( img );
		el.appendChild( frame );
		return el;
	}

	const dot = document.createElement( 'div' );
	dot.className = 'gpxrm-marker';
	dot.style.borderColor = bg || '#ffffff';

	if ( 'icon' === post?.marker && post.icon ) {
		dot.classList.add( 'gpxrm-marker-icon' );
		dot.style.background = post.background || TRACK_COLOR;
		dot.style.color = post.color || '#ffffff';
		const glyph = document.createElement( 'span' );
		glyph.className = `dashicons dashicons-${ post.icon }`;
		glyph.setAttribute( 'aria-hidden', 'true' );
		dot.appendChild( glyph );
	} else {
		const cfg = iconFor( wpt.type );
		dot.style.background = cfg.color;
		dot.textContent = cfg.icon;
	}

	el.appendChild( dot );
	return el;
}

/**
 * Keep an open popup within the map. One taller than the map, such as a
 * photo's on a short map, would run past its edge out of reach, so it
 * scrolls instead. Measured afresh each time, as the map may have changed
 * size, or the waypoint moved, since it was last fitted.
 *
 * @param map   Map instance.
 * @param popup The popup, open.
 */
function fitPopup( map: MapLibreMap, popup: MapLibreNs.Popup ): void {
	const box = popup
		.getElement()
		?.querySelector< HTMLElement >( '.maplibregl-popup-content' );
	if ( ! box ) {
		return;
	}
	const fitted = '' !== box.style.maxHeight;
	box.style.maxHeight = '';
	box.style.overflowY = '';
	// With no anchor set, MapLibre opens it above its waypoint, or below when
	// it does not fit above. Kept to the larger side's room, less its offset
	// and tip, it fits on whichever side MapLibre then picks for it.
	const height = map.getContainer().clientHeight;
	const { y } = map.project( popup.getLngLat() );
	const room = Math.round( Math.max( y, height - y ) ) - 32;
	const tall = room > 0 && box.offsetHeight > room;
	if ( tall ) {
		// Padding included, as MapLibre measures the whole popup.
		box.style.boxSizing = 'border-box';
		box.style.maxHeight = room + 'px';
		box.style.overflowY = 'auto';
	}
	if ( tall || fitted ) {
		// Placed again for its new size.
		popup.setLngLat( popup.getLngLat() );
	}
}

/**
 * Keep a popup within the map while it is open: when it opens, and when the
 * map changes size under it, such as on leaving fullscreen.
 *
 * @param map   Map instance.
 * @param popup The popup.
 */
function fitWhileOpen( map: MapLibreMap, popup: MapLibreNs.Popup ): void {
	const fit = () => fitPopup( map, popup );
	popup.on( 'open', () => {
		fit();
		map.on( 'resize', fit );
	} );
	popup.on( 'close', () => {
		map.off( 'resize', fit );
	} );
}

/**
 * Add DOM markers with popups for each waypoint.
 *
 * @param maplibregl MapLibre GL module.
 * @param map        Map instance.
 * @param waypoints  Parsed waypoints.
 * @param bg         Map background colour used for the marker ring.
 * @param posts      What Waypoint posts change about waypoints, by their
 *                   position in the file: by default, what the map's
 *                   element says about them.
 */
export function addWaypointMarkers(
	maplibregl: MapLibreGl,
	map: MapLibreMap,
	waypoints: Waypoint[],
	bg: string,
	posts: Record< number, WaypointPost > = readWaypointPosts(
		map.getContainer()
	)
): void {
	waypoints.forEach( ( wpt ) => {
		const post = posts[ wpt.index ];
		const name = post?.name || wpt.name;
		const el = markerElement( wpt, post, bg );

		// Popup content is built with DOM APIs (never HTML strings) so
		// attacker-controlled GPX fields can only ever become text nodes.
		const content = document.createElement( 'div' );
		if ( post?.photo ) {
			const photo = document.createElement( 'img' );
			photo.className = 'gpxrm-popup-photo';
			photo.src = post.photo.src;
			if ( post.photo.srcset ) {
				photo.srcset = post.photo.srcset;
				// The popup's content is at most 240px wide.
				photo.sizes = '240px';
			}
			// Its size is known before it loads, so the popup does not grow
			// and shift once it opens.
			if ( post.photo.width > 0 && post.photo.height > 0 ) {
				photo.width = post.photo.width;
				photo.height = post.photo.height;
			}
			photo.alt = post.photo.alt;
			photo.decoding = 'async';
			content.appendChild( photo );
		}
		appendPopupRow( content, 'gpxrm-popup-name', name );
		if ( post?.desc ) {
			// Written in an editor: links, bold and italic text and lists.
			appendDescription(
				content,
				'gpxrm-popup-desc gpxrm-popup-desc-post',
				post.desc
			);
		} else if ( wpt.desc ) {
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
		fitWhileOpen( map, popup );

		const marker = new maplibregl.Marker( { element: el } )
			.setLngLat( [ wpt.lon, wpt.lat ] )
			.setPopup( popup );
		keyboardMarker( marker, popup, name );
		marker.addTo( map );
	} );
}

/**
 * A map control that links to the GPX file.
 *
 * Built as a MapLibre control so it sits in the same stack as the zoom and
 * fullscreen buttons and inherits their chrome. It is an anchor rather than a
 * button so it behaves like a link: middle-click, right-click and keyboard
 * activation all work. The `download` attribute is honoured for same-origin
 * files; for a cross-origin GPX the browser opens it instead, which is still
 * the useful outcome.
 */
export class DownloadControl {
	private url: string;
	private filename: string;
	private label: string;
	private container: HTMLElement | null = null;

	/**
	 * @param url      GPX file URL.
	 * @param filename Suggested filename.
	 * @param label    Accessible label and tooltip.
	 */
	constructor( url: string, filename: string, label: string ) {
		this.url = url;
		this.filename = filename;
		this.label = label;
	}

	/**
	 * Build the control element.
	 */
	onAdd(): HTMLElement {
		const container = document.createElement( 'div' );
		container.className =
			'maplibregl-ctrl maplibregl-ctrl-group gpxrm-download-ctrl';

		const link = document.createElement( 'a' );
		link.className = 'gpxrm-download-btn';
		link.href = this.url;
		link.setAttribute( 'download', this.filename );
		link.title = this.label;
		link.setAttribute( 'aria-label', this.label );
		link.innerHTML =
			'<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' +
			'<path fill="currentColor" d="M12 3a1 1 0 0 1 1 1v8.59l2.3-2.3a1 1 0 1 1 1.4 1.42l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.42l2.3 2.3V4a1 1 0 0 1 1-1Z"/>' +
			'<path fill="currentColor" d="M5 18a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H6a1 1 0 0 1-1-1Z"/>' +
			'</svg>';

		container.appendChild( link );
		this.container = container;
		return container;
	}

	/**
	 * Detach the control.
	 */
	onRemove(): void {
		this.container?.parentNode?.removeChild( this.container );
		this.container = null;
	}
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
