<?php
/**
 * Per-viewport map heights.
 *
 * The trap this suite exists for: leaving the literal `height` in the map's
 * style attribute "as a fallback". An inline declaration beats any stylesheet
 * rule, so the media queries would never apply, while phpcs, PHPStan, Pest,
 * tsc, eslint, vitest and Plugin Check all stay green and the editor looks
 * right. Nothing else in the project can see it.
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
 * The style attribute of the rendered .gpxrm-map element.
 *
 * @param array<string, mixed> $atts Block attributes.
 * @return string
 */
function gpxrm_map_style( array $atts = array() ): string {
	$html = Renderer::render( $atts + array( 'gpxUrl' => 'https://example.test/route.gpx' ) );
	preg_match( '/class="gpxrm-map" style="([^"]*)"/', $html, $m );

	return $m[1] ?? '';
}

test(
	'a map with no per-viewport height keeps the plain inline height',
	function () {
		// Byte-identical to what every published map already renders, so an
		// existing post does not start depending on the stylesheet.
		expect( gpxrm_map_style() )->toBe( 'height:480px' );
	}
);

test(
	'a map with a per-viewport height emits no literal height at all',
	function ( $atts ) {
		$style = gpxrm_map_style( $atts );

		// The whole feature rests on this: an inline height would win over
		// every media query and the breakpoints could never apply.
		expect( $style )->not->toContain( 'height:' );
		expect( $style )->toContain( '--gpxrm-h:' );
	}
)->with(
	array(
		'tablet set on the map' => array( array( 'heightTablet' => 400 ) ),
		'mobile set on the map' => array( array( 'heightMobile' => 320 ) ),
		'both set on the map'   => array(
			array(
				'heightTablet' => 400,
				'heightMobile' => 320,
			),
		),
	)
);

test(
	'a site-wide per-viewport height reaches a map that sets nothing itself',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_height_mobile'] = 320;

		$style = gpxrm_map_style();

		expect( $style )->not->toContain( 'height:' );
		expect( $style )->toContain( '--gpxrm-h-m:320px' );
	}
);

test(
	'a band with nothing of its own follows the next larger one',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_height']        = 600;
		$GLOBALS['gpxrm_test_options']['gpxrm_height_tablet'] = 400;

		// Mobile is unset, so it inherits tablet rather than jumping back to
		// the base height.
		$style = gpxrm_map_style();

		expect( $style )->toContain( '--gpxrm-h:600px' );
		expect( $style )->toContain( '--gpxrm-h-t:400px' );
		expect( $style )->toContain( '--gpxrm-h-m:400px' );
	}
);

test(
	'a per-map height beats the site setting for the same band',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_height_mobile'] = 320;

		$style = gpxrm_map_style( array( 'heightMobile' => 260 ) );

		expect( $style )->toContain( '--gpxrm-h-m:260px' );
		expect( $style )->not->toContain( '--gpxrm-h-m:320px' );
	}
);

test(
	'per-viewport heights are clamped like the base height',
	function () {
		$style = gpxrm_map_style(
			array(
				'heightTablet' => 99999,
				'heightMobile' => 10,
			)
		);

		expect( $style )->toContain( '--gpxrm-h-t:' . Renderer::MAX_HEIGHT . 'px' );
		expect( $style )->toContain( '--gpxrm-h-m:' . Renderer::MIN_HEIGHT . 'px' );
	}
);

test(
	'a filter may change one band without discarding the others',
	function () {
		// Returning only the band you care about is the natural way to use the
		// hook. Indexing the result directly warned on PHP 8 and zeroed every
		// band the filter did not mention.
		$GLOBALS['gpxrm_test_options']['gpxrm_height']        = 500;
		$GLOBALS['gpxrm_test_options']['gpxrm_height_tablet'] = 400;

		add_filter( 'gpxrm_heights', fn() => array( 'base' => 700 ) );

		expect( Renderer::default_heights() )->toBe(
			array(
				'base'   => 700,
				'tablet' => 400,
				'mobile' => 0,
			)
		);
	}
);

test(
	'a filter that returns nothing usable leaves the heights alone',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_height'] = 500;

		add_filter( 'gpxrm_heights', fn() => null );

		expect( Renderer::default_heights()['base'] )->toBe( 500 );
	}
);
