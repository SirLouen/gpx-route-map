/**
 * The height cascade, driven from the shared fixture.
 *
 * The other half of tests/HeightCascadeTest.php. Both read the same JSON, so
 * if the PHP and TypeScript cascades ever disagree one of them goes red. The
 * failure they guard against is invisible otherwise: the editor would state
 * one height while the visitor gets another, and neither side is wrong on its
 * own.
 */

import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { resolveHeights, optionalHeight } from '../../src/heights';
import type { HeightBands } from '../../src/heights';

type Case = {
	name: string;
	map: HeightBands;
	site: HeightBands;
	want: HeightBands;
};

const FIXTURE = path.join(
	path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) ),
	'fixtures',
	'height-cascade.json'
);

const cases: Case[] = JSON.parse( fs.readFileSync( FIXTURE, 'utf8' ) ).cases;

describe( 'resolveHeights', () => {
	it( 'reads the shared fixture', () => {
		// Guards against the suite silently testing nothing if the file moves.
		expect( cases.length ).toBeGreaterThan( 0 );
	} );

	for ( const c of cases ) {
		it( c.name, () => {
			expect( resolveHeights( c.map, c.site ) ).toEqual( c.want );
		} );
	}
} );

describe( 'optionalHeight', () => {
	it( 'treats anything non-positive as "nothing set"', () => {
		for ( const value of [ 0, -1, '', null, undefined, 'tall', NaN ] ) {
			expect( optionalHeight( value ) ).toBe( 0 );
		}
	} );

	it( 'clamps into the supported range', () => {
		expect( optionalHeight( 99999 ) ).toBe( 1200 );
		expect( optionalHeight( 10 ) ).toBe( 200 );
		expect( optionalHeight( 640 ) ).toBe( 640 );
	} );
} );
