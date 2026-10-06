/**
 * The figures the stats bar can list, as the editor sees them.
 *
 * Free of WordPress imports so it can be unit tested directly.
 */

/** Every figure, in display order. Mirrors Renderer::STAT_FIELDS. */
export const STAT_KEYS = [
	'distance',
	'gain',
	'loss',
	'max',
	'min',
	'waypoints',
];

/**
 * The figures shown when nothing says otherwise: all but the min elevation,
 * which came later. Mirrors Renderer::DEFAULT_STAT_FIELDS.
 */
export const DEFAULT_STAT_KEYS = [
	'distance',
	'gain',
	'loss',
	'max',
	'waypoints',
];

/**
 * The figures a map that follows the site shows, printed by the server before
 * the editor script.
 *
 * Not read from /wp/v2/settings: core gates that route on manage_options, so
 * an Editor or Author would silently be shown the shipped default instead of
 * the real setting.
 */
export function siteStatFields(): string[] {
	const injected = (
		window as unknown as { gpxrmDefaults?: { statFields?: unknown } }
	 ).gpxrmDefaults?.statFields;
	if ( ! Array.isArray( injected ) ) {
		return DEFAULT_STAT_KEYS;
	}
	const keys = STAT_KEYS.filter( ( key ) => injected.includes( key ) );
	return keys.length ? keys : DEFAULT_STAT_KEYS;
}
