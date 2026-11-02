<?php
/**
 * What the block editor learns about a Waypoint post's coordinates.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Waypoint_Rest;
use Gpxrm\Waypoints;

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_meta']  = array();
		$GLOBALS['gpxrm_test_posts'] = array( 7 => new WP_Post( 7, 'Summit' ) );
		$GLOBALS['gpxrm_test_caps']  = array( 'edit_posts' );

		$GLOBALS['gpxrm_test_meta'][7] = array(
			Waypoints::META_LAT => '50.75',
			Waypoints::META_LON => '5.93',
		);
	}
);

afterEach(
	function () {
		$GLOBALS['gpxrm_test_caps'] = array();
	}
);

test(
	'someone who can write posts sees a waypoint\'s coordinates',
	function () {
		expect( ( new Waypoint_Rest() )->coords( array( 'id' => 7 ) ) )->toBe(
			array(
				'lat' => 50.75,
				'lon' => 5.93,
			)
		);
	}
);

test(
	'nobody else does',
	function () {
		$GLOBALS['gpxrm_test_caps'] = array();

		expect( ( new Waypoint_Rest() )->coords( array( 'id' => 7 ) ) )->toBeNull();
	}
);

test(
	'a waypoint with a password keeps them to itself',
	function () {
		$GLOBALS['gpxrm_test_posts'][7] = new WP_Post( 7, 'Summit', 'secret' );

		expect( ( new Waypoint_Rest() )->coords( array( 'id' => 7 ) ) )->toBeNull();
	}
);

test(
	'a waypoint without coordinates has none to give',
	function () {
		$GLOBALS['gpxrm_test_meta'][7] = array();

		expect( ( new Waypoint_Rest() )->coords( array( 'id' => 7 ) ) )->toBeNull();
	}
);

test(
	'no post, or none named, has none either',
	function () {
		expect( ( new Waypoint_Rest() )->coords( array( 'id' => 8 ) ) )->toBeNull();
		expect( ( new Waypoint_Rest() )->coords( array() ) )->toBeNull();
	}
);
