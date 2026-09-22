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
	'a stored zoom above the range is clamped rather than trusted',
	function ( $stored, $expected ) {
		$GLOBALS['gpxrm_test_options']['gpxrm_max_zoom'] = $stored;

		expect( Renderer::default_max_zoom() )->toBe( $expected );
	}
)->with(
	array(
		'too high'       => array( 99, Renderer::MAX_ZOOM ),
		'numeric string' => array( '18', 18 ),
		// Fractional but positive, so a real zoom rather than a missing one.
		'fractional'     => array( 0.5, Renderer::MIN_ZOOM ),
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
	'saving a zoom that is not a real zoom restores the default',
	function ( $submitted ) {
		// This is the path that actually reaches the database: the settings
		// API runs the sanitizer on every update_option, so clamping here
		// would persist MIN_ZOOM and outlive whatever caused it.
		expect( ( new Plugin() )->sanitize_max_zoom( $submitted ) )->toBe( Renderer::DEFAULT_ZOOM );
	}
)->with(
	array(
		'zero'           => array( 0 ),
		'zero as string' => array( '0' ),
		'negative'       => array( -3 ),
		'emptied field'  => array( '' ),
		'nonsense'       => array( 'close' ),
	)
);

test(
	'saving a real zoom keeps it, clamped to the supported range',
	function ( $submitted, int $expected ) {
		expect( ( new Plugin() )->sanitize_max_zoom( $submitted ) )->toBe( $expected );
	}
)->with(
	array(
		'in range'   => array( 19, 19 ),
		'as string'  => array( '19', 19 ),
		'too high'   => array( 99, Renderer::MAX_ZOOM ),
		'fractional' => array( 0.5, Renderer::MIN_ZOOM ),
	)
);

test(
	'a stored value that is not a real zoom falls back to the default',
	function ( $stored ) {
		// Clamping instead would give MIN_ZOOM - the most zoomed-out level a
		// map can show - so a site that never set a zoom, or that an old
		// version left at zero, would open on a view of the whole world.
		$GLOBALS['gpxrm_test_options']['gpxrm_max_zoom'] = $stored;

		expect( Renderer::default_max_zoom() )->toBe( Renderer::DEFAULT_ZOOM );
	}
)->with(
	array(
		'zero'            => array( 0 ),
		'zero as string'  => array( '0' ),
		'negative'        => array( -1 ),
		'empty string'    => array( '' ),
		'nonsense'        => array( 'close' ),
	)
);

test(
	'a filter cannot leave maps on a zoom that is not a real zoom',
	function () {
		add_filter( 'gpxrm_max_zoom', fn() => 0 );

		expect( Renderer::default_max_zoom() )->toBe( Renderer::DEFAULT_ZOOM );
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
