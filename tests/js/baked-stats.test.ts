/**
 * The stats the editor stores on a block, for the server's stats bar.
 */

import { describe, expect, it } from 'vitest';

import {
	bakeStats,
	sameStats,
	shouldStore,
	statsToStore,
} from '../../src/baked-stats';
import type { GpxStats } from '../../src/baked-stats';
import { parseGPX } from '../../src/view/map-core';

const STORED: GpxStats = {
	distance: 1.5,
	gain: 20,
	loss: 10,
	max: 120,
	min: 100,
	waypoints: 1,
};

describe( 'bakeStats', () => {
	it( 'stores the min elevation, leaving out points without one', () => {
		const parsed = parseGPX(
			'<gpx><trk><trkseg>' +
				'<trkpt lat="43.0" lon="-6.0"></trkpt>' +
				'<trkpt lat="43.01" lon="-6.0"><ele>120</ele></trkpt>' +
				'<trkpt lat="43.02" lon="-6.0"><ele>100</ele></trkpt>' +
				'</trkseg></trk><wpt lat="43.01" lon="-6.0"/></gpx>'
		);

		expect( bakeStats( parsed ) ).toMatchObject( {
			max: 120,
			min: 100,
			waypoints: 1,
		} );
	} );
} );

describe( 'sameStats', () => {
	it( 'is false when nothing is stored', () => {
		expect( sameStats( undefined, STORED ) ).toBe( false );
	} );

	// Opening a post must not change it: stats stored before 2.0.0 have no
	// min, and that alone is no reason to store them again.
	it( 'takes stats stored without a min as unchanged', () => {
		const old: GpxStats = { ...STORED };
		delete old.min;

		expect( sameStats( old, STORED ) ).toBe( true );
		expect( sameStats( old, { ...STORED, max: 121 } ) ).toBe( false );
	} );

	it( 'notices any changed figure, the min included', () => {
		expect( sameStats( STORED, { ...STORED } ) ).toBe( true );
		expect( sameStats( STORED, { ...STORED, min: 99 } ) ).toBe( false );
		expect( sameStats( STORED, { ...STORED, distance: 1.6 } ) ).toBe(
			false
		);
		expect( sameStats( STORED, { ...STORED, waypoints: 2 } ) ).toBe(
			false
		);
	} );
} );

// Opening a post never stores its stats again, but choosing the min is the
// author's own edit: then the stats just worked out from the file go with it.
describe( 'statsToStore', () => {
	const old: GpxStats = { ...STORED };
	delete old.min;
	const fresh = { url: 'a.gpx', stats: STORED };

	it( 'stores the fresh stats when the min is chosen and missing', () => {
		expect( statsToStore( old, fresh, 'a.gpx', [ 'max', 'min' ] ) ).toBe(
			STORED
		);
		expect( statsToStore( undefined, fresh, 'a.gpx', [ 'min' ] ) ).toBe(
			STORED
		);
	} );

	it( 'stores nothing otherwise', () => {
		expect(
			statsToStore( old, fresh, 'a.gpx', [ 'max' ] )
		).toBeUndefined();
		expect(
			statsToStore( STORED, fresh, 'a.gpx', [ 'min' ] )
		).toBeUndefined();
		// The file has not been read yet.
		expect(
			statsToStore( old, undefined, 'a.gpx', [ 'min' ] )
		).toBeUndefined();
	} );

	// Read from a file the block no longer shows: one that failed to load
	// since, or none at all.
	it( 'never stores the stats of another file', () => {
		expect(
			statsToStore( undefined, fresh, 'b.gpx', [ 'min' ] )
		).toBeUndefined();
		expect(
			statsToStore( undefined, fresh, '', [ 'min' ] )
		).toBeUndefined();
	} );
} );

describe( 'shouldStore', () => {
	const old: GpxStats = { ...STORED };
	delete old.min;

	it( 'stores changed stats', () => {
		expect( shouldStore( undefined, STORED, false ) ).toBe( true );
		expect( shouldStore( old, { ...STORED, max: 121 }, false ) ).toBe(
			true
		);
	} );

	// Opening a post must not change it.
	it( 'leaves stats without a min alone, unless the author chose the min', () => {
		expect( shouldStore( old, STORED, false ) ).toBe( false );
		// Chosen before the file had been read.
		expect( shouldStore( old, STORED, true ) ).toBe( true );
		expect( shouldStore( STORED, STORED, true ) ).toBe( false );
	} );
} );
