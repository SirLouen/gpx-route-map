/**
 * The map on a Waypoint post's screen: clicking or dragging fills the
 * latitude and longitude the form sends, and those fields move the pin.
 *
 * MapLibre needs WebGL, which jsdom lacks, so a fake stands in for it and
 * records what is done to the map.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
	formatDegrees,
	initPicker,
	readDegrees,
	readPlace,
} from '../../src/admin/coordinate-picker';
import type { MapLibreGl } from '../../src/view/types';

const GPX =
	'<gpx><trk><trkseg>' +
	'<trkpt lat="43.0" lon="-6.0"/><trkpt lat="43.1" lon="-6.2"/>' +
	'</trkseg></trk></gpx>';

/** A stand-in for the MapLibre module that records what is done to it. */
function fakeMapLibre() {
	const state = {
		maps: [] as FakeMap[],
		markers: [] as FakeMarker[],
	};

	class FakeMarker {
		options: Record< string, unknown >;
		lngLat: [ number, number ] = [ 0, 0 ];
		removed = false;
		element = document.createElement( 'div' );
		handlers: Record< string, () => void > = {};
		constructor( options: Record< string, unknown > ) {
			this.options = options;
			state.markers.push( this );
		}
		setLngLat( lngLat: [ number, number ] ) {
			this.lngLat = lngLat;
			return this;
		}
		getLngLat() {
			const [ lng, lat ] = this.lngLat;
			return { wrap: () => ( { lng: wrapped( lng ), lat } ) };
		}
		getElement() {
			return this.element;
		}
		addTo() {
			return this;
		}
		remove() {
			this.removed = true;
			return this;
		}
		on( type: string, fn: () => void ) {
			this.handlers[ type ] = fn;
			return this;
		}
	}

	class FakeMap {
		options: Record< string, unknown >;
		handlers: Record< string, Array< ( e?: unknown ) => void > > = {};
		sources: Record< string, unknown > = {};
		layers: string[] = [];
		fitted: unknown[] = [];
		eased: unknown[] = [];
		constructor( options: Record< string, unknown > ) {
			this.options = options;
			state.maps.push( this );
		}
		on( type: string, fn: ( e?: unknown ) => void ) {
			( this.handlers[ type ] ??= [] ).push( fn );
			return this;
		}
		once( type: string, fn: ( e?: unknown ) => void ) {
			return this.on( type, fn );
		}
		fire( type: string, event?: unknown ) {
			( this.handlers[ type ] ?? [] ).forEach( ( fn ) => fn( event ) );
		}
		addControl() {
			return this;
		}
		addSource( id: string, source: { data: unknown } ) {
			this.sources[ id ] = source.data;
		}
		addLayer( layer: { id: string } ) {
			this.layers.push( layer.id );
		}
		getSource( id: string ) {
			return {
				setData: ( data: unknown ) => {
					this.sources[ id ] = data;
				},
			};
		}
		fitBounds( ...args: unknown[] ) {
			this.fitted.push( args );
		}
		easeTo( options: unknown ) {
			this.eased.push( options );
		}
	}

	const control = () => class {};
	const lib = {
		Map: FakeMap,
		Marker: FakeMarker,
		NavigationControl: control(),
		ScaleControl: control(),
		FullscreenControl: control(),
		GeolocateControl: control(),
	} as unknown as MapLibreGl;

	return { lib, ...state };
}

type FakeMap = ReturnType< typeof fakeMapLibre >[ 'maps' ][ number ];

/**
 * A longitude brought back to ±180, as MapLibre's wrap() does.
 *
 * @param lng Longitude.
 */
function wrapped( lng: number ): number {
	return ( ( ( ( lng + 180 ) % 360 ) + 360 ) % 360 ) - 180;
}

/**
 * The box as the server prints it.
 *
 * @param stored     The stored place.
 * @param stored.lat Its latitude.
 * @param stored.lon Its longitude.
 */
