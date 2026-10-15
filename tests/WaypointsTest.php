<?php
/**
 * Waypoint posts: their settings, the links between a map's waypoints and
 * the posts, and what a map is told about them.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Dashicons;
use Gpxrm\Plugin;
use Gpxrm\Renderer;
use Gpxrm\Waypoints;

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options'] = array();
		$GLOBALS['gpxrm_test_filters'] = array();
	}
);

/**
 * Write a GPX file to a temp file and return its path.
 *
 * @param string $waypoints The file's <wpt> elements, and anything else.
 * @return string
 */
function gpxrm_waypoint_gpx( string $waypoints ): string {
	$path = (string) tempnam( sys_get_temp_dir(), 'gpxrm' );
	file_put_contents(
		$path,
		'<?xml version="1.0" encoding="UTF-8"?>' . "\n"
		. '<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1" xmlns:gpxrm="' . Waypoints::GPX_NAMESPACE . '">'
		. $waypoints
		. '<trk><trkseg><trkpt lat="43.0" lon="-6.0"/><trkpt lat="43.1" lon="-6.0"/></trkseg></trk>'
		. '</gpx>'
	);

	return $path;
}

/**
 * The links a GPX file holds, read from a temp file.
 *
 * @param string $waypoints The file's <wpt> elements.
 * @return array<int, int>
 */
function gpxrm_links_in( string $waypoints ): array {
	$path  = gpxrm_waypoint_gpx( $waypoints );
	$links = Waypoints::links_in_gpx( $path );
	unlink( $path );

	return $links;
}

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
	'Waypoint posts are off until the site turns them on',
	function () {
		expect( Waypoints::enabled() )->toBeFalse();
		expect( Plugin::editor_defaults()['waypoints'] )->toBeFalse();

		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = '1';
		expect( Waypoints::enabled() )->toBeTrue();
		expect( Plugin::editor_defaults()['waypoints'] )->toBeTrue();
	}
);

test(
	'only a stored true turns them on',
	function ( $stored, bool $on ) {
		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = $stored;
		expect( Waypoints::enabled() )->toBe( $on );
	}
)->with(
	array(
		'true'          => array( true, true ),
		'1'             => array( 1, true ),
		"'1'"           => array( '1', true ),
		'false'         => array( false, false ),
		"''"            => array( '', false ),
		"'0'"           => array( '0', false ),
		"'yes'"         => array( 'yes', false ),
		'a list'        => array( array( 1 ), false ),
		'not saved yet' => array( null, false ),
	)
);

test(
	'the setting keeps a ticked box as on and anything else as off',
	function ( $submitted, bool $on ) {
		expect( ( new Plugin() )->sanitize_waypoints( $submitted ) )->toBe( $on );
	}
)->with(
	array(
		'on'     => array( 'on', true ),
		'1'      => array( '1', true ),
		'true'   => array( true, true ),
		'absent' => array( null, false ),
		"''"     => array( '', false ),
		'off'    => array( 'off', false ),
		'a list' => array( array( 'on' ), false ),
	)
);

test(
	'a waypoint keeps only settings the map can use',
	function () {
		expect(
			Waypoints::sanitize_fields(
				array(
					'description' => "  Cold water\nall year  ",
					'marker'      => 'icon',
					'icon'        => 'flag',
					'color'       => '#FF0000',
					'background'  => ' #00f ',
				)
			)
		)->toBe(
			array(
				'description' => "Cold water\nall year",
				'marker'      => 'icon',
				'icon'        => 'flag',
				'color'       => '#ff0000',
				'background'  => '#0000ff',
			)
		);
	}
);

// So a map draws its own colours, which may one day follow the site or theme.
test(
	'a waypoint keeps a colour left at its default as none',
	function () {
		$fields = Waypoints::sanitize_fields(
			array(
				'marker'     => 'icon',
				'icon'       => 'flag',
				'color'      => '#FFF',
				'background' => strtoupper( Waypoints::DEFAULT_BACKGROUND ),
			)
		);
		expect( $fields['color'] )->toBe( '' );
		expect( $fields['background'] )->toBe( '' );
	}
);

