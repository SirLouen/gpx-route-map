<?php
/**
 * Tests for choosing which figures the stats bar lists.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Renderer;

/**
 * Resolve a statFields attribute the way Renderer::normalize() does.
 *
 * @param mixed $raw Attribute value.
 * @return array<int, string>
 */
function gpxrm_resolve_fields( $raw ): array {
	$method = new ReflectionMethod( Renderer::class, 'normalize_stat_fields' );
	$method->setAccessible( true );

	return $method->invoke( null, $raw, Renderer::default_stat_fields() );
}

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options'] = array();
	}
);

test(
	'every stat is listed by default',
	function () {
		expect( Renderer::default_stat_fields() )->toBe(
			array( 'distance', 'gain', 'loss', 'max', 'waypoints' )
		);
	}
);

test(
	'the site setting narrows the list',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_stat_fields'] = 'distance,max';

		expect( Renderer::default_stat_fields() )->toBe( array( 'distance', 'max' ) );
		expect( gpxrm_resolve_fields( '' ) )->toBe( array( 'distance', 'max' ) );
	}
);

test(
	'the list never empties, because hiding the bar is a separate setting',
	function () {
		// Storing nothing must not become a second way to hide the bar.
		$GLOBALS['gpxrm_test_options']['gpxrm_stat_fields'] = '';
		expect( Renderer::default_stat_fields() )->toBe(
			array( 'distance', 'gain', 'loss', 'max', 'waypoints' )
		);

		// Nor may a map empty it.
		expect( gpxrm_resolve_fields( 'none' ) )->not->toBe( array() );
		expect( gpxrm_resolve_fields( 'nope,nada' ) )->not->toBe( array() );
	}
);

test(
	'a map can choose its own stats regardless of the site setting',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_stat_fields'] = 'distance';

		expect( gpxrm_resolve_fields( 'gain,loss' ) )->toBe( array( 'gain', 'loss' ) );
		// An unrecognised list falls back to the site setting.
		expect( gpxrm_resolve_fields( 'none' ) )->toBe( array( 'distance' ) );
	}
);

test(
	'unknown keys are dropped and the display order is enforced',
	function ( $raw, $expected ) {
		expect( gpxrm_resolve_fields( $raw ) )->toBe( $expected );
	}
)->with(
	array(
		'reordered'   => array( 'waypoints,distance', array( 'distance', 'waypoints' ) ),
		'unknown key' => array( 'distance,bogus', array( 'distance' ) ),
		'spaces'      => array( ' gain , max ', array( 'gain', 'max' ) ),
		'uppercase'   => array( 'DISTANCE', array( 'distance' ) ),
		'duplicates'  => array( 'gain,gain', array( 'gain' ) ),
	)
);

test(
	'a non-string attribute falls back to the site setting',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_stat_fields'] = 'max';

		expect( gpxrm_resolve_fields( null ) )->toBe( array( 'max' ) );
		expect( gpxrm_resolve_fields( 42 ) )->toBe( array( 'max' ) );
	}
);
