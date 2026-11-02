/**
 * A real file with an elevation on only 46 of its 381 points, as MapSource
 * can write them. Reading the other 335 as 0 turned a walk between 112 and
 * 171 m into thousands of metres of climbing.
 */

import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { bakeStats } from '../../src/baked-stats';
import { parseGPX } from '../../src/view/map-core';

const parsed = parseGPX(
	fs.readFileSync(
		path.join(
			path.dirname( fileURLToPath( import.meta.url ) ),
			'..',
			'fixtures',
			'partial-elevation.gpx'
		),
		'utf8'
	)
);

describe( 'a GPX file with elevations on only some points', () => {
	it( 'keeps every point between the lowest and highest reading', () => {
		const elevations = parsed.coords.map( ( c ) => c[ 2 ] );

		expect( elevations ).toHaveLength( 381 );
		expect( Math.min( ...elevations ) ).toBe( 111.65 );
		expect( Math.max( ...elevations ) ).toBe( 170.77 );
	} );

	it( 'counts only the climbing its readings hold', () => {
		const stats = bakeStats( parsed );

		expect( stats.distance ).toBeCloseTo( 7.68, 2 );
		expect( stats.gain ).toBeCloseTo( 111.03, 2 );
		expect( stats.loss ).toBeCloseTo( 117.28, 2 );
		expect( stats.max ).toBe( 170.77 );
		expect( stats.min ).toBe( 111.65 );
	} );
} );
