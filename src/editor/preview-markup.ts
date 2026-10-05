/**
 * Turning rendered block markup into the editor preview, and deciding how the
 * preview follows a change.
 *
 * The preview shows exactly the markup WordPress renders for the block, so it
 * matches the front end. A change of settings re-renders it; the live map is
 * rebuilt only when something it was built from changed.
 */

/** The marks the editor puts on the live map; they are not settings. */
const EDITOR_MARKS = new Set( [ 'data-gpxrm-booted', 'data-gpxrm-preview' ] );

/**
 * Rendered block markup, parsed.
 *
 * Parsed in a <template>, whose content is inert: nothing in the markup runs
 * or loads until it is shown.
 *
 * @param html Markup from /wp/v2/block-renderer.
 * @param doc  The canvas document.
 */
export function parseRendered( html: string, doc: Document ): DocumentFragment {
	const template = doc.createElement( 'template' );
	template.innerHTML = html;
	return template.content;
}

/**
 * The `.gpxrm` element of rendered block markup.
 *
 * @param html Markup from /wp/v2/block-renderer.
 * @param doc  The canvas document.
 * @return The preview, or null for markup without one, such as the notice
 *         WordPress renders when the GPX file is missing.
 */
export function extractPreview(
	html: string,
	doc: Document
): HTMLElement | null {
	return parseRendered( html, doc ).querySelector< HTMLElement >( '.gpxrm' );
}

/**
 * Whether rendered block markup is a notice WordPress shows instead of a map,
 * such as when the GPX file is missing.
 *
 * @param html Markup from /wp/v2/block-renderer.
 * @param doc  A document to parse it in.
 */
export function isNotice( html: string, doc: Document ): boolean {
	return ! extractPreview( html, doc )?.querySelector( '.gpxrm-map' );
}

/**
 * What the live map was built from: the map element's settings and which
 * parts surround it.
 *
 * @param preview A `.gpxrm` element.
 */
function buildKey( preview: Element ): string {
	const map = preview.querySelector( '.gpxrm-map' );
	const settings = map
		? [ ...map.attributes ]
				.filter(
					( a ) =>
						a.name.startsWith( 'data-gpxrm-' ) &&
						! EDITOR_MARKS.has( a.name )
				)
				.map( ( a ) => `${ a.name }=${ a.value }` )
				.sort()
		: [];
	return JSON.stringify( {
		settings,
		stats: !! preview.querySelector( '.gpxrm-stats' ),
		elevation: !! preview.querySelector( '[data-gpxrm-elevation]' ),
	} );
}

/**
 * Whether going from the live preview to new markup means rebuilding the map.
 *
 * The height (the map element's style) and the stats values are patched in
 * place by patchPreview(). Anything else the map reads when it is built - the
 * GPX file, tiles, attribution, zoom, units, translations, the download
 * button - and adding or removing the stats bar or elevation profile, which
 * the map instance wires up, needs a new map.
 *
 * @param live The preview on screen.
 * @param next The preview from the new markup.
 */
export function needsRebuild( live: Element, next: Element ): boolean {
	return buildKey( live ) !== buildKey( next );
}

/**
 * Apply new markup to the live preview without rebuilding the map.
 *
 * Only for changes needsRebuild() lets through. MapLibre follows its
 * container's size by itself, so a new height needs nothing more than the new
 * style.
 *
 * @param live The preview on screen.
 * @param next The preview from the new markup.
 */
export function patchPreview( live: Element, next: Element ): void {
	// The server always gives the map its height there.
	const style = next.querySelector( '.gpxrm-map' )?.getAttribute( 'style' );
	if ( style ) {
		live.querySelector( '.gpxrm-map' )?.setAttribute( 'style', style );
	}

	const nextStats = next.querySelector( '.gpxrm-stats' );
	if ( nextStats ) {
		live.querySelector( '.gpxrm-stats' )?.replaceWith( nextStats );
	}
}
