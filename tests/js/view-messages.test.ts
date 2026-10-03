/**
 * Unit tests for the front-end message payload.
 *
 * The view runs as a script module and cannot use wp_set_script_translations,
 * so every user-facing string is translated server-side and handed over as a
 * JSON `data-gpxrm-i18n` attribute. A key missing from that payload silently
 * degrades to English, which is how the "Map failed to load" message stayed
 * untranslated until 1.6.1 - hence the coverage.
 */

import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { viewMessages } from '../../src/view/map-instance';

/** The message set the view renders, derived so it cannot drift from source. */
type ViewMessages = ReturnType< typeof viewMessages >;

/**
 * The keys both sides must agree on, from the fixture tests/ViewMessagesTest.php
 * checks the PHP payload against.
 *
 * Read at runtime, so the type annotation checks nothing by itself; drift is
 * caught by the fallback test at the bottom, which compares this list with the
 * keys the view actually defines.
 */
const KEYS: ( keyof ViewMessages )[] = JSON.parse(
	fs.readFileSync(
		path.join(
			path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) ),
			'fixtures',
			'view-message-keys.json'
		),
		'utf8'
	)
).keys;

/**
 * Build a map element carrying an i18n payload.
 *
 * A real element rather than a stub, so `dataset` behaves as it does in a
 * browser.
 *
 * @param value Raw attribute value, or undefined to omit it.
 */
function elementWith( value?: string ): HTMLElement {
	const el = document.createElement( 'div' );
	if ( undefined !== value ) {
		el.dataset.gpxrmI18n = value;
	}
	return el;
}

describe( 'viewMessages', () => {
	it( 'falls back to English when the attribute is absent', () => {
		const messages = viewMessages( elementWith( undefined ) );

		for ( const key of KEYS ) {
			expect( messages[ key ] ).toBeTypeOf( 'string' );
			expect( messages[ key ].length ).toBeGreaterThan( 0 );
		}
	} );

	it( 'uses the server-provided translation', () => {
		const messages = viewMessages(
			elementWith(
				JSON.stringify( {
					maplibre: 'No se ha podido cargar el mapa.',
				} )
			)
		);

		expect( messages.maplibre ).toBe( 'No se ha podido cargar el mapa.' );
	} );

	it( 'keeps the English fallback for keys the server omits', () => {
		const messages = viewMessages(
			elementWith( JSON.stringify( { load: 'Traducido' } ) )
		);

		expect( messages.load ).toBe( 'Traducido' );
		// A partial payload must not blank out the other messages.
		for ( const key of KEYS ) {
			expect( messages[ key ] ).toBeTypeOf( 'string' );
			expect( messages[ key ].length ).toBeGreaterThan( 0 );
		}
	} );

	it( 'survives malformed JSON rather than throwing', () => {
		const messages = viewMessages( elementWith( '{not json' ) );

		expect( messages.maplibre ).toBeTypeOf( 'string' );
		expect( messages.maplibre.length ).toBeGreaterThan( 0 );
	} );
} );

describe( 'the English fallback', () => {
	it( 'defines exactly the keys the server sends', () => {
		// Without the attribute, viewMessages() returns the fallback as is.
		const fallback = Object.keys(
			viewMessages( elementWith( undefined ) )
		);

		expect( [ ...fallback ].sort() ).toEqual( [ ...KEYS ].sort() );
	} );
} );
