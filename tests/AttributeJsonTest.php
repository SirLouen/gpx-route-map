<?php
/**
 * Translations travelling to the view in JSON data attributes.
 *
 * esc_attr() leaves an entity that is already in the text alone, so a
 * translation containing "&quot;" printed as plain JSON reaches the browser as
 * a bare quote. That breaks the JSON, and the view then falls back to English
 * for every string in the attribute, not just the one with the entity.
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
	'a translation containing an entity reaches the view intact',
	function () {
		$translate = fn( $translation ) => $translation . ' &quot;x&quot; &amp; y';
		add_filter( 'gettext', $translate );
		add_filter( 'gettext_with_context', $translate );

		$html = Renderer::render( array( 'gpxUrl' => 'https://example.test/route.gpx' ) );

		expect( gpxrm_attribute_json( $html, 'data-gpxrm-i18n' )['load'] ?? null )
			->toBe( 'Could not load GPX file. &quot;x&quot; &amp; y' );
		expect( gpxrm_attribute_json( $html, 'data-gpxrm-map-ui' )['Popup.Close'] ?? null )
			->toBe( 'Close popup &quot;x&quot; &amp; y' );
		expect( gpxrm_attribute_json( $html, 'data-gpxrm-units' )['distLabel'] ?? null )
			->toBe( 'km &quot;x&quot; &amp; y' );
	}
);
