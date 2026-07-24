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
} from './map-core';
import type { FeatureCollection } from 'geojson';
import type { GeoJSONSource } from 'maplibre-gl';

import type { Coord, MapLibreGl } from './types';
import { ElevationProfile } from './elevation';
import { routeStats, nearestIndex } from './stats';
import type { RouteStats } from './stats';

/**
 * Write the stats bar values inside a container.
 *
 * @param root      Instance root.
 * @param stats     Route stats.
 * @param waypoints Waypoint count.
 */
function fillStats(
	root: Element,
	stats: RouteStats,
	waypoints: number
): void {
	const set = ( key: string, value: string ): void => {
		const el = root.querySelector( `[data-gpxrm-stat="${ key }"]` );
		if ( el ) {
			el.textContent = value;
		}
	};
	set( 'distance', `${ stats.distance.toFixed( 2 ) } km` );
	set( 'gain', `+${ Math.round( stats.gain ).toLocaleString() } m` );
	set( 'loss', `−${ Math.round( stats.loss ).toLocaleString() } m` );
	set( 'max', `${ Math.round( stats.maxEle ).toLocaleString() } m` );
	set( 'waypoints', `${ waypoints }` );
}

/**
 * Show an inline error inside the map element.
 *
 * @param mapEl Map element.
 * @param msg   Message.
 */
function showError( mapEl: HTMLElement, msg: string ): void {
	mapEl.innerHTML = `<div class="gpxrm-error">${ msg }</div>`;
}

/**
 * Initialize one map instance.
 *
 * @param mapEl      The `.gpxrm-map` element.
 * @param maplibregl MapLibre GL module.
 */
export async function initInstance(
	mapEl: HTMLElement,
	maplibregl: MapLibreGl
): Promise< void > {
	const root = mapEl.closest( '.gpxrm' ) || mapEl.parentElement || mapEl;
	const gpxUrl = mapEl.dataset.gpxrmGpx;
	if ( ! gpxUrl ) {
		return;
	}

	let text;
	try {
		const res = await fetch( gpxUrl );
		if ( ! res.ok ) {
			throw new Error( `HTTP ${ res.status }` );
		}
		text = await res.text();
	} catch ( err ) {
		let crossOrigin = false;
		try {
			crossOrigin =
				err instanceof TypeError &&
				new URL( gpxUrl, window.location.href ).origin !==
					window.location.origin;
		} catch ( urlErr ) {
			crossOrigin = false;
		}
		showError(
			mapEl,
			crossOrigin
				? 'Could not load GPX file: its host does not allow cross-origin (CORS) requests. Upload the file to this site instead.'
				: 'Could not load GPX file.'
		);
		return;
	}

	const { coords, waypoints, segmentStarts, invalid } = parseGPX( text );
	if ( invalid ) {
		showError( mapEl, 'Invalid GPX file.' );
		return;
	}
	if ( ! coords.length ) {
		showError( mapEl, 'No track or route points found in GPX file.' );
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
	} );

	const stats = routeStats( coords, segStarts );
	fillStats( root, stats, waypoints.length );

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
		( src as GeoJSONSource ).setData( {
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
		} );
	};

	const clearPositionDot = (): void => {
		const src = map.getSource( 'gpxrm-position' );
		if ( src && 'setData' in src ) {
			( src as GeoJSONSource ).setData( {
				type: 'FeatureCollection',
				features: [],
			} );
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
			segStarts
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

		const start = coords[ 0 ];
		const end = coords[ coords.length - 1 ];
		new maplibregl.Marker( { color: '#22c55e' } )
			.setLngLat( [ start[ 0 ], start[ 1 ] ] )
			.setPopup(
				new maplibregl.Popup().setHTML(
					'<div class="gpxrm-popup-name">Start</div>'
				)
			)
			.addTo( map );
		new maplibregl.Marker( { color: '#ef4444' } )
			.setLngLat( [ end[ 0 ], end[ 1 ] ] )
			.setPopup(
				new maplibregl.Popup().setHTML(
					'<div class="gpxrm-popup-name">End</div>'
				)
			)
			.addTo( map );

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
		window.addEventListener( 'resize', () => {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( () => boundProfile.build(), 200 );
		} );
	}
}
