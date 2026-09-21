/**
 * Assertions about the built front-end bundle.
 *
 * These exist because of a failure that every other gate missed. MapLibre 6 is
 * ESM-only and locates its worker from `import.meta.url` using a computed
 * specifier, so the bundler cannot follow it and emitted no worker. The map
 * then drew its base tiles, markers and controls, logged nothing, and simply
 * never rendered the GPX track. Typecheck, lint, unit tests and Plugin Check
 * were all green.
 *
 * The invariant worth pinning is narrow: whatever worker the bundle asks for
 * must actually exist next to it.
 */

import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const BUILD = path.join(
	path.dirname(
		path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) )
	),
	'build'
);

const built = fs.existsSync( path.join( BUILD, 'view.js' ) );

describe.skipIf( ! built )( 'built view bundle', () => {
	const view = fs.readFileSync( path.join( BUILD, 'view.js' ), 'utf8' );
	const referenced = [
		...view.matchAll( /maplibre-gl-worker-[A-Za-z0-9_-]+\.js/g ),
	].map( ( m ) => m[ 0 ] );

	it( 'asks for a MapLibre worker', () => {
		// If this fails, the bundler stopped emitting the worker: the map will
		// render tiles but never the track.
		expect( referenced.length ).toBeGreaterThan( 0 );
	} );

	it( 'ships every worker it references', () => {
		for ( const name of new Set( referenced ) ) {
			const file = path.join( BUILD, name );
			expect( fs.existsSync( file ), `${ name } is missing` ).toBe(
				true
			);
			// A stub or empty file would 404-by-another-name at runtime.
			expect( fs.statSync( file ).size ).toBeGreaterThan( 10000 );
		}
	} );

	it( 'keeps the maplibre-gl-* prefix the i18n:pot exclude relies on', () => {
		for ( const name of new Set( referenced ) ) {
			expect( name.startsWith( 'maplibre-gl-' ) ).toBe( true );
		}
	} );
} );

describe.skipIf( ! built )( 'compiled stylesheet', () => {
	const css = fs.readFileSync(
		path.join( BUILD, 'style-index.css' ),
		'utf8'
	);
	const renderer = fs.readFileSync(
		path.join( path.dirname( BUILD ), 'includes', 'class-renderer.php' ),
		'utf8'
	);
	const phpMin = Number(
		/const MIN_HEIGHT\s*=\s*(\d+)/.exec( renderer )?.[ 1 ]
	);

	/**
	 * Every min-height the stylesheet declares for a given selector.
	 *
	 * @param selector Class selector, without the leading dot.
	 */
	function minHeights( selector: string ): number[] {
		const rules = [
			...css.matchAll(
				new RegExp( `\\.${ selector }\\{([^}]*)\\}`, 'g' )
			),
		];
		return rules
			.map( ( m ) => /min-height:(\d+)px/.exec( m[ 1 ] )?.[ 1 ] )
			.filter( ( v ): v is string => undefined !== v )
			.map( Number );
	}

	it( 'reads MIN_HEIGHT from the renderer', () => {
		expect( phpMin ).toBeGreaterThan( 0 );
	} );

	// A CSS floor above MIN_HEIGHT silently overrides any smaller height the
	// renderer resolved - min-height beats height - so the shortest maps the
	// UI offers are simply unreachable, with every PHP test still passing.
	it( 'never floors the map above the height the renderer can resolve', () => {
		const found = minHeights( 'gpxrm-map' );
		expect( found.length ).toBeGreaterThan( 0 );
		for ( const value of found ) {
			expect( value ).toBeLessThanOrEqual( phpMin );
		}
	} );

	// .gpxrm-error is rendered inside .gpxrm-map, so it cannot be taller than
	// the shortest map without overflowing its own container.
	it( 'keeps the error box within the shortest possible map', () => {
		for ( const value of minHeights( 'gpxrm-error' ) ) {
			expect( value ).toBeLessThanOrEqual( phpMin );
		}
	} );
} );

it( 'has a build to inspect', () => {
	// Guards against the suite silently skipping everything above.
	expect( built, 'run `pnpm run build` before the JS tests' ).toBe( true );
} );
