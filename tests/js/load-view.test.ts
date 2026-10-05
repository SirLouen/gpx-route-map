/**
 * Loading the front-end view module into the editor canvas.
 *
 * The canvas is usually an iframe with a document of its own, which WordPress
 * gives no script modules, so the editor adds the view module itself. jsdom
 * never fetches scripts, so these tests fire the script's load and error
 * events by hand.
 */

import { describe, expect, it } from 'vitest';

import { loadViewModule } from '../../src/editor/load-view';

const SRC =
	'https://example.test/wp-content/plugins/gpx-route-map/build/view.js?ver=1';

/**
 * The module scripts in a document.
 *
 * @param doc Document.
 */
function scripts( doc: Document ): HTMLScriptElement[] {
	return [ ...doc.querySelectorAll< HTMLScriptElement >( 'script' ) ];
}

/** A fresh document, as each editor canvas is. */
function canvas(): Document {
	return document.implementation.createHTMLDocument( 'canvas' );
}

describe( 'loadViewModule', () => {
	it( 'adds the module once per document, however often it is asked', async () => {
		const doc = canvas();

		const first = loadViewModule( doc, SRC );
		const second = loadViewModule( doc, SRC );
		expect( scripts( doc ) ).toHaveLength( 1 );
		expect( scripts( doc )[ 0 ].type ).toBe( 'module' );
		expect( scripts( doc )[ 0 ].src ).toBe( SRC );

		scripts( doc )[ 0 ].dispatchEvent( new Event( 'load' ) );
		await expect( first ).resolves.toBeUndefined();
		await expect( second ).resolves.toBeUndefined();
		await expect( loadViewModule( doc, SRC ) ).resolves.toBeUndefined();
		expect( scripts( doc ) ).toHaveLength( 1 );
	} );

	it( 'loads it separately into each document', () => {
		const a = canvas();
		const b = canvas();

		loadViewModule( a, SRC );
		loadViewModule( b, SRC );

		expect( scripts( a ) ).toHaveLength( 1 );
		expect( scripts( b ) ).toHaveLength( 1 );
	} );

	it( 'fails when the module cannot load, and tries again next time', async () => {
		const doc = canvas();

		const failing = loadViewModule( doc, SRC );
		scripts( doc )[ 0 ].dispatchEvent( new Event( 'error' ) );
		await expect( failing ).rejects.toThrow();

		loadViewModule( doc, SRC );
		expect( scripts( doc ) ).toHaveLength( 1 );
	} );
} );