test(
	'a waypoint drops a marker, icon or colour it does not know',
	function () {
		expect(
			Waypoints::sanitize_fields(
				array(
					'description' => array( 'not text' ),
					'marker'      => 'balloon',
					'icon'        => 'not-an-icon',
					'color'       => 'red',
					'background'  => '#12345',
				)
			)
		)->toBe(
			array(
				'description' => '',
				'marker'      => '',
				'icon'        => '',
				'color'       => '',
				'background'  => '',
			)
		);
	}
);

// The map shows it as text, so nothing in it is a tag.
test(
	'a description keeps what looks like a tag',
	function () {
		expect( Waypoints::sanitize_description( "Gradient <10% here.\r\nViews >180 degrees, <b>not bold</b> & more." ) )
			->toBe( "Gradient <10% here.\nViews >180 degrees, <b>not bold</b> & more." );
	}
);

test(
	'a description drops control characters and invalid text',
	function () {
		expect( Waypoints::sanitize_description( "  One\x00\x07 line\rtwo\ttabbed  " ) )->toBe( "One line\ntwo\ttabbed" );
		expect( Waypoints::sanitize_description( "bad \xC3\x28 bytes" ) )->toBe( '' );
	}
);

test(
	'an icon marker always has an icon',
	function () {
		$fields = Waypoints::sanitize_fields(
			array(
				'marker' => 'icon',
				'icon'   => 'not-an-icon',
			)
		);
		expect( $fields['icon'] )->toBe( Waypoints::DEFAULT_ICON );
	}
);

test(
	'the icon list holds only names a class can take',
	function () {
		expect( Dashicons::NAMES )->not->toBeEmpty();
		expect( count( array_unique( Dashicons::NAMES ) ) )->toBe( count( Dashicons::NAMES ) );
		foreach ( Dashicons::NAMES as $name ) {
			expect( preg_match( '/^[a-z0-9-]+$/', $name ) )->toBe( 1, $name );
		}
		expect( Dashicons::exists( Waypoints::DEFAULT_ICON ) )->toBeTrue();
		expect( Dashicons::exists( 'location' ) )->toBeTrue();
		expect( Dashicons::exists( 'dashicons-location' ) )->toBeFalse();
		expect( Dashicons::exists( 3 ) )->toBeFalse();
	}
);

test(
	'colours are #rgb or #rrggbb, in lower case',
	function ( $value, string $want ) {
		expect( Waypoints::hex_color( $value, 'fallback' ) )->toBe( $want );
	}
)->with(
	array(
		'short'          => array( '#ABC', '#aabbcc' ),
		'long'           => array( '#A1B2C3', '#a1b2c3' ),
		'padded'         => array( " #fff\n", '#ffffff' ),
		'no hash'        => array( 'ffffff', 'fallback' ),
		'four digits'    => array( '#ffff', 'fallback' ),
		'a name'         => array( 'red', 'fallback' ),
		'css after it'   => array( '#fff; background: url(x)', 'fallback' ),
		'not text'       => array( 255, 'fallback' ),
		'nothing at all' => array( '', 'fallback' ),
	)
);

test(
	'post IDs are whole numbers above zero',
	function ( $value, int $want ) {
		expect( Waypoints::post_id( $value ) )->toBe( $want );
	}
)->with(
	array(
		'a number'      => array( 12, 12 ),
		'text'          => array( '12', 12 ),
		'padded'        => array( " 12\n", 12 ),
		'negative'      => array( -3, 0 ),
		'negative text' => array( '-3', 0 ),
		'a fraction'    => array( '1.5', 0 ),
		'a float'       => array( 1.0, 0 ),
		'a word'        => array( 'twelve', 0 ),
		'too long'      => array( '1234567890123456', 0 ),
		'nothing'       => array( null, 0 ),
	)
);

