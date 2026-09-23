/**
 * Scheme check for the author-supplied GPX URL.
 *
 * Free of WordPress imports so it can be unit tested directly.
 */

/**
 * The URL when it is safe to hand to a link, otherwise an empty string.
 *
 * The attribute is free text typed into the inspector, and it survives saving
 * untouched even for an author without `unfiltered_html`: it lives inside the
 * block delimiter's JSON, which kses does not parse as markup. The front end
 * is already covered - Renderer::normalize() runs it through esc_url_raw() -
 * but the editor previously used it as an href exactly as written, so a
 * contributor could store a scheme that an editor or administrator would then
 * have rendered into their own admin session.
 *
 * http and https only. A GPX track is fetched over the network, so no other
 * scheme is meaningful here; this is the narrower cousin of safeLinkUrl(),
 * which also permits mailto: because it serves waypoint links. Relative URLs
 * still pass, resolving against the current page the way the fetch does.
 *
 * @param url Stored attribute value.
 */
export function safeGpxUrl( url: string ): string {
	// Whitespace alone would otherwise resolve against the current page and
	// pass as http, putting a blank target on the link.
	if ( ! url || ! url.trim() ) {
		return '';
	}

	let parsed: URL;

	try {
		parsed = new URL( url, window.location.href );
	} catch {
		return '';
	}

	return [ 'http:', 'https:' ].includes( parsed.protocol ) ? url : '';
}
