<?php
/**
 * What a map is told about the Waypoint posts its waypoints link to.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Dashicons;
use Gpxrm\Plugin;
use Gpxrm\Renderer;
use Gpxrm\Waypoint_Map_Markers;
use Gpxrm\Waypoint_Marker;
use Gpxrm\Waypoints;

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options'] = array();
		$GLOBALS['gpxrm_test_filters'] = array();
		$GLOBALS['gpxrm_test_files']   = array();
		$GLOBALS['gpxrm_test_meta']    = array();
		$GLOBALS['gpxrm_test_posts']   = array();
		$GLOBALS['gpxrm_test_queries'] = array();
	}
);

/**
 * A waypoint's settings as entry() takes them, with some changed.
 *
 * @param array<string, mixed> $changes Settings that differ.
 * @return array{name: string, desc: string, marker: mixed, icon: mixed, color: mixed, background: mixed, photo: array{src: string, width: int, height: int, alt: string, srcset: string}|null, thumb: string}
 */
function gpxrm_waypoint_settings( array $changes = array() ): array {
	/** @var array{name: string, desc: string, marker: mixed, icon: mixed, color: mixed, background: mixed, photo: array{src: string, width: int, height: int, alt: string, srcset: string}|null, thumb: string} */
	return array_merge(
		array(
			'name'       => '',
			'desc'       => '',
			'marker'     => '',
			'icon'       => '',
			'color'      => '',
			'background' => '',
			'photo'      => null,
			'thumb'      => '',
		),
		$changes
	);
}

test(
	'a map is told only what a post changes about its waypoint',
	function () {
		expect( ( new Waypoint_Marker( gpxrm_waypoint_settings() ) )->to_array() )->toBe( array() );
		expect(
			( new Waypoint_Marker(
				gpxrm_waypoint_settings(
					array(
						'name' => 'Fountain',
						'desc' => "Cold\nwater",
					)
				)
			) )->to_array()
		)->toBe(
			array(
				'name' => 'Fountain',
				'desc' => "Cold\nwater",
			)
		);
	}
);

test(
	'a photo marker needs a photo',
	function () {
		$photo = array(
			'src'    => 'https://example.test/a-300x200.jpg',
			'width'  => 300,
			'height' => 200,
			'alt'    => 'A fountain',
			'srcset' => '',
		);

		expect(
			( new Waypoint_Marker(
				gpxrm_waypoint_settings(
					array(
						'marker' => 'image',
						'photo'  => $photo,
						'thumb'  => 'https://example.test/a-150x150.jpg',
					)
				)
			) )->to_array()
		)->toBe(
			array(
				'photo'  => array(
					'src'    => 'https://example.test/a-300x200.jpg',
					'width'  => 300,
					'height' => 200,
					'alt'    => 'A fountain',
				),
				'marker' => 'image',
				'thumb'  => 'https://example.test/a-150x150.jpg',
			)
		);

		// No photo: the waypoint keeps its usual marker.
		expect( ( new Waypoint_Marker( gpxrm_waypoint_settings( array( 'marker' => 'image' ) ) ) )->to_array() )->toBe( array() );
	}
);

test(
	'an icon marker gets an icon, and the colours chosen for it',
	function () {
		expect(
			( new Waypoint_Marker(
				gpxrm_waypoint_settings(
					array(
						'marker' => 'icon',
						'icon'   => 'flag',
						'color'  => '#FF0000',
					)
				)
			) )->to_array()
		)->toBe(
			array(
				'marker' => 'icon',
				'icon'   => 'flag',
				'color'  => '#ff0000',
			)
		);

		$entry = ( new Waypoint_Marker(
			gpxrm_waypoint_settings(
				array(
					'marker' => 'icon',
					'icon'   => '" onclick="x',
				)
			)
		) )->to_array();
		expect( $entry )->toBe(
			array(
				'marker' => 'icon',
				'icon'   => Waypoints::DEFAULT_ICON,
			)
		);
	}
);

test(
	'a marker the map does not know is the usual one',
	function () {
		expect(
			( new Waypoint_Marker(
				gpxrm_waypoint_settings(
					array(
						'marker' => 'balloon',
						'icon'   => 'flag',
					)
				)
			) )->to_array()
		)->toBe( array() );
	}
);

test(
	'the Dashicons font is needed only for an icon marker',
	function () {
		expect( ( new Waypoint_Map_Markers( array() ) )->uses_icons() )->toBeFalse();
		expect( ( new Waypoint_Map_Markers( array( 2 => array( 'marker' => 'image' ) ) ) )->uses_icons() )->toBeFalse();
		expect( ( new Waypoint_Map_Markers( array( 2 => array( 'marker' => 'icon' ) ) ) )->uses_icons() )->toBeTrue();
	}
);