test(
	'a block links waypoints by their place in the file, counting from 0',
	function () {
		expect(
			Waypoints::links_from_attribute(
				array(
					'3'   => 40,
					'0'   => '12',
					'-1'  => 5,
					'x'   => 6,
					'1.5' => 7,
					'2'   => 0,
					'4'   => 'none',
				)
			)
		)->toBe(
			array(
				0 => 12,
				3 => 40,
			)
		);
		// As PHP writes {"0":12,"1":40} when it saves a post for a user
		// without unfiltered_html.
		expect( Waypoints::links_from_attribute( array( 12, 40 ) ) )->toBe(
			array(
				0 => 12,
				1 => 40,
			)
		);
		expect( Waypoints::links_from_attribute( 'not a list' ) )->toBe( array() );
		expect( Waypoints::links_from_attribute( null ) )->toBe( array() );
	}
);

test(
	'a block links no more waypoints than the cap',
	function () {
		$raw = array_fill( 0, Waypoints::MAX_LINKS + 5, 9 );
		expect( Waypoints::links_from_attribute( $raw ) )->toHaveCount( Waypoints::MAX_LINKS );
	}
);

test(
	'a shortcode links waypoints by number, counting from 1',
	function () {
		expect( Waypoints::links_from_shortcode( '1:12, 3:40' ) )->toBe(
			array(
				0 => 12,
				2 => 40,
			)
		);
		expect( Waypoints::links_from_shortcode( '0:5, 2, 3:x, a:4, 4:5:6, , 2:7' ) )->toBe( array( 1 => 7 ) );
		expect( Waypoints::links_from_shortcode( '' ) )->toBe( array() );
	}
);

test(
	'the shortcode takes a waypoints attribute, empty by default',
	function () {
		expect( Plugin::shortcode_defaults()['waypoints'] )->toBe( '' );
	}
);

test(
	'a GPX file links a waypoint by the post ID in its extensions',
	function () {
		$links = gpxrm_links_in(
			'<wpt lat="43.0" lon="-6.0"><name>A</name></wpt>'
			. '<wpt lat="43.1" lon="-6.0"><extensions><gpxrm:waypoint>12</gpxrm:waypoint></extensions></wpt>'
			// Skipped by the map for its coordinates, but still counted.
			. '<wpt lat="x" lon="-6.0"><extensions><gpxrm:waypoint>13</gpxrm:waypoint></extensions></wpt>'
			. '<wpt lat="43.3" lon="-6.0"><extensions><gpxrm:waypoint>' . "\n  40\n" . '</gpxrm:waypoint></extensions></wpt>'
		);
		expect( $links )->toBe(
			array(
				1 => 12,
				2 => 13,
				3 => 40,
			)
		);
	}
);

test(
	'a GPX file links nothing with an element from another namespace or outside a waypoint',
	function () {
		$links = gpxrm_links_in(
			'<wpt lat="43.0" lon="-6.0" xmlns:other="https://example.com/ns"><extensions><other:waypoint>12</other:waypoint></extensions></wpt>'
			. '<wpt lat="43.1" lon="-6.0"><extensions><waypoint>13</waypoint></extensions></wpt>'
			. '<wpt lat="43.2" lon="-6.0"/>'
			. '<extensions><gpxrm:waypoint>14</gpxrm:waypoint></extensions>'
		);
		expect( $links )->toBe( array() );
	}
);

test(
	'a GPX file links a waypoint only by a post ID',
	function () {
		$links = gpxrm_links_in(
			'<wpt lat="43.0" lon="-6.0"><extensions><gpxrm:waypoint>twelve</gpxrm:waypoint></extensions></wpt>'
			. '<wpt lat="43.1" lon="-6.0"><extensions><gpxrm:waypoint>-3</gpxrm:waypoint></extensions></wpt>'
			. '<wpt lat="43.2" lon="-6.0"><extensions><gpxrm:waypoint>0</gpxrm:waypoint><gpxrm:waypoint>7</gpxrm:waypoint></extensions></wpt>'
		);
		// The first element that holds an ID counts, as the map reads it.
		expect( $links )->toBe( array( 2 => 7 ) );
	}
);

