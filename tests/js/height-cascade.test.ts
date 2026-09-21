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

const EDGE_FIXTURE = path.join(
	path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) ),
	'fixtures',
	'optional-height.json'
);

const edgeCases: Array< { in: unknown; want: number } > = JSON.parse(
	fs.readFileSync( EDGE_FIXTURE, 'utf8' )
).cases;

describe( 'optionalHeight', () => {
	it( 'reads the shared fixture', () => {
		expect( edgeCases.length ).toBeGreaterThan( 0 );
	} );

	// PHP's is_numeric() and JavaScript's Number() disagree about booleans and
	// hex literals, and casting before testing positivity made anything under
	// 1px read as "nothing set" on one side only. tests/HeightCascadeTest.php
	// drives the very same table.
	for ( const c of edgeCases ) {
		it( `${ JSON.stringify( c.in ) } resolves to ${ c.want }`, () => {
			expect( optionalHeight( c.in ) ).toBe( c.want );
		} );
	}

	it( 'treats undefined as "nothing set"', () => {
		// Not in the shared fixture: JSON has no undefined.
		expect( optionalHeight( undefined ) ).toBe( 0 );
		expect( optionalHeight( NaN ) ).toBe( 0 );
	} );
} );
