/**
 * Editor-side checks for a hand-entered tile URL.
 *
 * Deliberately free of WordPress imports so it can be unit tested directly.
 */

/**
 * Whether the browser will refuse to load these tiles.
 *
 * MapLibre fetches raster tiles with fetch() rather than as <img> elements,
 * because the Map default `refreshExpiredTiles: true` rules out the image
 * path. That distinction decides the answer: an <img> over http on an https
 * page is silently upgraded, but fetch() is *blockable* mixed content and is
 * refused outright. The map then draws the track, markers, stats and
 * elevation profile over a blank basemap, with nothing logged, because the
 * view registers no MapLibre error handler.
 *
 * Keyed on the protocol of the page doing the editing, not on the tile host.
 * An https admin screen implies an https front end, where the block applies
 * whatever the host is; an http site has no mixed content to worry about, so
 * a local dev environment never sees this. That also avoids classifying the
 * host, which is a nest of edge cases: 127.1, 127.000.000.1 and [::1] all
 * reach the loopback yet none of them passes a naive IP check.
 *
 * @param url          The tile URL template as typed.
 * @param pageProtocol location.protocol of the editing page, e.g. 'https:'.
 */
export function tileUrlWillBeBlocked(
	url: string,
	pageProtocol: string
): boolean {
	if ( 'https:' !== pageProtocol ) {
		return false;
	}

	return /^\s*http:\/\//i.test( url );
}
