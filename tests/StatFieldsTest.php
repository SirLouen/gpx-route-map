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
		$GLOBALS['gpxrm_test_filters'] = array();
	}
);

test(
	'every stat but the min elevation is listed by default',
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

test(
	'the min elevation shows only where it is chosen, after the max',
	function () {
		expect( gpxrm_resolve_fields( '' ) )->not->toContain( 'min' );
		expect( gpxrm_resolve_fields( 'min' ) )->toBe( array( 'min' ) );
		expect( gpxrm_resolve_fields( 'waypoints,min,max' ) )->toBe( array( 'max', 'min', 'waypoints' ) );

		$GLOBALS['gpxrm_test_options']['gpxrm_stat_fields'] = 'distance,min';
		expect( Renderer::default_stat_fields() )->toBe( array( 'distance', 'min' ) );
	}
);

test(
	'the settings page offers the min elevation, unticked unless chosen',
	function () {
		$plugin = new Gpxrm\Plugin();

		expect( array_keys( Gpxrm\Plugin::stat_field_labels() ) )->toBe(
			array( 'distance', 'gain', 'loss', 'max', 'min', 'waypoints' )
		);
		expect( $plugin->sanitize_stat_fields( array( 'min', 'max' ) ) )->toBe( 'max,min' );

		ob_start();
		$plugin->render_stat_fields_field();
		$html = (string) ob_get_clean();
		expect( $html )->toContain( "value=\"max\" checked='checked'" );
		expect( $html )->toMatch( '/value="min">/' );
	}
);

test(
	'the settings page ticks what is in use',
	function ( $stored, array $ticked ) {
		$GLOBALS['gpxrm_test_options']['gpxrm_stat_fields'] = $stored;

		ob_start();
		( new Gpxrm\Plugin() )->render_stat_fields_field();
		preg_match_all( "/value=\"([a-z]+)\" checked='checked'/", (string) ob_get_clean(), $matches );

		expect( $matches[1] )->toBe( $ticked );
	}
)->with(
	array(
		'the min chosen'                    => array( 'distance,min', array( 'distance', 'min' ) ),
		// Saved with nothing ticked: the maps show the defaults, so say so.
		'nothing chosen, so the defaults'   => array( '', array( 'distance', 'gain', 'loss', 'max', 'waypoints' ) ),
	)
);

// The editor reads the list on every request, so a slip here must not take
// the site down.
test(
	'a filter that returns something other than a list is ignored',
	function ( $returned ) {
		$GLOBALS['gpxrm_test_filters']['gpxrm_stat_fields'] = array(
			static function () use ( $returned ) {
				return $returned;
			},
		);

		expect( Renderer::default_stat_fields() )->toBe( Renderer::DEFAULT_STAT_FIELDS );
	}
)->with(
	array(
		'a comma separated string' => array( 'distance,min' ),
		'nothing'                  => array( null ),
	)
);

test(
	'a stored list that is not a string means the defaults',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_stat_fields'] = 42;

		expect( Renderer::default_stat_fields() )->toBe( Renderer::DEFAULT_STAT_FIELDS );
	}
);

test(
	'the editor gets the stats the site shows',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_stat_fields'] = 'max,min';

		expect( Gpxrm\Plugin::editor_defaults()['statFields'] )->toBe( array( 'max', 'min' ) );
	}
);
