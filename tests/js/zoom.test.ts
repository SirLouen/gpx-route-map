/**
 * The site-wide maximum zoom, as the editor reads it.
 *
 * The counterpart of Renderer::usable_zoom() in tests/MaxZoomTest.php. Both
 * sides have to treat a non-positive value as "nothing set", or the editor
 * shows one zoom while the published map opens at another.
 */

import { beforeEach, describe, expect, it } from 'vitest';

import {
	DEFAULT_ZOOM,
	MAX_ZOOM,
	MIN_ZOOM,
	siteDefaultMaxZoom,
} from '../../src/zoom';

function inject( maxZoom: unknown ): void {
	( window as unknown as { gpxrmDefaults?: unknown } ).gpxrmDefaults =
		undefined === maxZoom ? undefined : { maxZoom };
}

describe( 'siteDefaultMaxZoom', () => {
	beforeEach( () => inject( undefined ) );

	it( 'uses the shipped default when the server printed nothing', () => {
		expect( siteDefaultMaxZoom() ).toBe( DEFAULT_ZOOM );
	} );

	it( 'uses a zoom the server actually set', () => {
		inject( 19 );

		expect( siteDefaultMaxZoom() ).toBe( 19 );
	} );

	it.each( [
		[ 'zero', 0 ],
		[ 'negative', -3 ],
	] )(
		'falls back to the default rather than clamping a %s zoom',
		( _label, value ) => {
			// Clamping would give MIN_ZOOM, so a stale payload would open the
			// editor preview on a view of the whole world.
			inject( value );

			expect( siteDefaultMaxZoom() ).toBe( DEFAULT_ZOOM );
		}
	);

	it.each( [
		[ 'a string', '19' ],
		[ 'null', null ],
		[ 'NaN', Number.NaN ],
		[ 'Infinity', Number.POSITIVE_INFINITY ],
	] )( 'falls back when the payload carries %s', ( _label, value ) => {
		inject( value );

		expect( siteDefaultMaxZoom() ).toBe( DEFAULT_ZOOM );
	} );

	it( 'clamps a zoom the server set too high', () => {
		inject( 99 );

		expect( siteDefaultMaxZoom() ).toBe( MAX_ZOOM );
	} );

	it( 'clamps a positive fractional zoom, which is odd but real', () => {
		inject( 0.5 );

		expect( siteDefaultMaxZoom() ).toBe( MIN_ZOOM );
	} );
} );
