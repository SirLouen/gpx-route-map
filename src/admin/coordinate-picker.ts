/**
 * The map on a Waypoint post's screen: a click or a drag of the pin fills the
 * latitude and longitude the form sends, and those fields move the pin.
 *
 * The fields are what is saved, and they work without the map, for pasting an
 * exact place, from a keyboard, or where the map cannot load.
 */

import type { FeatureCollection } from 'geojson';
import type { GeoJSONSource, Map as MapLibreMap } from 'maplibre-gl';

import {
	buildRasterStyle,
	computeBoundsFromCoords,
	createMap,
	parseGPX,
	readTileMaxZoom,
	trackGeoJSON,
	TRACK_COLOR,
} from '../view/map-core';
import { readMapUi } from '../view/map-ui';
import type { Bounds, MapLibreGl, ParsedGpx } from '../view/types';

const MAX_LAT = 90;
const MAX_LON = 180;

/** The world, leaving out the poles a tile map barely shows. */
const WORLD: Bounds = [
	[ -170, -55 ],
	[ 170, 70 ],
];

/** How close the map opens on a stored place. */
const PLACE_ZOOM = 14;

const ROUTE = 'gpxrm-picker-route';
const NO_ROUTE: FeatureCollection = { type: 'FeatureCollection', features: [] };

export interface Place {
	lat: number;
	lon: number;
}

/** The parts of the form the map works with. */
interface Form {
	lat: HTMLInputElement;
	lon: HTMLInputElement;
	clear: HTMLElement | null;
	/** The moves of a link to the waypoint's own coordinates. */
	own: NodeListOf< HTMLOptionElement >;
	/** The choice of a map to add the waypoint to, if the box offers one. */
	add: HTMLSelectElement | null;
	/** Asks for a place when a map was chosen without one. */
	hint: HTMLElement | null;
}

/** What the fields need from the map, once it is there. */
interface PickerMap {
	showPlace: ( place: Place | null ) => void;
	centre: ( place: Place ) => void;
	showRoute: ( url: string ) => void;
}

/** The form, and the map once it is there. */
interface Picking {
	form: Form;
	map: PickerMap | null;
}

/**
 * A number in decimal degrees, by the rule the server saves by: a dot or a
 * comma, and only the digits 0 to 9. See Waypoint_Coordinates::number().
 *
 * @param text  What the field holds.
 * @param limit The largest it may be, either way.
 */
export function readDegrees( text: string, limit: number ): number | null {
	const tidy = text.replace( /−/g, '-' ).trim();
	if ( ! /^[+-]?[0-9]{1,3}(?:[.,][0-9]+)?$/.test( tidy ) ) {
		return null;
	}
	const value = Number( tidy.replace( ',', '.' ) );
	return Math.abs( value ) <= limit ? value : null;
}

/**
 * A place from what the two fields hold, or null without both.
 *
 * @param lat The latitude field's text.
 * @param lon The longitude field's text.
 */
export function readPlace( lat: string, lon: string ): Place | null {
	const latitude = readDegrees( lat, MAX_LAT );
	const longitude = readDegrees( lon, MAX_LON );
	return null === latitude || null === longitude
		? null
		: { lat: latitude, lon: longitude };
}

/**
 * Degrees as the server stores them: at most 7 decimals, about a centimetre,
 * with no trailing zeros and no sign on zero.
 *
 * @param value Degrees.
 */
export function formatDegrees( value: number ): string {
	const text = value.toFixed( 7 ).replace( /\.?0+$/, '' );
	return '-0' === text ? '0' : text;
}

/**
 * The parts of the form, or null on a screen without them.
 *
 * @param root The picker's element.
 */
function findForm( root: HTMLElement ): Form | null {
	const doc = root.ownerDocument;
	const lat = doc.getElementById( 'gpxrm_lat' );
	const lon = doc.getElementById( 'gpxrm_lon' );
	if (
		! ( lat instanceof HTMLInputElement ) ||
		! ( lon instanceof HTMLInputElement )
	) {
		return null;
	}
	return {
		lat,
		lon,
		clear: root.querySelector( '.gpxrm-picker-clear' ),
		own: doc.querySelectorAll( '#gpxrm-waypoint-maps option[value="own"]' ),
		add: doc.querySelector( 'select#gpxrm_maps_add' ),
		hint: doc.querySelector( '.gpxrm-maps-add-place' ),
	};
}

