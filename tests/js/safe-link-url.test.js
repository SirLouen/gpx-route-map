/**
 * Waypoint popup links must only ever carry safe schemes.
 */

import { describe, expect, it } from 'vitest';

import { safeLinkUrl } from '../../src/view/map-core';

describe( 'safeLinkUrl', () => {
	it.each( [
		[ 'https://gpx.studio/route', 'https://gpx.studio/route' ],
		[ 'http://example.org/a', 'http://example.org/a' ],
		[ 'mailto:x@y.z', 'mailto:x@y.z' ],
		[ 'relative/path.html', 'relative/path.html' ],
		[ '//evil.example/x', '//evil.example/x' ],
		[
			'y" onmouseover="alert(document.cookie)',
			'y" onmouseover="alert(document.cookie)',
		],
	] )( 'keeps %s', ( input, expected ) => {
		expect( safeLinkUrl( input ) ).toBe( expected );
	} );

	it.each( [
		[ 'javascript:alert(1)' ],
		[ 'JaVaScRiPt:alert(1)' ],
		[ 'data:text/html,<script>alert(1)</script>' ],
		[ 'vbscript:msgbox' ],
		[ '' ],
	] )( 'rejects %s', ( input ) => {
		expect( safeLinkUrl( input ) ).toBe( '' );
	} );
} );
