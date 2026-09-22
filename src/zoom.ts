/**
 * The site-wide maximum zoom, as the editor sees it.
 *
 * Free of WordPress imports so it can be unit tested directly.
 */

/** Bounds, mirroring Renderer::MIN_ZOOM / MAX_ZOOM / DEFAULT_ZOOM. */
export const MIN_ZOOM = 1;
export const MAX_ZOOM = 22;
export const DEFAULT_ZOOM = 17;

/**
 * The site-wide maximum zoom, printed by the server before the editor script.
 *
 * Not read from /wp/v2/settings: core gates that route on manage_options, so
 * an Editor or Author would silently be shown the shipped default instead of
 * the real setting.
 */
export function siteDefaultMaxZoom(): number {
	const injected = (
		window as unknown as { gpxrmDefaults?: { maxZoom?: number } }
	 ).gpxrmDefaults?.maxZoom;

	if ( 'number' !== typeof injected || ! Number.isFinite( injected ) ) {
		return DEFAULT_ZOOM;
	}

	return Math.min( MAX_ZOOM, Math.max( MIN_ZOOM, Math.trunc( injected ) ) );
}
