/**
 * MapLibre's interface strings: which ones are translated, and how the view
 * reads them.
 *
 * MapLibre takes translations by its own string IDs and silently keeps its
 * English for any it is not given, so nothing fails at runtime when a MapLibre
 * update adds a string or renames one. These tests are where that shows up.
 */

import { describe, expect, it, vi } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

import { readMapUi } from '../../src/view/map-ui';
import { createMap } from '../../src/view/map-core';
import type { MapLibreGl } from '../../src/view/types';

const fixture: { sent: string[]; notShown: Record< string, string > } =
	JSON.parse(
		fs.readFileSync(
			path.join(
				path.dirname(
					path.dirname( fileURLToPath( import.meta.url ) )
				),
				'fixtures',
				'map-ui-keys.json'
			),
			'utf8'
		)
	);

/**
 * Every string ID the installed MapLibre defines, from its own source.
 *
 * MapLibre does not export the table, but it ships the source file it is
 * built from.
 */
function mapLibreStringIds(): string[] {
	const require = createRequire( import.meta.url );
	const file = path.join(
		path.dirname( require.resolve( 'maplibre-gl/package.json' ) ),
		'src',
		'ui',
		'default_locale.ts'
	);
	const source = fs.readFileSync( file, 'utf8' );
	return [ ...source.matchAll( /^\s*'([\w.]+)'\s*:/gm ) ].map(
		( m ) => m[ 1 ]
	);
}

/**
 * Build a map element carrying a payload.
 *
 * @param value Raw attribute value, or undefined to omit it.
 */
function elementWith( value?: string ): HTMLElement {
	const el = document.createElement( 'div' );
	if ( undefined !== value ) {
		el.dataset.gpxrmMapUi = value;
	}
	return el;
}

describe( 'the translated strings', () => {
	it( 'account for every string MapLibre has', () => {
		const ids = mapLibreStringIds();
		// Guards the parse: a changed file format must not pass as "no strings".
		expect( ids.length ).toBeGreaterThan( 20 );

		const accounted = [
			...fixture.sent,
			...Object.keys( fixture.notShown ),
		];
		expect( [ ...accounted ].sort() ).toEqual( [ ...ids ].sort() );
	} );

	it( 'are either sent or not shown, never both', () => {
		const both = fixture.sent.filter( ( id ) => id in fixture.notShown );
		expect( both ).toEqual( [] );
	} );
} );

describe( 'readMapUi', () => {
	it( 'passes the translations through', () => {
		const strings = {
			'NavigationControl.ZoomIn': 'Acercar',
			'Popup.Close': 'Cerrar ventana emergente',
		};

		expect( readMapUi( elementWith( JSON.stringify( strings ) ) ) ).toEqual(
			strings
		);
	} );

	it.each( [
		[ 'absent', undefined ],
		[ 'empty', '' ],
		[ 'malformed', '{not json' ],
		[ 'null', 'null' ],
		[ 'an array', '["Acercar"]' ],
		[ 'a string', '"Acercar"' ],
		[ 'a number', '42' ],
	] )( 'falls back to English when the attribute is %s', ( _label, raw ) => {
		expect( readMapUi( elementWith( raw ) ) ).toEqual( {} );
	} );

	// MapLibre throws on a null string, which stops the map from building at
	// all; an empty or blank one would leave the button with no name.
	it( 'drops anything that is not a string with something in it', () => {
		const strings = readMapUi(
			elementWith(
				JSON.stringify( {
					'NavigationControl.ZoomIn': 'Acercar',
					'NavigationControl.ZoomOut': null,
					'FullscreenControl.Enter': '',
					'FullscreenControl.Exit': ' \t\n',
					'GeolocateControl.FindMyLocation': 3,
					'Popup.Close': { text: 'Cerrar' },
				} )
			)
		);

		expect( strings ).toEqual( { 'NavigationControl.ZoomIn': 'Acercar' } );
	} );
} );

describe( 'createMap', () => {
	it( 'hands the translations to MapLibre', () => {
		const Map = vi.fn( function ( this: { addControl: () => void } ) {
			this.addControl = vi.fn();
		} );
		const Control = vi.fn();
		const maplibregl = {
			Map,
			NavigationControl: Control,
			ScaleControl: Control,
			FullscreenControl: Control,
			GeolocateControl: Control,
		} as unknown as MapLibreGl;
		const locale = { 'NavigationControl.ZoomIn': 'Acercar' };

		createMap( {
			maplibregl,
			container: document.createElement( 'div' ),
			style: { version: 8, sources: {}, layers: [] },
			bounds: [
				[ 0, 0 ],
				[ 1, 1 ],
			],
			locale,
		} );

		expect( Map ).toHaveBeenCalledWith(
			expect.objectContaining( { locale } )
		);
	} );
} );
