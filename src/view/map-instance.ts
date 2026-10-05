/**
 * Wire up a single GPX map: track layers, markers, elevation profile and stats.
 */

import {
	parseGPX,
	computeBoundsFromCoords,
	buildRasterStyle,
	createMap,
	addWaypointMarkers,
	TRACK_COLOR,
	TRACK_CASING,
	DEFAULT_TILE_URL,
	DEFAULT_ATTRIBUTION,
	DownloadControl,
} from './map-core';
import { keyboardMarker } from './marker-keyboard';
import { readMapUi } from './map-ui';
import type { FeatureCollection } from 'geojson';
import type { GeoJSONSource } from 'maplibre-gl';

import type { Coord, MapLibreGl } from './types';
import { ElevationProfile } from './elevation';
import { routeStats, nearestIndex } from './stats';
import type { RouteStats } from './stats';
import { readUnits, formatDistance, formatElevation, METRIC } from './units';
import type { UnitConfig } from './units';

/**
 * Write the stats bar values inside a container.
 *
 * @param root      Instance root.
 * @param stats     Route stats, in kilometres and metres.
 * @param waypoints Waypoint count.
 * @param units     Display units.
 */
function fillStats(
	root: Element,
	stats: RouteStats,
	waypoints: number,
	units: UnitConfig = METRIC
): void {
	const set = ( key: string, value: string ): void => {
		const el = root.querySelector( `[data-gpxrm-stat="${ key }"]` );
		if ( el ) {
			el.textContent = value;
		}
	};
	set( 'distance', formatDistance( stats.distance, units ) );
	set( 'gain', `+${ formatElevation( stats.gain, units ) }` );
	set( 'loss', `−${ formatElevation( stats.loss, units ) }` );
	set( 'max', formatElevation( stats.maxEle, units ) );
	set( 'waypoints', `${ waypoints }` );
}

/**
 * Show an inline error inside the map element.
 *
 * @param mapEl Map element.
 * @param msg   Message.
 */
function showError( mapEl: HTMLElement, msg: string ): void {
	const div = document.createElement( 'div' );
	div.className = 'gpxrm-error';
	div.textContent = msg;
	mapEl.replaceChildren( div );
}

/** User-facing view messages, localized server-side (see viewMessages). */
interface ViewMessages {
	load: string;
	cors: string;
	invalid: string;
	nopoints: string;
	download: string;
	maplibre: string;
	start: string;
	end: string;
}

const FALLBACK_MESSAGES: ViewMessages = {
	load: 'Could not load GPX file.',
	cors: 'Could not load GPX file: its host does not allow cross-origin (CORS) requests. Upload the file to this site instead.',
	invalid: 'Invalid GPX file.',
	nopoints: 'No track or route points found in GPX file.',
	download: 'Download GPX file',
	maplibre: 'Map failed to load. Click to retry.',
	start: 'Start',
	end: 'End',
};

/**
 * The view runs as a script module, which cannot use
 * wp_set_script_translations, so the server passes already-translated messages
 * as a JSON `data-gpxrm-i18n` attribute. Falls back to English if absent.
 *
 * @param mapEl Map element.
 */
export function viewMessages( mapEl: HTMLElement ): ViewMessages {
	try {
		const raw = mapEl.dataset.gpxrmI18n;
		if ( raw ) {
			return { ...FALLBACK_MESSAGES, ...JSON.parse( raw ) };
		}
	} catch {
		// Ignore malformed JSON and use the English fallback.
	}
	return FALLBACK_MESSAGES;
}

/**
 * Initialize one map instance.
 *
 * The front end builds each map once and leaves it. Aborting `signal` tears
 * the instance down instead, at whatever point it has reached: the GPX
 * download is cancelled, the map is removed, which frees its WebGL context,
 * and nothing it set up keeps listening to the page. The element can then
 * take a fresh instance. What this one wrote into the surrounding markup -
 * stats values, the drawn profile, the loading placeholder it removed -
 * stays as it is; rendering fresh markup is up to the caller.
 *
 * @param mapEl      The `.gpxrm-map` element.
 * @param maplibregl MapLibre GL module.
 * @param signal     Aborted to tear the instance down.
 */
