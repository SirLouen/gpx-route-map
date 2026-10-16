<?php
/**
 * What a map is told about the Waypoint posts its waypoints link to.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Dashicons;
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
	}
);
