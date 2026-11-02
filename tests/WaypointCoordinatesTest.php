<?php
/**
 * A Waypoint post's coordinates, as an author types them and as a map reads
 * them.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Waypoint_Coordinates;
use Gpxrm\Waypoints;

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_meta'] = array();
	}
);

test(
	'coordinates are stored as plain decimal text',
	function ( string $lat, string $lon, string $want_lat, string $want_lon ) {
		expect( Waypoint_Coordinates::parse( $lat, $lon ) )->toBe(
			array(
				'lat' => $want_lat,
				'lon' => $want_lon,
			)
		);
	}
)->with(
	array(
		'dots'                    => array( '50.75', '5.93', '50.75', '5.93' ),
		'decimal commas'          => array( '50,75', '5,93', '50.75', '5.93' ),
		'spaces and a plus sign'  => array( ' 50.750000 ', '+5.930', '50.75', '5.93' ),
		'a line break'            => array( "50.75\n", '5.93', '50.75', '5.93' ),
		'a space that cannot end' => array( "\xC2\xA050.75\xC2\xA0", '5.93', '50.75', '5.93' ),
		'zero is a place'         => array( '0', '0', '0', '0' ),
		'minus zero'              => array( '0.0', '-0', '0', '0' ),
		'a tiny negative'         => array( '-0.0000001', '0', '-0.0000001', '0' ),
		'a minus sign'            => array( "\xE2\x88\x9233.86", '151.2', '-33.86', '151.2' ),
		'leading zeros'           => array( '005', '-007.5', '5', '-7.5' ),
		'the limits'              => array( '90', '180', '90', '180' ),
		'the other limits'        => array( '-90', '-180', '-90', '-180' ),
		'seven decimals are kept' => array( '50.123456789', '5.123456789', '50.1234567', '5.1234567' ),
	)
);

test(
	'both fields left empty clear the coordinates',
	function () {
		expect( Waypoint_Coordinates::parse( '', ' ' ) )->toBe(
			array(
				'lat' => '',
				'lon' => '',
			)
		);
	}
);

// A cast would read "50,75" as 50.0, about 83 km off, with no error.
test(
	'anything else is not a pair of coordinates',
	function ( string $lat, string $lon ) {
		expect( Waypoint_Coordinates::parse( $lat, $lon ) )->toBeNull();
	}
)->with(
	array(
		'no longitude'             => array( '50.75', '' ),
		'no latitude'              => array( '', '5.93' ),
		'no longitude after zero'  => array( '0', '' ),
		'both in one field'        => array( '50.75, 5.93', '' ),
		'a lone decimal comma'     => array( '50,5', '' ),
		'degrees and minutes'      => array( "50°45'00.0\"N", "5°55'48.0\"E" ),
		'compass letters'          => array( '50.75 N', '5.93 E' ),
		'past the pole'            => array( '91', '0' ),
		'just past the pole'       => array( '90.0000001', '0' ),
		'past the antimeridian'    => array( '0', '180.5' ),
		'an exponent'              => array( '1e1', '5' ),
		'hexadecimal'              => array( '0x1A', '5' ),
		'not numbers'              => array( 'NaN', 'INF' ),
		'digits of another script' => array( '٥٠', '5' ),
		'two decimal marks'        => array( '50.75.1', '5' ),
		'a thousands separator'    => array( '1,234.5', '5' ),
		'markup'                   => array( '<b>50</b>', '5' ),
		'four whole digits'        => array( '1000', '5' ),
	)
);

/**
 * Store a post's coordinates as its meta.
 *
 * @param int   $post_id Post ID.
 * @param mixed $lat     Stored latitude.
 * @param mixed $lon     Stored longitude.
 * @return void
 */
function gpxrm_store_coordinates( int $post_id, $lat, $lon ): void {
	$GLOBALS['gpxrm_test_meta'][ $post_id ] = array(
		Waypoints::META_LAT => $lat,
		Waypoints::META_LON => $lon,
	);
}

test(
	'a post reads its coordinates as numbers',
	function () {
		gpxrm_store_coordinates( 7, '50.75', '-5.93' );

		expect( Waypoint_Coordinates::of( 7 ) )->toBe(
			array(
				'lat' => 50.75,
				'lon' => -5.93,
			)
		);
	}
);

test(
	'coordinates of zero are coordinates',
	function () {
		gpxrm_store_coordinates( 7, '0', '0' );

		expect( Waypoint_Coordinates::of( 7 ) )->toBe(
			array(
				'lat' => 0.0,
				'lon' => 0.0,
			)
		);
	}
);

test(
	'a post has no coordinates unless both stored values are good',
	function ( $lat, $lon ) {
		gpxrm_store_coordinates( 7, $lat, $lon );

		expect( Waypoint_Coordinates::of( 7 ) )->toBeNull();
	}
)->with(
	array(
		'none stored'    => array( '', '' ),
		'one stored'     => array( '50.75', '' ),
		'past the pole'  => array( '95', '5.93' ),
		'not a number'   => array( '50.75', 'east' ),
		'not text'       => array( array( '50.75' ), '5.93' ),
		'a stored float' => array( 50.75, 5.93 ),
	)
);

test(
	'the box shows what is stored, and nothing for a post without coordinates',
	function () {
		expect( Waypoint_Coordinates::stored( 7 ) )->toBe(
			array(
				'lat' => '',
				'lon' => '',
			)
		);

		gpxrm_store_coordinates( 7, '50.75', '5.93' );
		expect( Waypoint_Coordinates::stored( 7 ) )->toBe(
			array(
				'lat' => '50.75',
				'lon' => '5.93',
			)
		);
	}
);
