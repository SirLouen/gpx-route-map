<?php
/**
 * Tests for the stats bar / elevation profile visibility resolution.
 *
 * The attribute is tri-state ('' defers to the site setting), but content
 * saved before the setting existed stores booleans, and the shortcode has
 * always used "true"/"false" strings. All three have to keep working.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Renderer;

/**
 * Resolve a visibility attribute the way Renderer::normalize() does.
 *
 * @param mixed $raw Attribute value.
 * @return bool
 */
function gpxrm_resolve_stats( $raw ): bool {
	$method = new ReflectionMethod( Renderer::class, 'normalize_visibility' );
	$method->setAccessible( true );

	return $method->invoke( null, $raw, Renderer::default_show_stats() );
}

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options'] = array();
	}
);

test(
	'the site default shows both panels out of the box',
	function () {
		expect( Renderer::default_show_stats() )->toBeTrue();
		expect( Renderer::default_show_elevation() )->toBeTrue();
	}
);

test(
	'the site setting can hide a panel',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_show_stats'] = 'hide';

		expect( Renderer::default_show_stats() )->toBeFalse();
		// The two panels are independent.
		expect( Renderer::default_show_elevation() )->toBeTrue();
	}
);

test(
	'an empty attribute follows the site setting',
	function () {
		expect( gpxrm_resolve_stats( '' ) )->toBeTrue();

		$GLOBALS['gpxrm_test_options']['gpxrm_show_stats'] = 'hide';
		expect( gpxrm_resolve_stats( '' ) )->toBeFalse();
	}
);

test(
	'an explicit choice overrides a site setting that disagrees',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_show_stats'] = 'hide';

		expect( gpxrm_resolve_stats( 'show' ) )->toBeTrue();
	}
);

test(
	'blocks saved as booleans before the setting existed still resolve',
	function () {
		// A site default of "hide" must not override an explicit legacy true.
		$GLOBALS['gpxrm_test_options']['gpxrm_show_stats'] = 'hide';

		expect( gpxrm_resolve_stats( true ) )->toBeTrue();
		expect( gpxrm_resolve_stats( false ) )->toBeFalse();
	}
);

test(
	'shortcode strings keep their long-standing meaning',
	function ( $raw, $expected ) {
		expect( gpxrm_resolve_stats( $raw ) )->toBe( $expected );
	}
)->with(
	array(
		'true'  => array( 'true', true ),
		'false' => array( 'false', false ),
		'yes'   => array( 'yes', true ),
		'no'    => array( 'no', false ),
		'1'     => array( '1', true ),
		'0'     => array( '0', false ),
		'off'   => array( 'off', false ),
		'HIDE'  => array( 'HIDE', false ),
		' show' => array( ' show ', true ),
	)
);

test(
	'an unrecognised value falls back to the site setting',
	function () {
		expect( gpxrm_resolve_stats( 'maybe' ) )->toBeTrue();

		$GLOBALS['gpxrm_test_options']['gpxrm_show_stats'] = 'hide';
		expect( gpxrm_resolve_stats( 'maybe' ) )->toBeFalse();
		expect( gpxrm_resolve_stats( null ) )->toBeFalse();
	}
);
