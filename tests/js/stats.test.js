/**
 * routeStats / nearestIndex: the numbers shown in the front-end stats bar.
 */

import { describe, expect, it } from 'vitest';

import { routeStats, nearestIndex } from '../../src/view/stats';

const SEGMENTED_COORDS = [
	[ -6.0, 43.0, 100 ],
	[ -6.0, 43.001, 101 ],
	// ~130 km gap to the second segment.
	[ -7.0, 44.0, 102 ],
	[ -7.0, 44.001, 103 ],
];

describe( 'routeStats', () => {
	it( 'skips segment gaps in the distance sum', () => {
		const split = routeStats( SEGMENTED_COORDS, new Set( [ 0, 2 ] ) );
		const joined = routeStats( SEGMENTED_COORDS, new Set( [ 0 ] ) );

		expect( split.distance ).toBeGreaterThan( 0.2 );
		expect( split.distance ).toBeLessThan( 0.25 );
		expect( joined.distance ).toBeGreaterThan( 100 );
	} );

	it( 'tracks max elevation across all segments', () => {
		expect(
			routeStats( SEGMENTED_COORDS, new Set( [ 0, 2 ] ) ).maxEle
		).toBe( 103 );
	} );

	it( 'returns zeros for an empty track', () => {
		expect( routeStats( [], new Set() ) ).toEqual( {
			distance: 0,
			gain: 0,
			loss: 0,
			maxEle: 0,
		} );
	} );
} );

describe( 'nearestIndex', () => {
	it( 'finds the closest coordinate to a lng/lat', () => {
		expect( nearestIndex( SEGMENTED_COORDS, -6.0, 43.0004 ) ).toBe( 0 );
		expect( nearestIndex( SEGMENTED_COORDS, -6.0, 43.0006 ) ).toBe( 1 );
		expect( nearestIndex( SEGMENTED_COORDS, -7.1, 44.1 ) ).toBe( 3 );
	} );
} );