function mount( { lat, lon } = { lat: '', lon: '' } ) {
	document.body.innerHTML = `
		<div id="gpxrm-waypoint-maps">
			<fieldset class="gpxrm-picker" data-gpxrm-tile-url="https://tiles.test/{z}/{x}/{y}.png" data-gpxrm-attribution="Tiles" data-gpxrm-tile-max-zoom="19" data-gpxrm-pin-label="Waypoint's place">
				<div class="gpxrm-picker-map"></div>
				<p class="gpxrm-picker-failed" hidden>Failed</p>
				<input id="gpxrm_lat" value="${ lat }">
				<input id="gpxrm_lon" value="${ lon }">
				<button type="button" class="gpxrm-picker-clear" hidden>Clear</button>
			</fieldset>
			<select id="gpxrm_maps_add">
				<option value="">Choose a map</option>
				<option value="100:b:0:abc" data-gpxrm-gpx="https://example.test/a.gpx">A</option>
				<option value="101:b:0:abc" data-gpxrm-gpx="https://example.test/b.gpx">B</option>
			</select>
			<p class="gpxrm-maps-add-place" hidden>Choose its place</p>
			<select><option value="keep">Keep</option><option value="own" disabled>Own</option></select>
		</div>`;

	const field = ( id: string ) =>
		document.getElementById( id ) as HTMLInputElement;

	return {
		root: document.querySelector( '.gpxrm-picker' ) as HTMLElement,
		lat: field( 'gpxrm_lat' ),
		lon: field( 'gpxrm_lon' ),
		add: document.getElementById( 'gpxrm_maps_add' ) as HTMLSelectElement,
		clear: document.querySelector(
			'.gpxrm-picker-clear'
		) as HTMLButtonElement,
		hint: document.querySelector( '.gpxrm-maps-add-place' ) as HTMLElement,
		own: document.querySelector(
			'option[value="own"]'
		) as HTMLOptionElement,
		failed: document.querySelector( '.gpxrm-picker-failed' ) as HTMLElement,
		mapEl: document.querySelector( '.gpxrm-picker-map' ) as HTMLElement,
	};
}

/**
 * Type into a field.
 *
 * @param input The field.
 * @param value What is typed.
 */
function typeInto( input: HTMLInputElement, value: string ) {
	input.value = value;
	input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
}

/**
 * Choose a map to add the waypoint to.
 *
 * @param add   The select.
 * @param value The option's value.
 */
function choose( add: HTMLSelectElement, value: string ) {
	add.value = value;
	add.dispatchEvent( new Event( 'change', { bubbles: true } ) );
}

/**
 * Click the map.
 *
 * @param map    The map.
 * @param at     Where.
 * @param at.lng Longitude.
 * @param at.lat Latitude.
 */
function clickMap( map: FakeMap, { lng, lat }: { lng: number; lat: number } ) {
	map.fire( 'click', {
		lngLat: { wrap: () => ( { lng: wrapped( lng ), lat } ) },
	} );
}

/** Let promises settle. */
const settle = async () => {
	for ( let i = 0; i < 10; i++ ) {
		await Promise.resolve();
	}
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
};

beforeEach( () => {
	vi.stubGlobal(
		'fetch',
		vi.fn( () =>
			Promise.resolve( {
				ok: true,
				text: () => Promise.resolve( GPX ),
			} as Response )
		)
	);
} );

afterEach( () => {
	vi.unstubAllGlobals();
	document.body.innerHTML = '';
} );

describe( 'readDegrees', () => {
	it.each( [
		[ '50.75', 50.75 ],
		[ '50,75', 50.75 ],
		[ ' +5.93 ', 5.93 ],
		[ '−33.86', -33.86 ],
		[ ' 50.75 ', 50.75 ],
		[ '0', 0 ],
		[ '-90', -90 ],
		[ '90', 90 ],
	] )( 'reads %s as the server does', ( text, want ) => {
		expect( readDegrees( text, 90 ) ).toBe( want );
	} );

	it.each( [
		[ '' ],
		[ '91' ],
		[ '90.0001' ],
		[ '1e1' ],
		[ '0x1A' ],
		[ '50.75.1' ],
		[ '1,234.5' ],
		[ '50.75 N' ],
		[ '٥٠' ],
		[ '.5' ],
		[ '5.' ],
	] )( 'does not read %s', ( text ) => {
		expect( readDegrees( text, 90 ) ).toBeNull();
	} );
} );

