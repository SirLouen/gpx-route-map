/**
 * The scheme check for the author-supplied GPX URL.
 *
 * The attribute is free text and survives saving untouched even for an author
 * without `unfiltered_html`, because it lives inside the block delimiter's
 * JSON rather than in markup kses inspects. Verified against a real
 * WordPress: a contributor-saved `javascript:` value came back from the
 * database byte for byte. So the editor cannot assume it is a URL at all.
 */

import { describe, expect, it } from 'vitest';

import { safeGpxUrl } from '../../src/gpx-url';

describe( 'safeGpxUrl', () => {
	it.each( [
		[ 'https', 'https://example.com/track.gpx' ],
		[ 'http', 'http://example.com/track.gpx' ],
		[ 'a query string', 'https://example.com/g?id=4&x=1' ],
		[ 'an uploads path', 'http://localhost:8888/wp-content/a.gpx' ],
	] )( 'passes %s through unchanged', ( _label, url ) => {
		expect( safeGpxUrl( url ) ).toBe( url );
	} );

	it.each( [
		[ 'javascript', 'javascript:alert(document.domain)' ],
		[ 'javascript with mixed case', 'JaVaScRiPt:alert(1)' ],
		[ 'javascript with leading space', ' javascript:alert(1)' ],
		[ 'data html', 'data:text/html,<script>alert(1)</script>' ],
		[ 'vbscript', 'vbscript:msgbox(1)' ],
		[ 'file', 'file:///etc/passwd' ],
		[ 'mailto, which is not a track', 'mailto:someone@example.com' ],
	] )( 'refuses %s', ( _label, url ) => {
		expect( safeGpxUrl( url ) ).toBe( '' );
	} );

	it.each( [
		[ 'empty', '' ],
		[ 'whitespace only', '   ' ],
		[ 'unparseable', 'http://[' ],
	] )( 'refuses %s', ( _label, url ) => {
		expect( safeGpxUrl( url ) ).toBe( '' );
	} );

	it( 'keeps relative URLs, which resolve the way the fetch does', () => {
		// jsdom serves these from http://localhost/, so they stay usable.
		expect( safeGpxUrl( '/uploads/track.gpx' ) ).toBe(
			'/uploads/track.gpx'
		);
	} );
} );