/**
 * The place the form holds.
 *
 * @param form The form.
 */
function placeIn( form: Form ): Place | null {
	return readPlace( form.lat.value, form.lon.value );
}

/**
 * Keep the rest of the form in step with the place.
 *
 * @param form The form.
 */
function syncForm( form: Form ): void {
	const place = placeIn( form );
	if ( form.clear ) {
		form.clear.hidden = ! form.lat.value && ! form.lon.value;
	}
	form.own.forEach( ( option ) => {
		option.disabled = ! place;
	} );
	if ( form.hint ) {
		form.hint.hidden = ! form.add?.value || !! place;
	}
}

/**
 * The address of the file of the map chosen, or ''.
 *
 * @param form The form.
 */
function chosenRoute( form: Form ): string {
	const option = form.add?.selectedOptions[ 0 ];
	return option?.dataset.gpxrmGpx ?? '';
}

/**
 * Read a route from its file. A file on another site may not allow it, and
 * the choice of its map stands all the same, so that is no failure.
 *
 * @param url The file's address.
 */
async function readRoute( url: string ): Promise< ParsedGpx | null > {
	try {
		const response = await fetch( url );
		const parsed = parseGPX( response.ok ? await response.text() : '' );
		return parsed.invalid || ! parsed.coords.length ? null : parsed;
	} catch {
		return null;
	}
}

/**
 * Build the map on the form.
 *
 * @param root       The picker's element, with the site's tiles.
 * @param form       The form.
 * @param maplibregl MapLibre GL module.
 * @param chosen     Called with a place chosen on the map.
 */
function buildMap(
	root: HTMLElement,
	form: Form,
	maplibregl: MapLibreGl,
	chosen: ( lat: number, lon: number ) => void
): PickerMap {
	const place = placeIn( form );
	const tileMaxZoom = readTileMaxZoom( root );
	const map: MapLibreMap = createMap( {
		maplibregl,
		container: root.querySelector< HTMLElement >(
			'.gpxrm-picker-map'
		) as HTMLElement,
		style: buildRasterStyle(
			root.dataset.gpxrmTileUrl ?? '',
			root.dataset.gpxrmAttribution ?? '',
			tileMaxZoom
		),
		bounds: place
			? [
					[ place.lon, place.lat ],
					[ place.lon, place.lat ],
				]
			: WORLD,
		maxZoom: PLACE_ZOOM,
		tileMaxZoom,
		locale: readMapUi( root ),
		preview: true,
	} );

	let pin: ReturnType< typeof newPin > | null = null;

	/**
	 * A pin that can be dragged.
	 *
	 * @param at Where it goes.
	 */
	function newPin( at: Place ) {
		const marker = new maplibregl.Marker( {
			color: TRACK_COLOR,
			draggable: true,
		} )
			.setLngLat( [ at.lon, at.lat ] )
			.addTo( map );
		marker
			.getElement()
			.setAttribute( 'aria-label', root.dataset.gpxrmPinLabel ?? '' );
		marker.on( 'dragend', () => {
			const { lng, lat } = marker.getLngLat().wrap();
			chosen( lat, lng );
		} );
		return marker;
	}

	const showPlace = ( at: Place | null ) => {
		if ( ! at ) {
			pin?.remove();
			pin = null;
			return;
		}
		pin = pin ?? newPin( at );
		pin.setLngLat( [ at.lon, at.lat ] );
	};

	map.on( 'click', ( event ) => {
		const { lng, lat } = event.lngLat.wrap();
		chosen( lat, lng );
	} );

	const ready = new Promise< void >( ( resolve ) => {
		map.once( 'load', () => {
			map.addSource( ROUTE, { type: 'geojson', data: NO_ROUTE } );
			map.addLayer( {
				id: ROUTE,
				type: 'line',
				source: ROUTE,
				layout: { 'line-join': 'round', 'line-cap': 'round' },
				paint: { 'line-color': TRACK_COLOR, 'line-width': 4 },
			} );
			resolve();
		} );
	} );

	const drawRoute = ( route: ParsedGpx | null ) => {
		void ( map.getSource( ROUTE ) as GeoJSONSource )
			.setData(
				route
					? trackGeoJSON( route.coords, route.segmentStarts )
					: NO_ROUTE
			)
			?.catch( () => {} );
		if ( route ) {
			map.fitBounds( computeBoundsFromCoords( route.coords ), {
				padding: 48,
				maxZoom: 15,
			} );
		}
	};

	let asked = 0;
	const showRoute = ( url: string ) => {
		asked++;
		const mine = asked;
		const route = url ? readRoute( url ) : Promise.resolve( null );
		void Promise.all( [ route, ready ] ).then( ( [ read ] ) => {
			// Another map may have been chosen while this one's file loaded.
			if ( mine === asked ) {
				drawRoute( read );
			}
		} );
	};

	showPlace( place );

	return {
		showPlace,
		centre: ( at ) => {
			map.easeTo( { center: [ at.lon, at.lat ] } );
		},
		showRoute,
	};
}