describe( 'readPlace', () => {
	it( 'needs both a latitude and a longitude', () => {
		expect( readPlace( '50.75', '5.93' ) ).toEqual( {
			lat: 50.75,
			lon: 5.93,
		} );
		expect( readPlace( '50.75', '' ) ).toBeNull();
		expect( readPlace( '', '5.93' ) ).toBeNull();
	} );

	it( 'keeps each to its own limit', () => {
		expect( readPlace( '5', '170' ) ).toEqual( { lat: 5, lon: 170 } );
		expect( readPlace( '170', '5' ) ).toBeNull();
		expect( readPlace( '5', '181' ) ).toBeNull();
	} );

	it( 'takes zero for a place', () => {
		expect( readPlace( '0', '0' ) ).toEqual( { lat: 0, lon: 0 } );
	} );
} );

describe( 'formatDegrees', () => {
	it.each( [
		[ 50.75, '50.75' ],
		[ 5.123456789, '5.1234568' ],
		[ -0.00000001, '0' ],
		[ 0, '0' ],
		[ 180, '180' ],
		[ -6.5, '-6.5' ],
	] )( 'writes %s as the server stores it', ( value, want ) => {
		expect( formatDegrees( value ) ).toBe( want );
	} );
} );

describe( 'the picker', () => {
	it( 'opens on the stored place, with its pin', async () => {
		const { lib, maps, markers } = fakeMapLibre();
		const { root } = mount( { lat: '50.75', lon: '5.93' } );

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();

		expect( maps[ 0 ].options.bounds ).toEqual( [
			[ 5.93, 50.75 ],
			[ 5.93, 50.75 ],
		] );
		expect( markers ).toHaveLength( 1 );
		expect( markers[ 0 ].lngLat ).toEqual( [ 5.93, 50.75 ] );
		expect( markers[ 0 ].options.draggable ).toBe( true );
		expect( markers[ 0 ].element.getAttribute( 'aria-label' ) ).toBe(
			"Waypoint's place"
		);
	} );

	it( 'opens on the world without a place, with no pin', async () => {
		const { lib, maps, markers } = fakeMapLibre();
		const { root } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();

		expect(
			( maps[ 0 ].options.bounds as number[][] )[ 0 ][ 0 ]
		).toBeLessThan( -100 );
		expect( markers ).toHaveLength( 0 );
	} );

	it( "uses the site's tiles, and leaves out what a screen has no use for", async () => {
		const { lib, maps } = fakeMapLibre();
		const { root } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();

		const style = maps[ 0 ].options.style as {
			sources: { osm: { tiles: string[]; attribution: string } };
		};
		expect( style.sources.osm.tiles ).toEqual( [
			'https://tiles.test/{z}/{x}/{y}.png',
		] );
		expect( style.sources.osm.attribution ).toBe( 'Tiles' );
		expect( maps[ 0 ].options.maxZoom ).toBe( 18 );
	} );

	it( 'puts a click on the map into the fields, and drops the pin', async () => {
		const { lib, maps, markers } = fakeMapLibre();
		const { root, lat, lon } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		clickMap( maps[ 0 ], { lng: -6.123456789, lat: 43.5 } );

		expect( lat.value ).toBe( '43.5' );
		expect( lon.value ).toBe( '-6.1234568' );
		expect( markers ).toHaveLength( 1 );
		expect( markers[ 0 ].lngLat ).toEqual( [ -6.1234568, 43.5 ] );
	} );

	it( 'moves the same pin on the next click', async () => {
		const { lib, maps, markers } = fakeMapLibre();
		const { root } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		clickMap( maps[ 0 ], { lng: -6, lat: 43 } );
		clickMap( maps[ 0 ], { lng: -7, lat: 44 } );

		expect( markers ).toHaveLength( 1 );
		expect( markers[ 0 ].lngLat ).toEqual( [ -7, 44 ] );
	} );

	it( 'brings a click on another copy of the world back to ±180', async () => {
		const { lib, maps } = fakeMapLibre();
		const { root, lon } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		clickMap( maps[ 0 ], { lng: 190, lat: 10 } );

		expect( lon.value ).toBe( '-170' );
	} );

	it( 'fills the fields when the pin is dragged', async () => {
		const { lib, markers } = fakeMapLibre();
		const { root, lat, lon } = mount( { lat: '50.75', lon: '5.93' } );

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		markers[ 0 ].setLngLat( [ 6.5, 51.25 ] );
		markers[ 0 ].handlers.dragend();

		expect( lat.value ).toBe( '51.25' );
		expect( lon.value ).toBe( '6.5' );
	} );

	it( 'moves the pin as coordinates are typed', async () => {
		const { lib, maps, markers } = fakeMapLibre();
		const { root, lat, lon } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		typeInto( lat, '50,75' );
		expect( markers ).toHaveLength( 0 );

		typeInto( lon, '5,93' );
		expect( markers[ 0 ].lngLat ).toEqual( [ 5.93, 50.75 ] );
		expect( maps[ 0 ].eased.at( -1 ) ).toEqual( {
			center: [ 5.93, 50.75 ],
		} );
	} );

	it( 'keeps what was typed as it was typed', async () => {
		const { lib } = fakeMapLibre();
		const { root, lat, lon } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		typeInto( lat, '50,750' );
		typeInto( lon, '5,93' );

		expect( lat.value ).toBe( '50,750' );
	} );

	it( 'takes the pin away when the fields stop being a place', async () => {
		const { lib, markers } = fakeMapLibre();
		const { root, lat } = mount( { lat: '50.75', lon: '5.93' } );

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		typeInto( lat, '95' );

		expect( markers[ 0 ].removed ).toBe( true );
	} );

	it( 'clears the place with one button, which only shows with something to clear', async () => {
		const { lib, markers } = fakeMapLibre();
		const { root, lat, lon, clear } = mount( {
			lat: '50.75',
			lon: '5.93',
		} );

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		expect( clear.hidden ).toBe( false );

		clear.click();

		expect( lat.value ).toBe( '' );
		expect( lon.value ).toBe( '' );
		expect( markers[ 0 ].removed ).toBe( true );
		expect( clear.hidden ).toBe( true );
	} );

	it( "offers moving a link to the waypoint's coordinates only with a place", async () => {
		const { lib, maps } = fakeMapLibre();
		const { root, own, clear } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		expect( own.disabled ).toBe( true );

		clickMap( maps[ 0 ], { lng: -6, lat: 43 } );
		expect( own.disabled ).toBe( false );

		clear.click();
		expect( own.disabled ).toBe( true );
	} );

	it( 'asks for a place when a map is chosen without one', async () => {
		const { lib, maps } = fakeMapLibre();
		const { root, add, hint } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		expect( hint.hidden ).toBe( true );

		choose( add, '100:b:0:abc' );
		expect( hint.hidden ).toBe( false );

		clickMap( maps[ 0 ], { lng: -6, lat: 43 } );
		expect( hint.hidden ).toBe( true );
	} );

	it( 'shows the route of a map chosen, and goes to it', async () => {
		const { lib, maps } = fakeMapLibre();
		const { root, add } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		maps[ 0 ].fire( 'load' );
		choose( add, '100:b:0:abc' );
		await settle();

		expect( fetch ).toHaveBeenCalledWith( 'https://example.test/a.gpx' );
		const route = maps[ 0 ].sources[ 'gpxrm-picker-route' ] as {
			features: Array< { geometry: { coordinates: number[][][] } } >;
		};
		expect( route.features[ 0 ].geometry.coordinates ).toEqual( [
			[
				[ -6, 43 ],
				[ -6.2, 43.1 ],
			],
		] );
		expect( maps[ 0 ].fitted[ 0 ] ).toEqual( [
			[
				[ -6.2, 43 ],
				[ -6, 43.1 ],
			],
			{ padding: 48, maxZoom: 15 },
		] );
	} );

	it( 'shows the route of a map chosen before the map had loaded', async () => {
		const { lib, maps } = fakeMapLibre();
		const { root, add } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		choose( add, '100:b:0:abc' );
		await settle();
		maps[ 0 ].fire( 'load' );
		await settle();

		expect( maps[ 0 ].fitted ).toHaveLength( 1 );
	} );

	it( 'takes the route away when no map is chosen', async () => {
		const { lib, maps } = fakeMapLibre();
		const { root, add } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		maps[ 0 ].fire( 'load' );
		choose( add, '100:b:0:abc' );
		await settle();
		choose( add, '' );
		await settle();

		const route = maps[ 0 ].sources[ 'gpxrm-picker-route' ] as {
			features: unknown[];
		};
		expect( route.features ).toEqual( [] );
	} );

	it( 'goes back to the place when no map is chosen any more', async () => {
		const { lib, maps } = fakeMapLibre();
		const { root, add } = mount( { lat: '50.75', lon: '5.93' } );

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		maps[ 0 ].fire( 'load' );
		choose( add, '100:b:0:abc' );
		await settle();
		choose( add, '' );
		await settle();

		expect( maps[ 0 ].eased.at( -1 ) ).toEqual( {
			center: [ 5.93, 50.75 ],
		} );
	} );

	it( 'stays where it is with no map chosen and no place', async () => {
		const { lib, maps } = fakeMapLibre();
		const { root, add } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		maps[ 0 ].fire( 'load' );
		choose( add, '100:b:0:abc' );
		await settle();
		choose( add, '' );
		await settle();

		expect( maps[ 0 ].eased ).toEqual( [] );
	} );

	it( 'shows only the last of two routes asked for', async () => {
		const answers: Array< ( gpx: string ) => void > = [];
		vi.stubGlobal(
			'fetch',
			vi.fn(
				() =>
					new Promise( ( resolve ) => {
						answers.push( ( gpx ) =>
							resolve( {
								ok: true,
								text: () => Promise.resolve( gpx ),
							} as Response )
						);
					} )
			)
		);
		const { lib, maps } = fakeMapLibre();
		const { root, add } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		maps[ 0 ].fire( 'load' );
		choose( add, '100:b:0:abc' );
		choose( add, '101:b:0:abc' );
		await settle();

		answers[ 1 ](
			'<gpx><trk><trkseg><trkpt lat="1" lon="1"/><trkpt lat="2" lon="2"/></trkseg></trk></gpx>'
		);
		await settle();
		answers[ 0 ]( GPX );
		await settle();

		expect( maps[ 0 ].fitted ).toHaveLength( 1 );
		expect( ( maps[ 0 ].fitted[ 0 ] as unknown[][] )[ 0 ] ).toEqual( [
			[ 1, 1 ],
			[ 2, 2 ],
		] );
	} );

	// A file on another site may not allow it. The choice still stands.
	it( 'carries on when a route cannot be read', async () => {
		vi.stubGlobal(
			'fetch',
			vi.fn( () => Promise.reject( new TypeError( 'blocked' ) ) )
		);
		const { lib, maps } = fakeMapLibre();
		const { root, add, failed } = mount();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		maps[ 0 ].fire( 'load' );
		choose( add, '100:b:0:abc' );
		await settle();

		expect( maps[ 0 ].fitted ).toHaveLength( 0 );
		expect( failed.hidden ).toBe( true );
		expect( add.value ).toBe( '100:b:0:abc' );
	} );

	it( 'leaves the fields working when the map cannot load', async () => {
		const { root, failed, mapEl, own, lat, lon, clear } = mount();

		initPicker( root, () => Promise.reject( new Error( 'no WebGL' ) ) );
		await settle();

		expect( failed.hidden ).toBe( false );
		expect( mapEl.hidden ).toBe( true );

		typeInto( lat, '50.75' );
		typeInto( lon, '5.93' );
		expect( own.disabled ).toBe( false );
		expect( clear.hidden ).toBe( false );
	} );

	it( 'works on a screen with no map to add to', async () => {
		const { lib, maps } = fakeMapLibre();
		const { root, add, lat } = mount();
		add.remove();

		initPicker( root, () => Promise.resolve( lib ) );
		await settle();
		clickMap( maps[ 0 ], { lng: -6, lat: 43 } );

		expect( lat.value ).toBe( '43' );
	} );
} );