test(
	'the map is told about waypoints as an object keyed by position',
	function () {
		expect( ( new Waypoint_Map_Markers( array( 0 => array( 'name' => 'A' ) ) ) )->json() )->toBe( '{"0":{"name":"A"}}' );
		expect( ( new Waypoint_Map_Markers( array() ) )->json() )->toBe( '{}' );
		expect( ( new Waypoint_Map_Markers( array( 0 => array( 'name' => 'Tom & Jerry' ) ) ) )->json() )->toBe( '{"0":{"name":"Tom \u0026 Jerry"}}' );
	}
);

test(
	'a map says nothing about waypoints while Waypoint posts are off',
	function () {
		expect( Waypoint_Map_Markers::for_map( 5, '', array( 0 => 12 ) )->all() )->toBe( array() );

		$html = Renderer::render(
			array(
				'gpxUrl'        => 'https://example.test/route.gpx',
				'waypointPosts' => array( 0 => 12 ),
			)
		);
		expect( $html )->toContain( 'data-gpxrm-gpx=' );
		expect( $html )->not->toContain( 'data-gpxrm-waypoints' );
	}
);

test(
	'a map that links no waypoint says nothing about them',
	function () {
		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = '1';

		$html = Renderer::render( array( 'gpxUrl' => 'https://example.test/route.gpx' ) );
		expect( $html )->not->toContain( 'data-gpxrm-waypoints' );
		expect( $html )->not->toContain( 'data-gpxrm-added' );
	}
);

/**
 * A published Waypoint post, for the queries a map makes.
 *
 * @param int                  $id   Post ID.
 * @param string               $name Its title.
 * @param array<string, mixed> $meta Its meta.
 * @param string               $pass Its password.
 * @return void
 */
function gpxrm_waypoint_post( int $id, string $name, array $meta = array(), string $pass = '' ): void {
	$GLOBALS['gpxrm_test_posts'][ $id ] = new WP_Post( $id, $name, $pass );
	$GLOBALS['gpxrm_test_meta'][ $id ]  = $meta;
}

/**
 * Meta holding coordinates.
 *
 * @param string $lat Latitude.
 * @param string $lon Longitude.
 * @return array<string, string>
 */
function gpxrm_at( string $lat, string $lon ): array {
	return array(
		Waypoints::META_LAT => $lat,
		Waypoints::META_LON => $lon,
	);
}

test(
	'a map is told where the posts added to it stand, in the order they were added',
	function () {
		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = '1';
		gpxrm_waypoint_post( 40, 'Refuge', gpxrm_at( '43.1', '-6' ) );
		gpxrm_waypoint_post( 49, 'Summit', gpxrm_at( '50.75', '5.93' ) );

		expect( Waypoint_Map_Markers::for_map( 0, '', array(), array( 49, 40 ) )->added() )->toBe(
			array(
				array(
					'lat'  => 50.75,
					'lon'  => 5.93,
					'name' => 'Summit',
				),
				array(
					'lat'  => 43.1,
					'lon'  => -6.0,
					'name' => 'Refuge',
				),
			)
		);
	}
);

test(
	'an added post keeps coordinates of zero',
	function () {
		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = '1';
		gpxrm_waypoint_post( 49, 'Null Island', gpxrm_at( '0', '0' ) );

		expect( Waypoint_Map_Markers::for_map( 0, '', array(), array( 49 ) )->added() )->toBe(
			array(
				array(
					'lat'  => 0.0,
					'lon'  => 0.0,
					'name' => 'Null Island',
				),
			)
		);
	}
);

// The map works out what to draw, as only it knows which waypoints of the
// file are on it.
test(
	'an added post says which waypoints of the file also link to it',
	function () {
		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = '1';
		gpxrm_waypoint_post( 49, 'Summit', gpxrm_at( '50.75', '5.93' ) );

		$added = Waypoint_Map_Markers::for_map(
			0,
			'',
			array(
				1 => 49,
				3 => 49,
			),
			array( 49 )
		)->added();

		expect( $added[0]['at'] ?? null )->toBe( array( 1, 3 ) );
	}
);

test(
	'an added post that lost its title still has a name to show',
	function () {
		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = '1';
		gpxrm_waypoint_post( 49, '', gpxrm_at( '50.75', '5.93' ) );

		$added = Waypoint_Map_Markers::for_map( 0, '', array(), array( 49 ) )->added();

		expect( $added[0]['name'] )->toBe( 'Waypoint' );
	}
);

