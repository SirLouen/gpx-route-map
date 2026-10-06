/**
 * routeStats / nearestIndex: the numbers shown in the front-end stats bar.
 */

import { describe, expect, it } from 'vitest';

import { routeStats, nearestIndex } from '../../src/view/stats';
import type { Coord } from '../../src/view/types';

const SEGMENTED_COORDS: Coord[] = [
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

	it( 'tracks min elevation across all segments', () => {
		const coords: Coord[] = [
			[ -6.0, 43.0, 100 ],
			[ -6.0, 43.001, 101 ],
			[ -7.0, 44.0, 95 ],
			[ -7.0, 44.001, 103 ],
		];
		expect( routeStats( coords, new Set( [ 0, 2 ] ) ).minEle ).toBe( 95 );
	} );

	it( 'finds a min below sea level', () => {
		const coords: Coord[] = [
			[ 35.5, 31.5, -395 ],
			[ 35.5, 31.6, -410 ],
		];
		expect( routeStats( coords, new Set( [ 0 ] ) ).minEle ).toBe( -410 );
	} );

	// A point without <ele> reads 0; as the lowest point it would be a lie.
	it( 'leaves points without an elevation out of the min', () => {
		const coords: Coord[] = [
			[ -6.0, 43.0, 0 ],
			[ -6.0, 43.001, 120 ],
			[ -6.0, 43.002, 100 ],
		];
		const stats = routeStats( coords, new Set( [ 0 ] ), [
			false,
			true,
			true,
		] );
		expect( stats.minEle ).toBe( 100 );
		expect( stats.maxEle ).toBe( 120 );
	} );

	it( 'gives 0 for the min of a track with no elevation, as for the max', () => {
		const coords: Coord[] = [
			[ -6.0, 43.0, 0 ],
			[ -6.0, 43.001, 0 ],
		];
		const stats = routeStats( coords, new Set( [ 0 ] ), [ false, false ] );
		expect( stats.minEle ).toBe( 0 );
		expect( stats.maxEle ).toBe( 0 );
	} );

	it( 'returns zeros for an empty track', () => {
		expect( routeStats( [], new Set() ) ).toEqual( {
			distance: 0,
			gain: 0,
			loss: 0,
			maxEle: 0,
			minEle: 0,
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
