<?php
/**
 * How the Waypoint box saves a post's coordinates.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Waypoint_Meta_Box;
use Gpxrm\Waypoints;

/**
 * The form as the box sends it.
 *
 * @param array<string, string> $changes Fields that differ, or to add.
 * @return array<string, string>
 */
function gpxrm_waypoint_form( array $changes = array() ): array {
	return $changes + array(
		Waypoints::NONCE => 'valid',
		'post_ID'        => '7',
	);
}

/**
 * The post's coordinates as stored, without what the box's other fields store.
 *
 * @return array<string, mixed>
 */
function gpxrm_saved_coordinates(): array {
	return array_intersect_key(
		$GLOBALS['gpxrm_test_meta'][7] ?? array(),
		array_flip( array( Waypoints::META_LAT, Waypoints::META_LON ) )
	);
}

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_meta']    = array();
		$GLOBALS['gpxrm_test_filters'] = array();
		$GLOBALS['gpxrm_test_caps']    = array( 'edit_post' );

		$GLOBALS['gpxrm_test_meta'][7] = array(
			Waypoints::META_LAT => '1.5',
			Waypoints::META_LON => '2.5',
		);
	}
);

afterEach(
	function () {
		$GLOBALS['gpxrm_test_caps'] = array();
		$_POST                      = array();
	}
);

test(
	'the box stores coordinates as it reads them',
	function () {
		$_POST = gpxrm_waypoint_form(
			array(
				'gpxrm_lat' => '50,75',
				'gpxrm_lon' => '5.930',
			)
		);

		( new Waypoint_Meta_Box() )->save( 7 );

		expect( gpxrm_saved_coordinates() )->toBe(
			array(
				Waypoints::META_LAT => '50.75',
				Waypoints::META_LON => '5.93',
			)
		);
		expect( $GLOBALS['gpxrm_test_filters'] )->not->toHaveKey( 'redirect_post_location' );
	}
);

test(
	'both fields emptied clear the coordinates',
	function () {
		$_POST = gpxrm_waypoint_form(
			array(
				'gpxrm_lat' => '',
				'gpxrm_lon' => ' ',
			)
		);

		( new Waypoint_Meta_Box() )->save( 7 );

		expect( gpxrm_saved_coordinates() )->toBe( array() );
	}
);

test(
	'a form that has no such fields leaves the coordinates alone',
	function () {
		$_POST = gpxrm_waypoint_form();

		( new Waypoint_Meta_Box() )->save( 7 );

		expect( gpxrm_saved_coordinates() )->toBe(
			array(
				Waypoints::META_LAT => '1.5',
				Waypoints::META_LON => '2.5',
			)
		);
		expect( $GLOBALS['gpxrm_test_filters'] )->not->toHaveKey( 'redirect_post_location' );
	}
);

test(
	'coordinates it cannot read keep the stored ones, and say so',
	function ( array $fields ) {
		$_POST = gpxrm_waypoint_form( $fields );

		( new Waypoint_Meta_Box() )->save( 7 );

		expect( gpxrm_saved_coordinates() )->toBe(
			array(
				Waypoints::META_LAT => '1.5',
				Waypoints::META_LON => '2.5',
			)
		);
		expect( apply_filters( 'redirect_post_location', 'post.php?post=7' ) )->toBe( 'post.php?post=7&gpxrm_coords=invalid' );
	}
)->with(
	array(
		'past the pole'    => array(
			array(
				'gpxrm_lat' => '95',
				'gpxrm_lon' => '5.93',
			),
		),
		'one left empty'   => array(
			array(
				'gpxrm_lat' => '50.75',
				'gpxrm_lon' => '',
			),
		),
		'one field posted' => array( array( 'gpxrm_lat' => '50.75' ) ),
		'not text'         => array(
			array(
				'gpxrm_lat' => 'north',
				'gpxrm_lon' => 'east',
			),
		),
	)
);

// A plugin saving another waypoint while this form's request runs, which
// would give that post this form's fields.
test(
	'the box saves only the post it was sent for',
	function () {
		$_POST = gpxrm_waypoint_form(
			array(
				'post_ID'   => '8',
				'gpxrm_lat' => '50.75',
				'gpxrm_lon' => '5.93',
			)
		);

		( new Waypoint_Meta_Box() )->save( 7 );

		expect( gpxrm_saved_coordinates() )->toBe(
			array(
				Waypoints::META_LAT => '1.5',
				Waypoints::META_LON => '2.5',
			)
		);
	}
);

test(
	'the box saves nothing for a user who cannot edit the post',
	function () {
		$GLOBALS['gpxrm_test_caps'] = array();

		$_POST = gpxrm_waypoint_form(
			array(
				'gpxrm_lat' => '50.75',
				'gpxrm_lon' => '5.93',
			)
		);

		( new Waypoint_Meta_Box() )->save( 7 );

		expect( gpxrm_saved_coordinates()[ Waypoints::META_LAT ] )->toBe( '1.5' );
	}
);

test(
	'the notice leaves the address once it has shown',
	function () {
		expect( ( new Waypoint_Meta_Box() )->removable_args( array( 'message' ) ) )->toBe( array( 'message', 'gpxrm_coords' ) );
	}
);