test(
	'a map is told only about added posts it may show',
	function () {
		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = '1';
		gpxrm_waypoint_post( 40, 'No coordinates' );
		gpxrm_waypoint_post( 41, 'Half coordinates', array( Waypoints::META_LAT => '50.75' ) );

		$markers = Waypoint_Map_Markers::for_map( 0, '', array(), array( 40, 41, 42 ) );

		expect( $markers->added() )->toBe( array() );
		expect(
			Renderer::render(
				array(
					'gpxUrl'         => 'https://example.test/route.gpx',
					'addedWaypoints' => array( 40, 41, 42 ),
				)
			)
		)->not->toContain( 'data-gpxrm-added' );
	}
);

test(
	'a map says nothing about added posts while Waypoint posts are off',
	function () {
		gpxrm_waypoint_post( 49, 'Summit', gpxrm_at( '50.75', '5.93' ) );

		expect( Waypoint_Map_Markers::for_map( 0, '', array(), array( 49 ) )->added() )->toBe( array() );
		expect(
			Renderer::render(
				array(
					'gpxUrl'         => 'https://example.test/route.gpx',
					'addedWaypoints' => array( 49 ),
				)
			)
		)->not->toContain( 'data-gpxrm-added' );
	}
);

test(
	'one map asks for its linked and added posts together',
	function () {
		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = '1';
		gpxrm_waypoint_post( 40, 'Refuge' );
		gpxrm_waypoint_post( 49, 'Summit', gpxrm_at( '50.75', '5.93' ) );

		$markers = Waypoint_Map_Markers::for_map( 0, '', array( 0 => 40 ), array( 49 ) );

		expect( $GLOBALS['gpxrm_test_queries'] )->toBe( array( array( 40, 49 ) ) );
		expect( $markers->all() )->toBe( array( 0 => array( 'name' => 'Refuge' ) ) );
		expect( $markers->added() )->toHaveCount( 1 );
	}
);

test(
	'the map is told about added posts as a list, kept safe for an attribute',
	function () {
		expect(
			( new Waypoint_Map_Markers(
				array(),
				array(
					array(
						'name' => 'Tom & Jerry',
						'lat'  => 50.75,
						'lon'  => 0.0,
					),
				)
			) )->added_json()
		)->toBe( '[{"name":"Tom \u0026 Jerry","lat":50.75,"lon":0}]' );
		expect( ( new Waypoint_Map_Markers( array() ) )->added_json() )->toBe( '[]' );
	}
);

test(
	'the Dashicons font is needed for an added post with an icon marker',
	function () {
		expect(
			( new Waypoint_Map_Markers(
				array(),
				array(
					array(
						'name'   => 'A',
						'lat'    => 1.0,
						'lon'    => 2.0,
						'marker' => 'icon',
					),
				)
			) )->uses_icons()
		)->toBeTrue();
	}
);

test(
	'only added posts no waypoint of the file links to add to the count',
	function () {
		$place = array(
			'name' => 'A',
			'lat'  => 1.0,
			'lon'  => 2.0,
		);

		expect( ( new Waypoint_Map_Markers( array() ) )->added_count() )->toBe( 0 );
		expect(
			( new Waypoint_Map_Markers(
				array(),
				array(
					$place,
					$place + array( 'at' => array( 2 ) ),
					$place,
				)
			) )->added_count()
		)->toBe( 2 );
	}
);

test(
	'a map carries its added posts, and the bar counts them',
	function () {
		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = '1';
		gpxrm_waypoint_post( 49, 'Summit', gpxrm_at( '50.75', '5.93' ) );

		$html = Renderer::render(
			array(
				'gpxUrl'         => 'https://example.test/route.gpx',
				'addedWaypoints' => array( 49 ),
				'showStats'      => 'show',
				'statFields'     => 'waypoints',
				'stats'          => array(
					'distance'  => 1.5,
					'gain'      => 20,
					'loss'      => 10,
					'max'       => 120,
					'waypoints' => 3,
				),
			)
		);

		expect( $html )->toContain( 'data-gpxrm-added="[{&quot;lat&quot;:50.75,&quot;lon&quot;:5.93,&quot;name&quot;:&quot;Summit&quot;}]"' );
		expect( $html )->toContain( 'data-gpxrm-stat="waypoints">4<' );
	}
);

test(
	'a shortcode adds posts to its map',
	function () {
		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = '1';
		gpxrm_waypoint_post( 49, 'Summit', gpxrm_at( '50.75', '5.93' ) );

		$html = ( new Plugin() )->render_shortcode(
			array(
				'gpx'       => 'https://example.test/route.gpx',
				'waypoints' => '@49',
			)
		);

		expect( $html )->toContain( 'data-gpxrm-added=' );
	}
);
