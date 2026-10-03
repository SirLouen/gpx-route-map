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

	/**
	 * Where a selector's height declarations sit, and whether each is inside a
	 * media query. Walks the stylesheet rather than matching it, because a
	 * regex cannot tell a rule inside an @media block from one beside it once
	 * the CSS is minified.
	 *
	 * @param selector Class selector, without the leading dot.
	 */
	function heightRules( selector: string ) {
		const out: Array< { at: number; inMedia: boolean } > = [];
		const needle = `.${ selector }{`;
		let depth = 0;
		let mediaDepth = -1;

		for ( let i = 0; i < css.length; i++ ) {
			if ( css.startsWith( '@media', i ) && mediaDepth < 0 ) {
				mediaDepth = depth;
			}
			if ( css.startsWith( needle, i ) ) {
				const body = css.slice( i, css.indexOf( '}', i ) );
				if ( /height:/.test( body ) ) {
					out.push( { at: i, inMedia: mediaDepth >= 0 } );
				}
			}
			if ( '{' === css[ i ] ) {
				depth++;
			}
			if ( '}' === css[ i ] ) {
				depth--;
				if ( mediaDepth >= 0 && depth <= mediaDepth ) {
					mediaDepth = -1;
				}
			}
		}

		return out;
	}

	// Source order, not specificity, decides between two rules for the same
	// selector. The responsive override sat above the base rule at first, so
	// the base height won and the rule was simply dead - while the CSS
	// compiled, every test passed, and the map itself still resized correctly.
	it( 'puts each responsive override after the rule it must beat', () => {
		for ( const selector of [ 'gpxrm-map', 'gpxrm-elevation-canvas' ] ) {
			const rules = heightRules( selector );
			const base = rules.filter( ( r ) => ! r.inMedia );
			const responsive = rules.filter( ( r ) => r.inMedia );

			expect( base.length, `no base .${ selector } height` ).toBe( 1 );
			expect(
				responsive.length,
				`no responsive .${ selector } height`
			).toBeGreaterThan( 0 );

			for ( const r of responsive ) {
				expect(
					r.at,
					`the responsive .${ selector } rule must come after its base rule`
				).toBeGreaterThan( base[ 0 ].at );
			}
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

describe.skipIf( ! built )( 'map chrome under theme styles', () => {
	const css = readIfBuilt( path.join( BUILD, 'style-index.css' ) );
	const rtl = readIfBuilt( path.join( BUILD, 'style-index-rtl.css' ) );

	const CLOSE = '.gpxrm .gpxrm-map .maplibregl-popup-close-button';
	const CONTENT = '.gpxrm .gpxrm-map .maplibregl-popup-content';
	const CONTROL = '.gpxrm .gpxrm-map .maplibregl-ctrl-group button';
	const DOWNLOAD = '.gpxrm .gpxrm-map .gpxrm-download-btn';

	/**
	 * The declarations of every rule whose selector is exactly `selector`.
	 *
	 * @param source   Compiled stylesheet.
	 * @param selector Full selector, as the build emits it.
	 */
	function declarations( source: string, selector: string ): string {
		const escaped = selector.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		return [
			...source.matchAll(
				new RegExp(
					`(?:^|[}{;])\\s*${ escaped }\\s*\\{([^}]*)\\}`,
					'g'
				)
			),
		]
			.map( ( m ) => m[ 1 ] )
			.join( ';' );
	}

	/**
	 * Property names a rule sets.
	 *
	 * @param body Declaration block, without braces.
	 */
	function properties( body: string ): string[] {
		return body
			.split( ';' )
			.map( ( d ) => d.split( ':' )[ 0 ].trim() )
			.filter( Boolean );
	}

	/**
	 * Every `@media` block whose condition matches `query`.
	 *
	 * Brace-matched rather than a lazy `.*?}}` regex, which ends at the first
	 * `}}` and so would cut a block short the moment it held a nested at-rule.
	 * Like heightRules() above, it assumes no braces inside strings, which this
	 * stylesheet does not have.
	 *
	 * @param source Compiled stylesheet.
	 * @param query  Pattern the media condition must match.
	 */
	function mediaBlocks( source: string, query: RegExp ) {
		const out: Array< { start: number; end: number; body: string } > = [];
		const head = /@media\s*([^{]*)\{/g;
		let match: RegExpExecArray | null;

		while ( ( match = head.exec( source ) ) ) {
			let depth = 1;
			let i = head.lastIndex;
			for ( ; i < source.length && depth > 0; i++ ) {
				if ( '{' === source[ i ] ) {
					depth++;
				} else if ( '}' === source[ i ] ) {
					depth--;
				}
			}
			if ( query.test( match[ 1 ] ) ) {
				out.push( {
					start: match.index,
					end: i,
					body: source.slice( head.lastIndex, i - 1 ),
				} );
			}
			head.lastIndex = i;
		}

		return out;
	}

	/**
	 * The stylesheet with the given blocks cut out.
	 *
	 * @param source Compiled stylesheet.
	 * @param blocks Blocks from mediaBlocks().
	 */
	function without(
		source: string,
		blocks: Array< { start: number; end: number } >
	): string {
		return blocks
			.slice()
			.reverse()
			.reduce(
				( rest, b ) => rest.slice( 0, b.start ) + rest.slice( b.end ),
				source
			);
	}

	// Under Vantage, a bare `button` rule filled every gap MapLibre leaves to
	// the browser and grew the close button to 49x38px, on top of the
	// waypoint's name. Each property here is one a popular theme sets on bare
	// buttons; losing any of them from the reset reopens that door.
	it( 'pins everything a theme button rule can inflate', () => {
		const set = properties( declarations( css, CLOSE ) );
		expect( set, `${ CLOSE } is missing from the build` ).not.toHaveLength(
			0
		);

		for ( const property of [
			'display',
			'width',
			'height',
			'min-width',
			'min-height',
			'margin',
			'padding',
			'border',
			'background-image',
			'background-color',
			'box-shadow',
			'color',
			'font-size',
			'line-height',
			'text-shadow',
			'appearance',
		] ) {
			expect( set, `${ CLOSE } no longer sets ${ property }` ).toContain(
				property
			);
		}
	} );

	// Twenty Twenty-One (0,4,1), Twenty Seventeen's dark scheme (0,3,1) and
	// Hestia (0,6,2) set these three on every button, above any reasonable
	// selector - TT1 alone hides MapLibre's dark icons behind its fill.
	it( 'holds the contested properties against theme specificity', () => {
		const contested: Array< [ string, string[] ] > = [
			[ CLOSE, [ 'background-color', 'color', 'box-shadow' ] ],
			[ CONTROL, [ 'background-color', 'box-shadow' ] ],
			[ DOWNLOAD, [ 'background-color', 'color', 'box-shadow' ] ],
		];

		for ( const [ selector, props ] of contested ) {
			const body = declarations( css, selector );
			for ( const property of props ) {
				// Anchored on the declaration boundary: unanchored, `color`
				// also matched the tail of `background-color` and could never
				// fail on its own.
				expect(
					body,
					`${ selector } ${ property } must be !important`
				).toMatch(
					new RegExp( `(?:^|;)\\s*${ property }\\s*:[^;]*!important` )
				);
			}
		}
	} );

	// An !important reset also beats MapLibre's own hover and focus rules,
	// so they are restated at the same strength. Dropping these would leave
	// the controls with no hover feedback and no keyboard focus ring.
	it( "restates MapLibre's control states it outranks", () => {
		expect( declarations( css, `${ CONTROL }:focus-visible` ) ).toMatch(
			/box-shadow:0 0 2px 2px #0096ff\s*!important/
		);
		expect(
			declarations( css, `${ CONTROL }:not(:disabled):active` )
		).toMatch(
			/background-color:rgba\(0,\s*0,\s*0,\s*\.05\)\s*!important/
		);
	} );

	// MapLibre gates its hover tint to hover-capable devices so a tapped
	// button does not stay tinted on a touch screen. Restating it outside
	// that gate quietly brought the sticky tint back.
	it( 'keeps the hover tint behind the (hover: hover) gate', () => {
		const gated = mediaBlocks( css, /hover:\s*hover/ );
		const tint =
			/background-color:rgba\(0,\s*0,\s*0,\s*\.05\)\s*!important/;

		expect(
			declarations(
				gated.map( ( b ) => b.body ).join( '' ),
				`${ CONTROL }:not(:disabled):hover`
			)
		).toMatch( tint );
		// And nowhere outside it.
		expect(
			declarations(
				without( css, gated ),
				`${ CONTROL }:not(:disabled):hover`
			)
		).toBe( '' );
	} );

	// Theme button rules up to (0,2,x) set these on every button; MapLibre
	// sets none of them on its controls, so the gradient and rounding showed
	// through behind every icon until this rule existed.
	it( 'resets the control buttons below MapLibre focus rules', () => {
		const set = properties(
			declarations(
				css,
				'.gpxrm .gpxrm-map .maplibregl-ctrl-group :where(button)'
			)
		);
		for ( const property of [
			'background-image',
			'border-radius',
			'margin',
			'min-width',
			'min-height',
			'text-shadow',
		] ) {
			expect(
				set,
				`control reset no longer sets ${ property }`
			).toContain( property );
		}
	} );

	// The reset also strips whatever border or shadow a theme draws in place
	// of the outline it switched off, so the button brings its own ring - a
	// shadow, since Hestia forces outline-width to 0, plus a transparent
	// outline for forced-colors mode.
	it( 'gives the close button and download link their own focus ring', () => {
		for ( const selector of [ CLOSE, DOWNLOAD ] ) {
			const ring = declarations( css, `${ selector }:focus-visible` );
			expect( ring, selector ).toMatch(
				/box-shadow:\s*inset[^;]*!important/
			);
			// Sass compresses `transparent` to rgba(0,0,0,0); either is the
			// same. !important so Hestia's `outline: 0 !important` cannot
			// erase it in forced-colors mode, where the shadow is dropped.
			expect( ring, selector ).toMatch(
				/outline:\s*2px solid (?:transparent|rgba\(0,\s*0,\s*0,\s*0\))\s*!important/
			);
		}
	} );

	// Raising the base rule's specificity silently outranked the
	// reduced-motion override written against the old, bare selector.
	it( 'keeps the reduced-motion override as strong as its base rule', () => {
		// Every reduced-motion block: the build emits one per source rule.
		const blocks = mediaBlocks( css, /prefers-reduced-motion:\s*reduce/ );
		const override = blocks.find( ( b ) =>
			/transition:\s*none/.test( declarations( b.body, DOWNLOAD ) )
		);
		expect(
			override,
			'no reduced-motion override for the download link'
		).toBeDefined();

		// Same specificity, so source order decides: the override has to
		// come after the rule that sets the transition.
		const base = css.search(
			new RegExp(
				`${ DOWNLOAD.replace(
					/[.]/g,
					'\\.'
				) }\\{[^}]*transition:background`
			)
		);
		expect( base ).toBeGreaterThan( -1 );
		expect( override?.start ).toBeGreaterThan( base );
	} );

	// MapLibre pins the button at `right: 0` whatever the writing direction,
	// and its own stylesheet is never mirrored. If rtlcss flips the room we
	// reserve for it, the overlap comes back on every RTL site.
	it( 'keeps the popup rules physical in the RTL build', () => {
		expect( declarations( rtl, CONTENT ) ).toMatch(
			/padding-right:\s*28px/
		);
		expect( declarations( rtl, CLOSE ) ).toMatch(
			/border-radius:\s*0 3px 0 0/
		);
	} );

	it( 'leaves no rtlcss directive behind in either build', () => {
		expect( css ).not.toMatch( /rtl:/ );
		expect( rtl ).not.toMatch( /rtl:/ );
	} );
} );

it( 'has a build to inspect', () => {
	// Guards against the suite silently skipping everything above.
	expect( built, 'run `pnpm run build` before the JS tests' ).toBe( true );
} );
