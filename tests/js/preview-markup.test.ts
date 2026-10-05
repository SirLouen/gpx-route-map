/**
 * Deciding how the editor preview follows a settings change.
 *
 * The preview shows the markup WordPress renders for the block, so it matches
 * the front end exactly. When that markup changes, the map is rebuilt only if
 * something the map itself was built from changed; a new height or new stats
 * values are patched into the live map instead, so it keeps its position.
 */

import { describe, expect, it } from 'vitest';

import {
	extractPreview,
	isNotice,
	needsRebuild,
	patchPreview,
} from '../../src/editor/preview-markup';

/**
 * The block markup as /wp/v2/block-renderer returns it.
 *
 * @param changes Values to change from the default.
 */
function rendered(
	changes: Partial< {
		height: string;
		tileUrl: string;
		units: string;
		gpx: string;
		statsValue: string;
		stats: boolean;
		elevation: boolean;
		download: boolean;
	} > = {}
): string {
	const o = {
		height: 'height:480px',
		tileUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
		units: '{&quot;distLabel&quot;:&quot;km&quot;}',
		gpx: 'https://example.test/route.gpx',
		statsValue: '12.3 km',
		stats: true,
		elevation: true,
		download: false,
		...changes,
	};
	return (
		'<div class="wp-block-gpx-route-map-map alignwide"><div class="gpxrm">' +
		`<div class="gpxrm-map" style="${ o.height }" data-gpxrm-gpx="${ o.gpx }" data-gpxrm-tile-url="${ o.tileUrl }" data-gpxrm-units="${ o.units }"` +
		( o.download ? ' data-gpxrm-download="route.gpx"' : '' ) +
		' role="application"><div class="gpxrm-placeholder"></div></div>' +
		( o.stats
			? `<dl class="gpxrm-stats"><div class="gpxrm-stat"><dd class="gpxrm-stat-value" data-gpxrm-stat="distance">${ o.statsValue }</dd></div></dl>`
			: '' ) +
		( o.elevation
			? '<figure class="gpxrm-elevation"><canvas data-gpxrm-elevation></canvas></figure>'
			: '' ) +
		'</div></div>'
	);
}

/**
 * Extract the preview from markup, failing the test when there is none.
 *
 * @param html Rendered markup.
 */
function preview( html: string ): HTMLElement {
	const el = extractPreview( html, document );
	if ( ! el ) {
		throw new Error( 'no .gpxrm in the markup' );
	}
	return el;
}

describe( 'extractPreview', () => {
	it( 'takes the map and its stats from inside the block wrapper', () => {
		const el = preview( rendered() );

		expect( el.className ).toBe( 'gpxrm' );
		expect( el.querySelector( '.gpxrm-map' ) ).not.toBeNull();
		expect( el.querySelector( '.gpxrm-stats' ) ).not.toBeNull();
	} );

	it( 'returns null for the notice WordPress renders without a file', () => {
		expect(
			extractPreview(
				'<div class="gpxrm-notice">No GPX file selected.</div>',
				document
			)
		).toBeNull();
	} );
} );

describe( 'isNotice', () => {
	it( 'is false for a map', () => {
		expect( isNotice( rendered(), document ) ).toBe( false );
	} );

	it.each( [
		[
			'the notice',
			'<div class="gpxrm-notice">No GPX file selected.</div>',
		],
		[ 'a preview without a map', '<div class="gpxrm"><dl></dl></div>' ],
	] )( 'is true for %s', ( _label, html ) => {
		expect( isNotice( html, document ) ).toBe( true );
	} );
} );

describe( 'needsRebuild', () => {
	it.each( [
		[ 'a new height', { height: 'height:320px' } ],
		[ 'new stats values', { statsValue: '7.1 mi' } ],
	] )( 'keeps the live map for %s', ( _label, changes ) => {
		expect(
			needsRebuild(
				preview( rendered() ),
				preview( rendered( changes ) )
			)
		).toBe( false );
	} );

	it.each( [
		[ 'another GPX file', { gpx: 'https://example.test/other.gpx' } ],
		[
			'other tiles',
			{ tileUrl: 'https://tiles.example.test/{z}/{x}/{y}.png' },
		],
		[ 'other units', { units: '{&quot;distLabel&quot;:&quot;mi&quot;}' } ],
		[ 'the download button toggled', { download: true } ],
		[ 'the elevation profile toggled', { elevation: false } ],
		[ 'the stats bar toggled', { stats: false } ],
	] )( 'rebuilds the map for %s', ( _label, changes ) => {
		expect(
			needsRebuild(
				preview( rendered() ),
				preview( rendered( changes ) )
			)
		).toBe( true );
	} );

	// The editor marks the live map so the front end's own boot leaves it
	// alone; those marks are not settings.
	it( 'ignores the marks the editor adds to the live map', () => {
		const live = preview( rendered() );
		const map = live.querySelector< HTMLElement >( '.gpxrm-map' );
		if ( map ) {
			map.dataset.gpxrmBooted = '1';
			map.dataset.gpxrmPreview = '';
		}

		expect( needsRebuild( live, preview( rendered() ) ) ).toBe( false );
	} );
} );

describe( 'patchPreview', () => {
	it( 'applies the new height and stats without touching the map', () => {
		const live = preview( rendered() );
		const map = live.querySelector( '.gpxrm-map' );
		const canvas = document.createElement( 'canvas' );
		map?.append( canvas );

		patchPreview(
			live,
			preview(
				rendered( { height: 'height:320px', statsValue: '7.1 mi' } )
			)
		);

		expect( map?.getAttribute( 'style' ) ).toBe( 'height:320px' );
		expect(
			live.querySelector( '[data-gpxrm-stat="distance"]' )?.textContent
		).toBe( '7.1 mi' );
		// Same element, same canvas: the map was not rebuilt.
		expect( live.querySelector( '.gpxrm-map' ) ).toBe( map );
		expect( map?.contains( canvas ) ).toBe( true );
	} );
} );
