/**
 * Listener binding segment-gap distances and pointer→index mapping.
 */

import { describe, expect, it, vi } from 'vitest';

import { ElevationProfile } from '../../src/view/elevation';
import type { Coord } from '../../src/view/types';

/** `state` once a build has succeeded; null only means "nothing drawn". */
type BuiltState = NonNullable< ElevationProfile[ 'state' ] >;

/**
 * Narrow `state` after a build the test expects to have produced one.
 *
 * @param profile Profile that has just been built.
 */
function builtState( profile: ElevationProfile ): BuiltState {
	if ( ! profile.state ) {
		throw new Error( 'expected build() to produce state' );
	}
	return profile.state;
}

/**
 * Canvas 2D context stub.
 */
const ctxStub = new Proxy(
	{},
	{
		get: ( target, prop ) => {
			if ( prop === 'createLinearGradient' ) {
				return () => ( { addColorStop: () => {} } );
			}
			if ( prop === 'measureText' ) {
				return () => ( { width: 50 } );
			}
			return () => {};
		},
		set: () => true,
	}
);

/**
 * Give a real canvas a measurable size and a drawable context.
 *
 * jsdom lays every element out at 0x0 and has no 2D context, so both are
 * stubbed - but on a genuine HTMLCanvasElement, so the profile sees real
 * `style` and event-listener behaviour.
 *
 * @param width  Reported client width.
 * @param height Reported client height.
 */
function makeCanvas( width = 400, height = 160 ) {
	let listeners = 0;
	const canvas = document.createElement( 'canvas' );

	canvas.getBoundingClientRect = () =>
		( {
			width,
			height,
			left: 0,
			top: 0,
			right: width,
			bottom: height,
			x: 0,
			y: 0,
		} ) as DOMRect;
	canvas.getContext = ( () =>
		ctxStub ) as unknown as HTMLCanvasElement[ 'getContext' ];

	const addEventListener = canvas.addEventListener.bind( canvas );
	canvas.addEventListener = (
		...args: Parameters< typeof addEventListener >
	) => {
		listeners++;
		addEventListener( ...args );
	};

	return { canvas, listenerCount: () => listeners };
}

const SEGMENTED_COORDS: Coord[] = [
	[ -6.0, 43.0, 100 ],
	[ -6.0, 43.001, 101 ],
	[ -7.0, 44.0, 102 ],
	[ -7.0, 44.001, 103 ],
];

describe( 'ElevationProfile', () => {
	it( 'attaches listeners exactly once across rebuilds', () => {
		const { canvas, listenerCount } = makeCanvas();
		const windowSpy = vi.spyOn( window, 'addEventListener' );
		const profile = new ElevationProfile( canvas, SEGMENTED_COORDS );

		profile.build(); // initial
		profile.build(); // resize 1
		profile.build(); // resize 2

		expect( listenerCount() ).toBe( 6 );
		expect(
			windowSpy.mock.calls.filter( ( [ type ] ) => type === 'mouseup' )
		).toHaveLength( 1 );
		windowSpy.mockRestore();
	} );

	it( 'does not count segment gaps in the distance axis', () => {
		const { canvas } = makeCanvas();
		const split = new ElevationProfile(
			canvas,
			SEGMENTED_COORDS,
			{},
			new Set( [ 0, 2 ] )
		);
		split.build();
		const splitTotal = builtState( split ).dists.at( -1 );

		const joined = new ElevationProfile( canvas, SEGMENTED_COORDS );
		joined.build();
		const joinedTotal = builtState( joined ).dists.at( -1 );

		expect( splitTotal ).toBeGreaterThan( 0.2 );
		expect( splitTotal ).toBeLessThan( 0.25 );
		expect( joinedTotal ).toBeGreaterThan( 100 );
	} );

	it( 'maps client X inside the plot to an index, outside to null', () => {
		// Evenly spaced points so nearest-index expectations are unambiguous.
		const uniformCoords: Coord[] = [
			[ -6.0, 43.0, 100 ],
			[ -6.0, 43.001, 101 ],
			[ -6.0, 43.002, 102 ],
			[ -6.0, 43.003, 103 ],
		];
		const { canvas } = makeCanvas();
		const profile = new ElevationProfile( canvas, uniformCoords );
		profile.build();

		expect( profile.indexAtClientX( 41 ) ).toBeNull(); // left padding
		expect( profile.indexAtClientX( 389 ) ).toBeNull(); // right padding
		expect( profile.indexAtClientX( 42 ) ).toBe( 0 ); // left edge = 0 km
		expect( profile.indexAtClientX( 388 ) ).toBe( 3 ); // right edge = end
	} );

	it( 'skips building while the canvas has no width', () => {
		const { canvas, listenerCount } = makeCanvas( 0, 0 );
		const profile = new ElevationProfile( canvas, SEGMENTED_COORDS );
		profile.build();

		expect( profile.state ).toBeNull();
		expect( listenerCount() ).toBe( 0 );
	} );
} );
