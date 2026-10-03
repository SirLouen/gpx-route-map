<?php
/**
 * Translations travelling to the view in JSON data attributes.
 *
 * Two ways for an entity in a translation to go wrong. Left as text, the view
 * shows "&nbsp;" where a visitor reading the same translation anywhere else
 * WordPress prints it sees a space. Left to the browser, an entity esc_attr()
 * did not touch, such as "&quot;", decodes to a bare quote inside the JSON,
 * which breaks it and sends every string in the attribute back to English.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Renderer;

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options'] = array();
		$GLOBALS['gpxrm_test_filters'] = array();
	}
);

/**
 * A data attribute of the rendered map, decoded the way the view reads it.
 *
 * @param string $html Rendered map.
 * @param string $name Attribute name.
 * @return mixed
 */
function gpxrm_attribute_json( string $html, string $name ) {
	preg_match( '/ ' . preg_quote( $name, '/' ) . '="([^"]*)"/', $html, $m );

	// The browser unescapes the attribute value before JSON.parse sees it.
	return json_decode( html_entity_decode( $m[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );
}

test(
	'a translation with entities reaches the view as a visitor reads it',
	function () {
		// A quote and an ampersand, the non-breaking space French puts before a
		// colon, an escaped entity that must stay one after decoding, and names
		// WordPress does not recognise as entities, so it prints them as text.
		$suffix    = ' &quot;x&quot; &amp; y&nbsp;: &amp;quot;z&amp;quot; &apos;&bigstar;&NewLine;';
		$translate = fn( $translation ) => $translation . $suffix;
		add_filter( 'gettext', $translate );
		add_filter( 'gettext_with_context', $translate );

		// What the browser shows for that translation printed through esc_html().
		$seen = " \"x\" & y\u{00A0}: &quot;z&quot; &apos;&bigstar;&NewLine;";

		$html = Renderer::render( array( 'gpxUrl' => 'https://example.test/route.gpx' ) );

		expect( gpxrm_attribute_json( $html, 'data-gpxrm-i18n' )['load'] ?? null )
			->toBe( 'Could not load GPX file.' . $seen );
		expect( gpxrm_attribute_json( $html, 'data-gpxrm-map-ui' )['Popup.Close'] ?? null )
			->toBe( 'Close popup' . $seen );
		expect( gpxrm_attribute_json( $html, 'data-gpxrm-units' )['distLabel'] ?? null )
			->toBe( 'km' . $seen );
	}
);
