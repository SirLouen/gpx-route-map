/**
 * Listener binding segment-gap distances and pointer→index mapping.
 */

import { describe, expect, it, vi } from 'vitest';

import { ElevationProfile } from '../../src/view/elevation';

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
 * Build a fake canvas the profile can size, draw on and listen to.
 *
 * @return {Object} Stub canvas plus a listener counter.
 */
function makeCanvas() {
	let listeners = 0;
	return {
		listenerCount: () => listeners,
		canvas: {
			style: {},
			getBoundingClientRect: () => ( {
				width: 400,
				height: 160,
				left: 0,
			} ),
			getContext: () => ctxStub,
			addEventListener: () => listeners++,
		},
	};
}

const SEGMENTED_COORDS = [
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
		const splitTotal = split.state.dists.at( -1 );

		const joined = new ElevationProfile( canvas, SEGMENTED_COORDS );
		joined.build();
		const joinedTotal = joined.state.dists.at( -1 );

		expect( splitTotal ).toBeGreaterThan( 0.2 );
		expect( splitTotal ).toBeLessThan( 0.25 );
		expect( joinedTotal ).toBeGreaterThan( 100 );
	} );

	it( 'maps client X inside the plot to an index, outside to null', () => {
		// Evenly spaced points so nearest-index expectations are unambiguous.
		const uniformCoords = [
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
		let listeners = 0;
		const zeroCanvas = {
			style: {},
			getBoundingClientRect: () => ( { width: 0, height: 0, left: 0 } ),
			getContext: () => ctxStub,
			addEventListener: () => listeners++,
		};
		const profile = new ElevationProfile( zeroCanvas, SEGMENTED_COORDS );
		profile.build();

		expect( profile.state ).toBeNull();
		expect( listeners ).toBe( 0 );
	} );
} );
