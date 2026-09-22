/**
 * The editor's insecure-tile-URL check.
 *
 * The warning exists because the failure it predicts is completely silent:
 * MapLibre fetches tiles with fetch(), which is blockable mixed content, so
 * an http tile URL on an https page is refused outright. The track, markers,
 * stats bar and elevation profile all still render, over a blank basemap,
 * and nothing is logged.
 */

import { describe, expect, it } from 'vitest';

import { tileUrlWillBeBlocked } from '../../src/tile-url';

describe( 'tileUrlWillBeBlocked', () => {
	it( 'warns about an http tile URL on an https page', () => {
		expect(
			tileUrlWillBeBlocked(
				'http://tiles.example.com/{z}/{x}/{y}.png',
				'https:'
			)
		).toBe( true );
	} );

	it( 'says nothing about an https tile URL', () => {
		expect(
			tileUrlWillBeBlocked(
				'https://tiles.example.com/{z}/{x}/{y}.png',
				'https:'
			)
		).toBe( false );
	} );

	// A plain-http site has no mixed content to worry about, and this is what
	// a wp-env dev environment looks like, so the warning must stay quiet.
	it( 'stays quiet on an http page, whatever the tile URL', () => {
		for ( const url of [
			'http://localhost:8081/tiles/{z}/{x}/{y}.png',
			'http://tiles.example.com/{z}/{x}/{y}.png',
			'https://tiles.example.com/{z}/{x}/{y}.png',
		] ) {
			expect( tileUrlWillBeBlocked( url, 'http:' ) ).toBe( false );
		}
	} );

	it( 'says nothing about an empty field', () => {
		expect( tileUrlWillBeBlocked( '', 'https:' ) ).toBe( false );
		expect( tileUrlWillBeBlocked( '   ', 'https:' ) ).toBe( false );
	} );

	// Leading whitespace is preserved by the field but ignored by the browser,
	// so it must not hide the warning.
	it( 'sees through leading whitespace and odd casing', () => {
		expect(
			tileUrlWillBeBlocked( '  http://t.example/{z}.png', 'https:' )
		).toBe( true );
		expect(
			tileUrlWillBeBlocked( 'HTTP://t.example/{z}.png', 'https:' )
		).toBe( true );
	} );

	// Substrings must not trigger it: only the scheme counts.
	it( 'does not fire on an https URL that merely mentions http', () => {
		expect(
			tileUrlWillBeBlocked(
				'https://proxy.example/?upstream=http://a.test/{z}.png',
				'https:'
			)
		).toBe( false );
	} );
} );
