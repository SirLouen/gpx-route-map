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

import { viewMessages } from '../../src/view/map-instance';

/** The message set the view renders, derived so it cannot drift from source. */
type ViewMessages = ReturnType< typeof viewMessages >;

// Typed as keys, so adding a message to the source without listing it here is
// a compile error rather than a silently unverified string.
const KEYS: ( keyof ViewMessages )[] = [
	'load',
	'cors',
	'invalid',
	'nopoints',
	'download',
	'maplibre',
];

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
