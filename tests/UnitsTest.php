<?php
/**
 * Unit tests for the display-unit configuration.
 *
 * These factors are the single definition of the metric/imperial conversion for
 * the whole plugin: the server formats the no-JS stats bar with them and hands
 * the same numbers to the browser, so a typo here would be silent.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Renderer;

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options'] = array();
	}
);

test(
	'metric leaves distances and elevations untouched',
	function () {
		$units = Renderer::units_config( 'metric' );

		expect( $units['distFactor'] )->toBe( 1.0 );
		expect( $units['eleFactor'] )->toBe( 1.0 );
		expect( $units['distLabel'] )->toBe( 'km' );
		expect( $units['eleLabel'] )->toBe( 'm' );
	}
);

test(
	'imperial converts kilometres to miles and metres to feet',
	function () {
		$units = Renderer::units_config( 'imperial' );

		expect( $units['distLabel'] )->toBe( 'mi' );
		expect( $units['eleLabel'] )->toBe( 'ft' );

		// A marathon is 42.195 km, which is 26.2187... miles.
		expect( 42.195 * $units['distFactor'] )->toBeGreaterThan( 26.21 );
		expect( 42.195 * $units['distFactor'] )->toBeLessThan( 26.22 );

		// Mont Blanc is 4808 m, which is 15774.3... feet.
		expect( round( 4808 * $units['eleFactor'] ) )->toBe( 15774.0 );
	}
);

test(
	'an unknown unit system falls back to metric',
	function () {
		$units = Renderer::units_config( 'furlongs' );

		expect( $units['distFactor'] )->toBe( 1.0 );
		expect( $units['distLabel'] )->toBe( 'km' );
	}
);

test(
	'the site default is metric unless the option says otherwise',
	function () {
		expect( Renderer::default_units() )->toBe( 'metric' );

		$GLOBALS['gpxrm_test_options']['gpxrm_units'] = 'imperial';
		expect( Renderer::default_units() )->toBe( 'imperial' );
	}
);

test(
	'a junk option value falls back to metric',
	function ( $stored ) {
		$GLOBALS['gpxrm_test_options']['gpxrm_units'] = $stored;

		expect( Renderer::default_units() )->toBe( 'metric' );
	}
)->with(
	array(
		'empty string' => '',
		'unknown'      => 'nautical',
		'integer'      => 1,
		// Wrapped twice: Pest spreads a dataset array as the argument list.
		'array'        => array( array( 'imperial' ) ),
	)
);
