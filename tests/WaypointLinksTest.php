<?php
/**
 * The links between a map's waypoints and Waypoint posts: from a block, a
 * shortcode or the GPX file itself.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Gpx_File_Links;
use Gpxrm\Gpx_Link_Reader;
use Gpxrm\Plugin;
use Gpxrm\Waypoint_Links;
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
	$links = Gpx_Link_Reader::read( $path );
	unlink( $path );

	return $links;
}

test(
	'post IDs are whole numbers above zero',
	function ( $value, int $want ) {
		expect( Waypoint_Links::post_id( $value ) )->toBe( $want );
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
			Waypoint_Links::from_attribute(
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
		expect( Waypoint_Links::from_attribute( array( 12, 40 ) ) )->toBe(
			array(
				0 => 12,
				1 => 40,
			)
		);
		expect( Waypoint_Links::from_attribute( 'not a list' ) )->toBe( array() );
		expect( Waypoint_Links::from_attribute( null ) )->toBe( array() );
	}
);

test(
	'a block links no more waypoints than the cap',
	function () {
		$raw = array_fill( 0, Waypoints::MAX_LINKS + 5, 9 );
		expect( Waypoint_Links::from_attribute( $raw ) )->toHaveCount( Waypoints::MAX_LINKS );
	}
);

test(
	'a shortcode links waypoints by number, counting from 1',
	function () {
		expect( Waypoint_Links::from_shortcode( '1:12, 3:40' ) )->toBe(
			array(
				0 => 12,
				2 => 40,
			)
		);
		expect( Waypoint_Links::from_shortcode( '0:5, 2, 3:x, a:4, 4:5:6, , 2:7' ) )->toBe( array( 1 => 7 ) );
		expect( Waypoint_Links::from_shortcode( '' ) )->toBe( array() );
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
		$links = Gpx_Link_Reader::read( $path );
		unlink( $path );

		// What was read before the break still counts.
		expect( $links )->toBe( array( 0 => 5 ) );
		// Not read at all, which is not the same as a file without links.
		expect( Gpx_Link_Reader::read( $path ) )->toBeNull();
		expect( libxml_get_errors() )->toBe( array() );
	}
);

test(
	'a GPX attachment is read once, and again only once its file changes',
	function () {
		$path                           = gpxrm_waypoint_gpx( '<wpt lat="43.0" lon="-6.0"><extensions><gpxrm:waypoint>12</gpxrm:waypoint></extensions></wpt>' );
		$GLOBALS['gpxrm_test_files'][5] = $path;

		expect( ( new Gpx_File_Links( 5 ) )->links() )->toBe( array( 0 => 12 ) );

		// The next view takes what was kept, without reading the file.
		$GLOBALS['gpxrm_test_meta'][5][ Waypoints::META_FILE_LINKS ]['links'] = array( 0 => 30 );
		expect( ( new Gpx_File_Links( 5 ) )->links() )->toBe( array( 0 => 30 ) );

		// Another file in its place, of another size.
		rename( gpxrm_waypoint_gpx( '<wpt lat="43.0" lon="-6.0"/><wpt lat="43.1" lon="-6.0"><extensions><gpxrm:waypoint>40</gpxrm:waypoint></extensions></wpt>' ), $path );
		clearstatcache();
		expect( ( new Gpx_File_Links( 5 ) )->links() )->toBe( array( 1 => 40 ) );

		unlink( $path );
	}
);

test(
	'a GPX attachment the site could not read is read again on the next view',
	function () {
		$path                           = gpxrm_waypoint_gpx( '<wpt lat="43.0" lon="-6.0"><extensions><gpxrm:waypoint>12</gpxrm:waypoint></extensions></wpt>' );
		$GLOBALS['gpxrm_test_files'][5] = $path;

		// As on a site whose PHP lacks XMLReader.
		$unreadable = new class( 5 ) extends Gpx_File_Links {
			/**
			 * No file can be read.
			 *
			 * @return array<int, int>|null
			 */
			protected function read(): ?array {
				return null;
			}
		};

		expect( $unreadable->links() )->toBe( array() );
		expect( $GLOBALS['gpxrm_test_meta'] )->toBe( array() );

		// Once it can, such as after the host adds XMLReader.
		expect( ( new Gpx_File_Links( 5 ) )->links() )->toBe( array( 0 => 12 ) );

		unlink( $path );
	}
);

test(
	'the editor is told whether the site reads the links in GPX files',
	function () {
		expect( Plugin::editor_defaults()['waypointFiles'] )->toBe( Gpx_Link_Reader::available() );
		expect( Gpx_Link_Reader::available() )->toBeTrue();
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
		$links = Gpx_Link_Reader::read( $path );
		unlink( $path );
		unlink( $secret );

		expect( $links )->toBe( array() );
	}
);