/**
 * Keep the rest of the form and the pin in step with the fields.
 *
 * @param picking The picker.
 */
function followFields( picking: Picking ): void {
	syncForm( picking.form );
	picking.map?.showPlace( placeIn( picking.form ) );
}

/**
 * Put a place in the fields.
 *
 * @param picking The picker.
 * @param lat     The latitude's text.
 * @param lon     The longitude's text.
 */
function fillFields( picking: Picking, lat: string, lon: string ): void {
	picking.form.lat.value = lat;
	picking.form.lon.value = lon;
	followFields( picking );
}

/**
 * Bring the place in the fields to the middle of the map, if there is one.
 *
 * @param picking The picker.
 */
function centreOnPlace( picking: Picking ): void {
	const place = placeIn( picking.form );
	if ( place ) {
		picking.map?.centre( place );
	}
}

/**
 * Show the route of the map chosen. With none chosen, a route that took the
 * view elsewhere has gone, so go back to the place.
 *
 * @param picking The picker.
 */
function followChoice( picking: Picking ): void {
	const route = chosenRoute( picking.form );
	syncForm( picking.form );
	picking.map?.showRoute( route );
	if ( ! route ) {
		centreOnPlace( picking );
	}
}

/**
 * Listen to the form.
 *
 * @param picking The picker.
 */
function bindForm( picking: Picking ): void {
	const { form } = picking;
	[ form.lat, form.lon ].forEach( ( input ) => {
		input.addEventListener( 'input', () => followFields( picking ) );
		input.addEventListener( 'change', () => centreOnPlace( picking ) );
	} );
	form.clear?.addEventListener( 'click', () =>
		fillFields( picking, '', '' )
	);
	form.add?.addEventListener( 'change', () => followChoice( picking ) );
}

/**
 * Say the map could not load. The fields work all the same.
 *
 * @param root The picker's element.
 */
function showFailure( root: HTMLElement ): void {
	const mapEl = root.querySelector< HTMLElement >( '.gpxrm-picker-map' );
	const failed = root.querySelector< HTMLElement >( '.gpxrm-picker-failed' );
	if ( mapEl && failed ) {
		mapEl.hidden = true;
		failed.hidden = false;
	}
}

/**
 * Tie the map and the form together.
 *
 * @param root The picker's element.
 * @param load Loads MapLibre.
 */
export function initPicker(
	root: HTMLElement,
	load: () => Promise< MapLibreGl >
): void {
	const form = findForm( root );
	if ( ! form ) {
		return;
	}

	const picking: Picking = { form, map: null };
	bindForm( picking );
	syncForm( form );

	load()
		.then( ( maplibregl ) => {
			picking.map = buildMap( root, form, maplibregl, ( lat, lon ) =>
				fillFields(
					picking,
					formatDegrees( lat ),
					formatDegrees( lon )
				)
			);
			if ( chosenRoute( form ) ) {
				picking.map.showRoute( chosenRoute( form ) );
			}
		} )
		.catch( () => showFailure( root ) );
}
