/**
 * Unit tests for computeElevationTotals (filtered elevation gain/loss).
 */

import { describe, expect, it } from 'vitest';

import { computeElevationTotals } from '../../src/view/elevation';

// A representative elevation series with noise below and above the threshold.
const FIXTURE_ELEVATIONS = [
	229.535, 227.786, 218.98, 212.782, 221, 256.25, 81, 219.25, 249.75, 66.41,
];

describe( 'computeElevationTotals', () => {
	it( 'computes filtered gain 212 / loss 375 for the fixture', () => {
		const { gain, loss } = computeElevationTotals( FIXTURE_ELEVATIONS );
		expect( Math.round( gain ) ).toBe( 212 );
		expect( Math.round( loss ) ).toBe( 375 );
	} );

	it( 'returns zero totals for fewer than two samples', () => {
		expect( computeElevationTotals( [] ) ).toEqual( { gain: 0, loss: 0 } );
		expect( computeElevationTotals( [ 100 ] ) ).toEqual( {
			gain: 0,
			loss: 0,
		} );
	} );

	it( 'ignores oscillation below the 9.05 m reversal threshold', () => {
		const { gain, loss } = computeElevationTotals( [
			100, 104, 99, 105, 100, 104,
		] );
		expect( gain ).toBe( 0 );
		expect( loss ).toBe( 0 );
	} );

	it( 'counts sustained climbs and descents', () => {
		const { gain, loss } = computeElevationTotals( [ 100, 150, 100 ] );
		expect( gain ).toBe( 50 );
		expect( loss ).toBe( 50 );
	} );
} );
