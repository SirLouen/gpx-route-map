/**
 * Link addresses a map may open.
 */

/**
 * Validate a link URL, allowing only safe schemes.
 *
 * @param url Raw URL, from a GPX file or a Waypoint post.
 * @return The original URL if safe, otherwise "".
 */
export function safeLinkUrl( url: string ): string {
	if ( ! url ) {
		return '';
	}
	let parsed: URL;
	try {
		parsed = new URL( url, window.location.href );
	} catch {
		return '';
	}
	return [ 'http:', 'https:', 'mailto:' ].includes( parsed.protocol )
		? url
		: '';
}
