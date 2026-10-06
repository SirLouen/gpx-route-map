/**
 * Unit tests for the display-unit helpers.
 */

import { describe, expect, it } from 'vitest';

import {
	METRIC,
	readUnits,
	formatDistance,
	formatElevation,
	roundElevation,
} from '../../src/view/units';

const IMPERIAL = {
	distFactor: 0.621371,
	distLabel: 'mi',
	eleFactor: 3.28084,
	eleLabel: 'ft',
};

/**
 * Build a map element carrying a units payload.
 *
 * A real element rather than a stub, so `dataset` behaves as it does in a
 * browser: keys are strings, and an absent attribute is `undefined`.
 *
 * @param value Raw attribute value, or undefined to omit it.
 */
function elementWith( value?: string ): HTMLElement {
	const el = document.createElement( 'div' );
	if ( undefined !== value ) {
		el.dataset.gpxrmUnits = value;
	}
	return el;
}

describe( 'formatDistance', () => {
	it( 'keeps kilometres unchanged in metric', () => {
		expect( formatDistance( 2.6636, METRIC ) ).toBe( '2.66 km' );
	} );

	it( 'converts kilometres to miles in imperial', () => {
		// 10 km = 6.21371 mi
		expect( formatDistance( 10, IMPERIAL ) ).toBe( '6.21 mi' );
	} );
} );

describe( 'formatElevation', () => {
	it( 'keeps metres unchanged in metric', () => {
		expect( formatElevation( 666.5, METRIC ) ).toBe( '667 m' );
	} );

	it( 'converts metres to feet in imperial', () => {
		// 1000 m = 3280.84 ft -> 3,281 ft
		expect( formatElevation( 1000, IMPERIAL ) ).toBe( '3,281 ft' );
	} );

	// A coastal track's lowest point can round to minus zero.
	it( 'never shows -0', () => {
		expect( formatElevation( -0.4, METRIC ) ).toBe( '0 m' );
	} );

	// As PHP's round() does, so the figure does not change when the map
	// replaces the one the server printed. The profile rounds the same way.
	it( 'rounds halves away from zero', () => {
		expect( roundElevation( -2.5, METRIC ) ).toBe( -3 );
		expect( Object.is( roundElevation( -0.4, METRIC ), 0 ) ).toBe( true );
		expect( formatElevation( -410.5, METRIC ) ).toBe( '-411 m' );
		expect( formatElevation( -0.5, METRIC ) ).toBe( '-1 m' );
		expect( formatElevation( 2.5, METRIC ) ).toBe( '3 m' );
	} );
} );

describe( 'readUnits', () => {
	it( 'reads the server-provided configuration', () => {
		const el = elementWith( JSON.stringify( IMPERIAL ) );
		expect( readUnits( el ) ).toEqual( IMPERIAL );
	} );

	it( 'falls back to metric when the attribute is missing', () => {
		expect( readUnits( elementWith( undefined ) ) ).toEqual( METRIC );
	} );

	it( 'falls back to metric on malformed JSON', () => {
		expect( readUnits( elementWith( '{not json' ) ) ).toEqual( METRIC );
	} );

	it( 'rejects non-positive or missing factors', () => {
		const el = elementWith(
			JSON.stringify( { distFactor: 0, eleFactor: -2, eleLabel: 'ft' } )
		);
		const units = readUnits( el );
		expect( units.distFactor ).toBe( 1 );
		expect( units.eleFactor ).toBe( 1 );
		expect( units.eleLabel ).toBe( 'ft' );
	} );
} );
