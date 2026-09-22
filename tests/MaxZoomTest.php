<?php
/**
 * The site-wide maximum zoom.
 *
 * Mirrors the height setting: 0 or nothing at all means "follow the site",
 * so changing the site setting reaches every map that has not chosen its own.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Plugin;
use Gpxrm\Renderer;

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options'] = array();
		$GLOBALS['gpxrm_test_filters'] = array();
	}
);

/**
 * Resolve attributes the way the block and shortcode both do.
 *
 * @param array<string, mixed> $atts Raw attributes.
 * @return array<string, mixed>
 */
function gpxrm_zoom_normalize( array $atts ): array {
	$method = new ReflectionMethod( Renderer::class, 'normalize' );
	$method->setAccessible( true );

	return (array) $method->invoke( null, $atts + array( 'gpxUrl' => 'https://e.test/a.gpx' ) );
}

test(
	'an unconfigured site uses the shipped default',
	function () {
		expect( Renderer::default_max_zoom() )->toBe( Renderer::DEFAULT_ZOOM );
	}
);

test(
	'a stored value outside the range is clamped rather than trusted',
	function ( $stored, $expected ) {
		$GLOBALS['gpxrm_test_options']['gpxrm_max_zoom'] = $stored;

		expect( Renderer::default_max_zoom() )->toBe( $expected );
	}
)->with(
	array(
		'too low'        => array( 0, Renderer::MIN_ZOOM ),
		'negative'       => array( -3, Renderer::MIN_ZOOM ),
		'too high'       => array( 99, Renderer::MAX_ZOOM ),
		'numeric string' => array( '18', 18 ),
		'nonsense'       => array( 'close', Renderer::DEFAULT_ZOOM ),
	)
);

test(
	'a map without its own zoom follows the site setting',
	function ( array $atts ) {
		$GLOBALS['gpxrm_test_options']['gpxrm_max_zoom'] = 19;

		expect( gpxrm_zoom_normalize( $atts )['max_zoom'] )->toBe( 19 );
	}
)->with(
	array(
		// Gutenberg omits an attribute equal to its default, so an inheriting
		// block arrives with no maxZoom at all.
		'attribute absent' => array( array() ),
		'explicit zero'    => array( array( 'maxZoom' => 0 ) ),
		'zero as string'   => array( array( 'maxZoom' => '0' ) ),
	)
);

test(
	'a map that sets its own zoom keeps it whatever the site says',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_max_zoom'] = 19;

		expect( gpxrm_zoom_normalize( array( 'maxZoom' => 14 ) )['max_zoom'] )->toBe( 14 );
	}
);

test(
	'a per-map zoom is still clamped',
	function () {
		expect( gpxrm_zoom_normalize( array( 'maxZoom' => 99 ) )['max_zoom'] )->toBe( Renderer::MAX_ZOOM );
	}
);

test(
	'the gpxrm_max_zoom filter changes what maps use',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_max_zoom'] = 16;
		add_filter( 'gpxrm_max_zoom', fn() => 20 );

		expect( Renderer::default_max_zoom() )->toBe( 20 );
	}
);

test(
	'a filter cannot push the zoom outside the supported range',
	function () {
		add_filter( 'gpxrm_max_zoom', fn() => 99 );

		expect( Renderer::default_max_zoom() )->toBe( Renderer::MAX_ZOOM );
	}
);

test(
	'the settings field shows the stored zoom, never the filtered one',
	function () {
		// Otherwise an unrelated "Save Changes" writes the filtered value into
		// the database, where it outlives the filter that produced it.
		$GLOBALS['gpxrm_test_options']['gpxrm_max_zoom'] = 16;
		add_filter( 'gpxrm_max_zoom', fn() => 20 );

		$plugin = new Plugin();

		ob_start();
		$plugin->render_max_zoom_field();
		$html = (string) ob_get_clean();

		expect( $html )->toContain( 'value="16"' );
		expect( $html )->not->toContain( 'value="20"' );
	}
);

test(
	'the shortcode defers to the site setting instead of hard-coding its own',
	function () {
		expect( Plugin::shortcode_defaults()['maxzoom'] )->toBe( 0 );
	}
);