test(
	'a broken or missing GPX file links nothing, and reports nothing',
	function () {
		$path = (string) tempnam( sys_get_temp_dir(), 'gpxrm' );
		file_put_contents( $path, '<gpx><wpt lat="1" lon="1"><extensions><gpxrm:waypoint xmlns:gpxrm="' . Waypoints::GPX_NAMESPACE . '">5</gpxrm:waypoint></extensions></wpt><wpt' );
		$links = Waypoints::links_in_gpx( $path );
		unlink( $path );

		// What was read before the break still counts.
		expect( $links )->toBe( array( 0 => 5 ) );
		expect( Waypoints::links_in_gpx( $path ) )->toBe( array() );
		expect( libxml_get_errors() )->toBe( array() );
	}
);

test(
	'a GPX file never loads an external entity',
	function () {
		$secret = (string) tempnam( sys_get_temp_dir(), 'gpxrm' );
		file_put_contents( $secret, '99' );
		$path = (string) tempnam( sys_get_temp_dir(), 'gpxrm' );
		file_put_contents(
			$path,
			'<?xml version="1.0"?><!DOCTYPE gpx [<!ENTITY id SYSTEM "file://' . $secret . '">]>'
			. '<gpx xmlns:gpxrm="' . Waypoints::GPX_NAMESPACE . '"><wpt lat="1" lon="1"><extensions><gpxrm:waypoint>&id;</gpxrm:waypoint></extensions></wpt></gpx>'
		);
		$links = Waypoints::links_in_gpx( $path );
		unlink( $path );
		unlink( $secret );

		expect( $links )->toBe( array() );
	}
);

test(
	'a map is told only what a post changes about its waypoint',
	function () {
		expect( Waypoints::entry( gpxrm_waypoint_settings() ) )->toBe( array() );
		expect(
			Waypoints::entry(
				gpxrm_waypoint_settings(
					array(
						'name' => 'Fountain',
						'desc' => "Cold\nwater",
					)
				)
			)
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
			Waypoints::entry(
				gpxrm_waypoint_settings(
					array(
						'marker' => 'image',
						'photo'  => $photo,
						'thumb'  => 'https://example.test/a-150x150.jpg',
					)
				)
			)
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
		expect( Waypoints::entry( gpxrm_waypoint_settings( array( 'marker' => 'image' ) ) ) )->toBe( array() );
	}
);

test(
	'an icon marker gets an icon, and the colours chosen for it',
	function () {
		expect(
			Waypoints::entry(
				gpxrm_waypoint_settings(
					array(
						'marker' => 'icon',
						'icon'   => 'flag',
						'color'  => '#FF0000',
					)
				)
			)
		)->toBe(
			array(
				'marker' => 'icon',
				'icon'   => 'flag',
				'color'  => '#ff0000',
			)
		);

		$entry = Waypoints::entry(
			gpxrm_waypoint_settings(
				array(
					'marker' => 'icon',
					'icon'   => '" onclick="x',
				)
			)
		);
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
			Waypoints::entry(
				gpxrm_waypoint_settings(
					array(
						'marker' => 'balloon',
						'icon'   => 'flag',
					)
				)
			)
		)->toBe( array() );
	}
);

test(
	'the Dashicons font is needed only for an icon marker',
	function () {
		expect( Waypoints::uses_icons( array() ) )->toBeFalse();
		expect( Waypoints::uses_icons( array( 2 => array( 'marker' => 'image' ) ) ) )->toBeFalse();
		expect( Waypoints::uses_icons( array( 2 => array( 'marker' => 'icon' ) ) ) )->toBeTrue();
	}
);

test(
	'the map is told about waypoints as an object keyed by position',
	function () {
		expect( Waypoints::json( array( 0 => array( 'name' => 'A' ) ) ) )->toBe( '{"0":{"name":"A"}}' );
		expect( Waypoints::json( array() ) )->toBe( '{}' );
		expect( Waypoints::json( array( 0 => array( 'name' => 'Tom & Jerry' ) ) ) )->toBe( '{"0":{"name":"Tom \u0026 Jerry"}}' );
	}
);

test(
	'a map says nothing about waypoints while Waypoint posts are off',
	function () {
		expect( Waypoints::markers_for_map( 5, '', array( 0 => 12 ) ) )->toBe( array() );

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
