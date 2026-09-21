/**
 * Per-viewport map heights.
 *
 * Deliberately free of WordPress imports: the cascade is mirrored in PHP
 * (Renderer::normalize) and both sides are driven from one shared fixture, so
 * it has to be importable by a plain unit test.
 */

/** A height for each viewport band; 0 means "follow the next larger band". */
export type HeightBands = { base: number; tablet: number; mobile: number };

/** Bounds, mirroring Renderer::MIN_HEIGHT / MAX_HEIGHT / DEFAULT_HEIGHT. */
export const MIN_HEIGHT = 200;
export const MAX_HEIGHT = 1200;
export const DEFAULT_HEIGHT = 480;

/**
 * Normalize an optional per-viewport height.
 *
 * Mirrors Renderer::optional_height(): 0 means "follow the next larger band",
 * anything else is clamped into the supported range.
 *
 * @param value Raw value.
 */
export function optionalHeight( value: unknown ): number {
	// Mirrors PHP's is_numeric(), which Number() does not: booleans and hex
	// literals are numeric to JavaScript but not to PHP, and disagreeing here
	// is exactly the drift the shared fixture exists to prevent.
	if ( 'boolean' === typeof value ) {
		return 0;
	}
	if (
		'string' === typeof value &&
		! /^\s*[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?\s*$/.test( value )
	) {
		return 0;
	}

	const n = Number( value );

	if ( ! Number.isFinite( n ) || n <= 0 ) {
		return 0;
	}

	// Math.trunc, not Math.round: PHP's (int) cast in Renderer::optional_height
	// truncates, and a non-integer band would otherwise render one pixel taller
	// in the editor than on the front end.
	return Math.min( MAX_HEIGHT, Math.max( MIN_HEIGHT, Math.trunc( n ) ) );
}

/**
 * Resolve the height each viewport band actually renders at.
 *
 * Cascading, not disjoint: a band with nothing of its own follows the next
 * larger one, which is what people expect when they set only a phone height.
 *
 * @param map  Per-map values; 0 means nothing set.
 * @param site Site-wide values; 0 means nothing set.
 */
export function resolveHeights(
	map: Partial< HeightBands >,
	site: HeightBands
): HeightBands {
	const base = optionalHeight( map.base ) || site.base;

	let tablet = optionalHeight( map.tablet ) || site.tablet;
	tablet = tablet || base;

	let mobile = optionalHeight( map.mobile ) || site.mobile;
	mobile = mobile || tablet;

	return { base, tablet, mobile };
}

/**
 * The site-wide heights, printed by the server before the editor script.
 *
 * Not read from /wp/v2/settings: core gates that route on manage_options, so
 * an Editor or Author would silently get the shipped default and be shown a
 * height the front end will not use.
 */
export function siteHeights(): HeightBands {
	const injected = (
		window as unknown as { gpxrmDefaults?: Partial< HeightBands > }
	 ).gpxrmDefaults;

	const base = injected?.base;

	return {
		base: 'number' === typeof base && base > 0 ? base : DEFAULT_HEIGHT,
		tablet: optionalHeight( injected?.tablet ),
		mobile: optionalHeight( injected?.mobile ),
	};
}
