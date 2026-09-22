/**
 * Editor-side checks for a hand-entered tile URL.
 *
 * Deliberately free of WordPress imports so it can be unit tested directly.
 */

/**
 * What is wrong with a tile URL, if anything.
 *
 * 'insecure' - the browser will refuse to fetch it.
 * 'ignored'  - the plugin itself will discard it.
 */
export type TileUrlProblem = 'insecure' | 'ignored' | null;

/** Mirrors the scheme test in Renderer::sanitize_tile_url(). */
const HAS_SCHEME = /^\s*https?:\/\//i;

/** An address the browser blocks as mixed content when the page is https. */
const IS_HTTP = /^\s*http:\/\//i;

/**
 * Why a tile URL will not produce the map the author expects.
 *
 * Both problems fail the same way - a blank basemap with the track, markers,
 * stats bar and elevation profile drawn over nothing - and neither logs
 * anything, because the view registers no MapLibre error handler. The only
 * place either can be noticed is here, while the value is being typed.
 *
 * 'insecure': MapLibre fetches raster tiles with fetch() rather than as <img>
 * elements, because the Map default `refreshExpiredTiles: true` rules out the
 * image path. An <img> over http on an https page would be silently upgraded,
 * but fetch() is *blockable* mixed content and is refused outright.
 *
 * Keyed on the protocol of the editing page rather than the tile host, which
 * avoids classifying the host - a nest of edge cases, since 127.1,
 * 127.000.000.1 and [::1] all reach the loopback yet none of them passes a
 * naive IP check.
 *
 * 'ignored': Renderer::sanitize_tile_url() requires an http or https scheme,
 * so a protocol-relative "//host/..." or a bare "host/..." is discarded and
 * the map quietly falls back to OpenStreetMap. That is a plugin rule, not a
 * browser one, so it applies whatever the page protocol is.
 *
 * This mirrors only the scheme test, not the FILTER_VALIDATE_URL step that
 * follows it in PHP. It is advisory and deliberately conservative: it may
 * stay silent about a URL PHP would still reject, but it never calls one
 * acceptable that PHP would refuse.
 *
 * @param url          The tile URL template as typed.
 * @param pageProtocol location.protocol of the editing page, e.g. 'https:'.
 */
export function tileUrlProblem(
	url: string,
	pageProtocol: string
): TileUrlProblem {
	// Blank is not a mistake: it means "use the default tiles".
	if ( '' === url.trim() ) {
		return null;
	}

	if ( ! HAS_SCHEME.test( url ) ) {
		return 'ignored';
	}

	if ( 'https:' === pageProtocol && IS_HTTP.test( url ) ) {
		return 'insecure';
	}

	return null;
}
