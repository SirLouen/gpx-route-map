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

/**
 * Read a file only when there is a build to read.
 *
 * `describe.skipIf` still executes its callback body at collection time, so a
 * bare `readFileSync` in there throws ENOENT and takes the whole module down
 * before the "has a build to inspect" guard can report anything useful.
 *
 * @param file Path to read.
 */
function readIfBuilt( file: string ): string {
	return built ? fs.readFileSync( file, 'utf8' ) : '';
}

describe.skipIf( ! built )( 'built view bundle', () => {
	const view = readIfBuilt( path.join( BUILD, 'view.js' ) );
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
	const css = readIfBuilt( path.join( BUILD, 'style-index.css' ) );
	const renderer = readIfBuilt(
		path.join( path.dirname( BUILD ), 'includes', 'class-renderer.php' )
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
				// Whitespace-tolerant: the build emits compressed CSS today,
				// but the assertions should not depend on that.
				new RegExp( `\\.${ selector }\\s*\\{([^}]*)\\}`, 'g' )
			),
		];
		return rules
			.map( ( m ) => /min-height:\s*(\d+)px/.exec( m[ 1 ] )?.[ 1 ] )
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

	// min-height alone is not the whole invariant: under the browser default
	// box model the padding is added to it, so the floor must be border-box
	// or the error box is 48px taller than the shortest map.
	it( 'sizes the error box so its padding stays inside the floor', () => {
		const rule = /\.gpxrm-error\s*\{([^}]*)\}/.exec( css )?.[ 1 ] ?? '';
		expect( rule ).toMatch( /min-height:\s*\d+px/ );
		if ( /padding:/.test( rule ) ) {
			expect( rule ).toMatch( /box-sizing:\s*border-box/ );
		}
	} );

	// .gpxrm-error is rendered inside .gpxrm-map, so it cannot be taller than
	// the shortest map without overflowing its own container.
	it( 'keeps the error box within the shortest possible map', () => {
		const found = minHeights( 'gpxrm-error' );
		// Without this the loop asserts nothing if the rule is renamed away.
		expect( found.length ).toBeGreaterThan( 0 );
		for ( const value of found ) {
			expect( value ).toBeLessThanOrEqual( phpMin );
		}
	} );
} );

it( 'has a build to inspect', () => {
	// Guards against the suite silently skipping everything above.
	expect( built, 'run `pnpm run build` before the JS tests' ).toBe( true );
} );
