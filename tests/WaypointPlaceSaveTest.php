<?php
/**
 * How a Waypoint post's place saves: with the Maps box its fields are in, so
 * that it saves whatever became of the Waypoint box.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Waypoint_Maps_Save;
use Gpxrm\Waypoint_Meta_Box;
use Gpxrm\Waypoints;

/**
 * The form as the Maps box sends it.
 *
 * @param array<string, string> $changes Fields that differ, or to add.
 * @return array<string, string>
 */
function gpxrm_place_form( array $changes = array() ): array {
	return $changes + array(
		Waypoints::MAPS_NONCE => 'valid',
		'post_ID'             => '7',
	);
}

/**
 * The post's coordinates as stored.
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
		$GLOBALS['gpxrm_test_filters'] = array();
		$GLOBALS['gpxrm_test_caps']    = array( 'edit_post' );

		$GLOBALS['gpxrm_test_meta'] = array(
			7 => array(
				Waypoints::META_LAT => '1.5',
				Waypoints::META_LON => '2.5',
			),
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
	'the place is stored as the box reads it',
	function () {
		$_POST = gpxrm_place_form(
			array(
				'gpxrm_lat' => '50,75',
				'gpxrm_lon' => '5.930',
			)
		);

		( new Waypoint_Maps_Save() )->queue( 7 );

		expect( gpxrm_saved_coordinates() )->toBe(
			array(
				Waypoints::META_LAT => '50.75',
				Waypoints::META_LON => '5.93',
			)
		);
		expect( $GLOBALS['gpxrm_test_filters'] )->not->toHaveKey( 'redirect_post_location' );
	}
);

// A site can remove the Waypoint box, whose nonce this form does not carry.
test(
	'the place saves without the Waypoint box',
	function () {
		$_POST = gpxrm_place_form(
			array(
				'gpxrm_lat' => '50.75',
				'gpxrm_lon' => '5.93',
			)
		);

		expect( $_POST )->not->toHaveKey( Waypoints::NONCE );

		( new Waypoint_Maps_Save() )->queue( 7 );

		expect( gpxrm_saved_coordinates()[ Waypoints::META_LAT ] )->toBe( '50.75' );
	}
);

test(
	'both fields emptied clear the place',
	function () {
		$_POST = gpxrm_place_form(
			array(
				'gpxrm_lat' => '',
				'gpxrm_lon' => ' ',
			)
		);

		( new Waypoint_Maps_Save() )->queue( 7 );

		expect( gpxrm_saved_coordinates() )->toBe( array() );
	}
);

test(
	'a form that has no such fields leaves the place alone',
	function () {
		$_POST = gpxrm_place_form();

		( new Waypoint_Maps_Save() )->queue( 7 );

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
	'a place it cannot read keeps the stored one, and says so',
	function ( array $fields ) {
		$_POST = gpxrm_place_form( $fields );

		( new Waypoint_Maps_Save() )->queue( 7 );

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
		'not numbers'      => array(
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
	'the place saves only for the post the form was sent for',
	function () {
		$_POST = gpxrm_place_form(
			array(
				'post_ID'   => '8',
				'gpxrm_lat' => '50.75',
				'gpxrm_lon' => '5.93',
			)
		);

		( new Waypoint_Maps_Save() )->queue( 7 );

		expect( gpxrm_saved_coordinates()[ Waypoints::META_LAT ] )->toBe( '1.5' );
	}
);

test(
	'the place saves nothing for a user who cannot edit the post',
	function () {
		$GLOBALS['gpxrm_test_caps'] = array();

		$_POST = gpxrm_place_form(
			array(
				'gpxrm_lat' => '50.75',
				'gpxrm_lon' => '5.93',
			)
		);

		( new Waypoint_Maps_Save() )->queue( 7 );

		expect( gpxrm_saved_coordinates()[ Waypoints::META_LAT ] )->toBe( '1.5' );
	}
);

test(
	'the place saves nothing without the box\'s own nonce',
	function () {
		$_POST = array(
			Waypoints::NONCE => 'valid',
			'post_ID'        => '7',
			'gpxrm_lat'      => '50.75',
			'gpxrm_lon'      => '5.93',
		);

		( new Waypoint_Maps_Save() )->queue( 7 );

		expect( gpxrm_saved_coordinates()[ Waypoints::META_LAT ] )->toBe( '1.5' );
	}
);

test(
	'the Waypoint box no longer looks after the place',
	function () {
		$_POST = array(
			Waypoints::NONCE => 'valid',
			'post_ID'        => '7',
			'gpxrm_lat'      => '50.75',
			'gpxrm_lon'      => '5.93',
		);

		( new Waypoint_Meta_Box() )->save( 7 );

		expect( gpxrm_saved_coordinates()[ Waypoints::META_LAT ] )->toBe( '1.5' );
	}
);

test(
	'the notice leaves the address once it has shown',
	function () {
		expect( ( new Waypoint_Maps_Save() )->removable_args( array( 'message' ) ) )->toBe( array( 'message', 'gpxrm_coords' ) );
	}
);