export async function initInstance(
	mapEl: HTMLElement,
	maplibregl: MapLibreGl,
	signal?: AbortSignal
): Promise< void > {
	const root = mapEl.closest( '.gpxrm' ) || mapEl.parentElement || mapEl;
	const gpxUrl = mapEl.dataset.gpxrmGpx;
	if ( ! gpxUrl ) {
		return;
	}
	// An error left on this element by an instance that failed before this
	// one would otherwise sit under the new map and still be read out.
	mapEl.querySelector( ':scope > .gpxrm-error' )?.remove();

	let text;
	try {
		const res = await fetch( gpxUrl, { signal } );
		if ( ! res.ok ) {
			throw new Error( `HTTP ${ res.status }` );
		}
		text = await res.text();
	} catch ( err ) {
		// Torn down mid-download: that is not a failure to report.
		if ( signal?.aborted ) {
			return;
		}
		let crossOrigin = false;
		try {
			crossOrigin =
				err instanceof TypeError &&
				new URL( gpxUrl, window.location.href ).origin !==
					window.location.origin;
		} catch {
			// A URL we cannot even parse is not worth blaming on CORS.
			crossOrigin = false;
		}
		const msg = viewMessages( mapEl );
		showError( mapEl, crossOrigin ? msg.cors : msg.load );
		return;
	}

	// A download that ignores the signal can still finish after the abort.
	if ( signal?.aborted ) {
		return;
	}
	const msg = viewMessages( mapEl );

	const { coords, waypoints, segmentStarts, invalid } = parseGPX( text );
	if ( invalid ) {
		showError( mapEl, msg.invalid );
		return;
	}
	if ( ! coords.length ) {
		showError( mapEl, msg.nopoints );
		return;
	}
	const segStarts = new Set( segmentStarts );

	const tileUrl = mapEl.dataset.gpxrmTileUrl || DEFAULT_TILE_URL;
	const attribution = mapEl.dataset.gpxrmAttribution || DEFAULT_ATTRIBUTION;
	const maxZoom = parseInt( mapEl.dataset.gpxrmMaxZoom || '17', 10 );

	const style = buildRasterStyle( tileUrl, attribution );
	const bounds = computeBoundsFromCoords( coords );
	const map = createMap( {
		maplibregl,
		container: mapEl,
		style,
		bounds,
		maxZoom,
		locale: readMapUi( mapEl ),
		// Set by the block editor on the map it previews.
		preview: 'gpxrmPreview' in mapEl.dataset,
	} );

	// The server only sets this attribute when the download button is enabled.
	const downloadName = mapEl.dataset.gpxrmDownload;
	if ( downloadName ) {
		map.addControl(
			new DownloadControl( gpxUrl, downloadName, msg.download ),
			'top-right'
		);
	}

	const units = readUnits( mapEl );
	const stats = routeStats( coords, segStarts );
	fillStats( root, stats, waypoints.length, units );

	let profile: ElevationProfile | null = null;
	const canvas: HTMLCanvasElement | null = root.querySelector(
		'[data-gpxrm-elevation]'
	);

	const setPositionDot = ( idx: number ): void => {
		const src = map.getSource( 'gpxrm-position' );
		if ( ! src || ! ( 'setData' in src ) ) {
			return;
		}
		const c = coords[ idx ];
		// MapLibre 6 returns a promise here. This fires on every scrub, so a
		// rejection is swallowed rather than left to surface unhandled: a
		// missed position dot is not worth a console error.
		void ( src as GeoJSONSource )
			.setData( {
				type: 'FeatureCollection',
				features: [
					{
						type: 'Feature',
						properties: {},
						geometry: {
							type: 'Point',
							coordinates: [ c[ 0 ], c[ 1 ] ],
						},
					},
				],
			} )
			?.catch( () => {} );
	};

	const clearPositionDot = (): void => {
		const src = map.getSource( 'gpxrm-position' );
		if ( src && 'setData' in src ) {
			void ( src as GeoJSONSource )
				.setData( {
					type: 'FeatureCollection',
					features: [],
				} )
				?.catch( () => {} );
		}
	};

	if ( canvas ) {
		profile = new ElevationProfile(
			canvas,
			coords,
			{
				onScrub: ( idx, dragging ) => {
					setPositionDot( idx );
					if ( dragging ) {
						const c = coords[ idx ];
						map.easeTo( {
							center: [ c[ 0 ], c[ 1 ] ],
							duration: 100,
						} );
					}
				},
				onLeave: clearPositionDot,
			},
			segStarts,
			units
		);
	}

	map.on( 'load', () => {
		const placeholder = root.querySelector( '.gpxrm-placeholder' );
		if ( placeholder ) {
			placeholder.classList.add( 'is-hidden' );
			setTimeout( () => placeholder.remove(), 400 );
		}

		const segmentLines = segmentStarts
			.map( ( startIdx, s ) =>
				coords
					.slice( startIdx, segmentStarts[ s + 1 ] ?? coords.length )
					.map( ( c: Coord ): [ number, number ] => [
						c[ 0 ],
						c[ 1 ],
					] )
			)
			.filter( ( line ) => line.length > 1 );
		const trackGeoJSON: FeatureCollection = {
			type: 'FeatureCollection',
			features: [
				{
					type: 'Feature',
					properties: {},
					geometry: {
						type: 'MultiLineString',
						coordinates: segmentLines,
					},
				},
			],
		};
		map.addSource( 'gpxrm-track', {
			type: 'geojson',
			data: trackGeoJSON,
		} );

		map.addLayer( {
			id: 'gpxrm-track-casing',
			type: 'line',
			source: 'gpxrm-track',
			layout: { 'line-join': 'round', 'line-cap': 'round' },
			paint: {
				'line-color': TRACK_CASING,
				'line-width': 9,
				'line-opacity': 0.7,
			},
		} );
		map.addLayer( {
			id: 'gpxrm-track-line',
			type: 'line',
			source: 'gpxrm-track',
			layout: { 'line-join': 'round', 'line-cap': 'round' },
			paint: { 'line-color': TRACK_COLOR, 'line-width': 5 },
		} );
		map.addLayer( {
			id: 'gpxrm-track-hit',
			type: 'line',
			source: 'gpxrm-track',
			layout: { 'line-join': 'round', 'line-cap': 'round' },
			paint: {
				'line-color': '#000000',
				'line-width': 24,
				'line-opacity': 0,
			},
		} );

		map.addSource( 'gpxrm-position', {
			type: 'geojson',
			data: { type: 'FeatureCollection', features: [] },
		} );
		map.addLayer( {
			id: 'gpxrm-position',
			type: 'circle',
			source: 'gpxrm-position',
			paint: {
				'circle-radius': 7,
				'circle-color': '#ffffff',
				'circle-stroke-color': TRACK_COLOR,
				'circle-stroke-width': 3,
			},
		} );

		// Built from text nodes rather than setHTML: the labels are now
		// translations, and a translation is not markup. The same label names
		// the pin for screen readers, in place of MapLibre's English default.
		const addPin = ( lngLat: Coord, color: string, label: string ) => {
			const name = document.createElement( 'div' );
			name.className = 'gpxrm-popup-name';
			name.textContent = label;
			const popup = new maplibregl.Popup().setDOMContent( name );
			const marker = new maplibregl.Marker( { color } )
				.setLngLat( [ lngLat[ 0 ], lngLat[ 1 ] ] )
				.setPopup( popup );
			keyboardMarker( marker, popup, label );
			marker.addTo( map );
		};

		addPin( coords[ 0 ], '#22c55e', msg.start );
		addPin( coords[ coords.length - 1 ], '#ef4444', msg.end );

		addWaypointMarkers( maplibregl, map, waypoints, TRACK_CASING );

		map.on( 'click', 'gpxrm-track-hit', ( e ) => {
			if ( ! e.lngLat || ! profile ) {
				return;
			}
			const idx = nearestIndex( coords, e.lngLat.lng, e.lngLat.lat );
			profile.highlight( idx );
			setPositionDot( idx );
		} );
		map.on( 'mouseenter', 'gpxrm-track-hit', () => {
			map.getCanvas().style.cursor = 'crosshair';
		} );
		map.on( 'mouseleave', 'gpxrm-track-hit', () => {
			map.getCanvas().style.cursor = '';
		} );
	} );

	if ( profile ) {
		const boundProfile = profile;

		window.requestAnimationFrame( () => boundProfile.build() );
		let resizeTimer: ReturnType< typeof setTimeout >;
		const rebuild = () => {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( () => boundProfile.build(), 200 );
		};
		// Redraw when the profile's own width changes: on a window resize,
		// but also when a theme or the block editor resizes the block, which
		// the window never hears about.
		if ( 'function' === typeof window.ResizeObserver ) {
			const observer = new window.ResizeObserver( rebuild );
			observer.observe( boundProfile.canvas );
			signal?.addEventListener( 'abort', () => observer.disconnect(), {
				once: true,
			} );
		} else {
			window.addEventListener( 'resize', rebuild, { signal } );
		}
	}

	// Nothing above awaits once the map exists, so the signal cannot fire
	// between its creation and here. A profile build still queued for the
	// next frame or after a resize is a no-op once the profile is destroyed.
	signal?.addEventListener(
		'abort',
		() => {
			profile?.destroy();
			map.remove();
		},
		{ once: true }
	);
}
