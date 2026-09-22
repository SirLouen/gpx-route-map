/**
 * The editor's tile-URL checks.
 *
 * Both problems these catch fail the same silent way: a blank basemap with
 * the track, markers, stats bar and elevation profile drawn over nothing, and
 * nothing logged, because the view registers no MapLibre error handler. The
 * editor is the only place either can be noticed.
 */

import { describe, expect, it } from 'vitest';

import { tileUrlProblem } from '../../src/tile-url';

const HTTPS = 'https:';
const HTTP = 'http:';

describe( 'tileUrlProblem', () => {
	describe( 'insecure: the browser refuses the request', () => {
		it( 'flags an http tile URL on an https page', () => {
			expect(
				tileUrlProblem(
					'http://tiles.example.com/{z}/{x}/{y}.png',
					HTTPS
				)
			).toBe( 'insecure' );
		} );

		it( 'accepts an https tile URL', () => {
			expect(
				tileUrlProblem(
					'https://tiles.example.com/{z}/{x}/{y}.png',
					HTTPS
				)
			).toBeNull();
		} );

		// A plain-http site has no mixed content, and this is what a wp-env dev
		// environment looks like, so the warning must stay quiet there.
		it( 'stays quiet about http on an http page', () => {
			expect(
				tileUrlProblem(
					'http://localhost:8081/tiles/{z}/{x}/{y}.png',
					HTTP
				)
			).toBeNull();
			expect(
				tileUrlProblem(
					'http://tiles.example.com/{z}/{x}/{y}.png',
					HTTP
				)
			).toBeNull();
		} );

		it( 'sees through leading whitespace and odd casing', () => {
			expect(
				tileUrlProblem( '  http://t.example/{z}.png', HTTPS )
			).toBe( 'insecure' );
			expect( tileUrlProblem( 'HTTP://t.example/{z}.png', HTTPS ) ).toBe(
				'insecure'
			);
		} );

		it( 'does not fire on an https URL that merely mentions http', () => {
			expect(
				tileUrlProblem(
					'https://proxy.example/?upstream=http://a.test/{z}.png',
					HTTPS
				)
			).toBeNull();
		} );
	} );

	describe( 'ignored: the plugin discards the URL', () => {
		// Renderer::sanitize_tile_url() requires a scheme, so these are thrown
		// away and the map falls back to OpenStreetMap with nothing said.
		it( 'flags a protocol-relative URL', () => {
			expect(
				tileUrlProblem( '//tiles.example.com/{z}/{x}/{y}.png', HTTPS )
			).toBe( 'ignored' );
		} );

		it( 'flags a bare host', () => {
			expect(
				tileUrlProblem( 'tiles.example.com/{z}/{x}/{y}.png', HTTPS )
			).toBe( 'ignored' );
		} );

		// It is a plugin rule, not a browser one, so the page protocol is
		// irrelevant here - unlike the insecure case.
		it( 'flags them on an http page too', () => {
			expect(
				tileUrlProblem( '//tiles.example.com/{z}.png', HTTP )
			).toBe( 'ignored' );
			expect( tileUrlProblem( 'tiles.example.com/{z}.png', HTTP ) ).toBe(
				'ignored'
			);
		} );

		it( 'flags an unsupported scheme', () => {
			expect(
				tileUrlProblem( 'ftp://tiles.example.com/{z}.png', HTTPS )
			).toBe( 'ignored' );
		} );
	} );

	describe( 'blank means "use the default tiles"', () => {
		it( 'says nothing about an empty field', () => {
			expect( tileUrlProblem( '', HTTPS ) ).toBeNull();
			expect( tileUrlProblem( '   ', HTTPS ) ).toBeNull();
		} );
	} );
} );
