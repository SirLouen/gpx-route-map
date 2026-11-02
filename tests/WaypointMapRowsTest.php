<?php
/**
 * The rows of a Waypoint's Maps box: every map that shows it, and only what
 * the user may see.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Waypoint_Map_Rows;
use Gpxrm\Waypoint_Maps_Box;

/**
 * A block map of the file with ID 5.
 *
 * @param string $attributes More attributes, as JSON, with a leading comma.
 * @return string
 */
function gpxrm_block_map( string $attributes = '' ): string {
	return '<!-- wp:gpx-route-map/map {"gpxId":5' . $attributes . '} /-->';
}

/**
 * The rows for Waypoint 12, from posts as post ID => content.
 *
 * @param array<int, string> $contents Posts.
 * @return array{rows: array<int, array<string, mixed>>, hidden: int, unreadable: int}
 */
function gpxrm_rows( array $contents ): array {
	return Waypoint_Map_Rows::from_contents( 12, $contents );
}

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_attachments'] = array( 5 => 'https://example.test/uploads/route.gpx' );
		$GLOBALS['gpxrm_test_files']       = array();
		$GLOBALS['gpxrm_test_meta']        = array();
		$GLOBALS['gpxrm_test_primed']      = array();
		$GLOBALS['gpxrm_test_caps']        = array( 'edit_post:100' );
		$GLOBALS['gpxrm_test_posts']       = array( 100 => new WP_Post( 100, 'Host' ) );
	}
);

afterEach(
	function () {
		$GLOBALS['gpxrm_test_caps'] = array();
	}
);

test(
	'a map linking the waypoint from the block gives a row for each place',
	function () {
		$found = gpxrm_rows( array( 100 => gpxrm_block_map( ',"waypointPosts":{"0":12,"3":12,"4":77}' ) ) );

		expect( array_column( $found['rows'], 'where' ) )->toBe( array( 'own', 'own' ) );
		expect( array_column( $found['rows'], 'position' ) )->toBe( array( 0, 3 ) );
		expect( $found['rows'][0]['host'] )->toBe( 100 );
		expect( $found['rows'][0]['file'] )->toBe( 'https://example.test/uploads/route.gpx' );
	}
);

test(
	'links stored as a list are links all the same',
	function () {
		$found = gpxrm_rows( array( 100 => gpxrm_block_map( ',"waypointPosts":[12]' ) ) );

		expect( array_column( $found['rows'], 'position' ) )->toBe( array( 0 ) );
	}
);

test(
	'a post added to a map gives a row, as well as any link to it',
	function () {
		$found = gpxrm_rows( array( 100 => gpxrm_block_map( ',"waypointPosts":{"1":12},"addedWaypoints":[12]' ) ) );

		expect( array_column( $found['rows'], 'where' ) )->toBe( array( 'own', 'added' ) );
	}
);

test(
	'a map linking the waypoint from a shortcode gives a row',
	function () {
		$found = gpxrm_rows( array( 100 => '[gpx_route_map id="5" waypoints="3:12"]' ) );

		expect( array_column( $found['rows'], 'position' ) )->toBe( array( 2 ) );
		expect( array_column( $found['rows'], 'where' ) )->toBe( array( 'own' ) );
	}
);

test(
	'a link in the file gives a row, unless the map overrides it',
	function () {
		$GLOBALS['gpxrm_test_files'][5] = gpxrm_waypoint_gpx(
			'<wpt lat="43.0" lon="-6.0"><extensions><gpxrm:waypoint>12</gpxrm:waypoint></extensions></wpt>'
		);

		$found = gpxrm_rows(
			array(
				100 => gpxrm_block_map(),
				101 => gpxrm_block_map( ',"waypointPosts":{"0":77}' ),
			)
		);

		unlink( $GLOBALS['gpxrm_test_files'][5] );

		expect( array_column( $found['rows'], 'host' ) )->toBe( array( 100 ) );
		expect( $found['rows'][0]['where'] )->toBe( 'file' );
	}
);

test(
	'a map without a file shows nothing, so it gives no row',
	function () {
		$found = gpxrm_rows( array( 100 => '<!-- wp:gpx-route-map/map {"waypointPosts":{"0":12}} /-->' ) );

		expect( $found['rows'] )->toBe( array() );
	}
);

test(
	'each row says which of a post\'s maps it is, across both kinds',
	function () {
		$found = gpxrm_rows( array( 100 => '[gpx_route_map id=5]' . gpxrm_block_map() . gpxrm_block_map( ',"addedWaypoints":[12]' ) ) );

		expect( $found['rows'][0]['number'] )->toBe( 3 );
		expect( $found['rows'][0]['of'] )->toBe( 3 );
	}
);

test(
	'a user who can edit a post gets its rows, marked so',
	function () {
		$found = gpxrm_rows( array( 100 => gpxrm_block_map( ',"addedWaypoints":[12]' ) ) );

		expect( $found['rows'][0]['can_edit'] )->toBeTrue();
		expect( $found['hidden'] )->toBe( 0 );
	}
);

test(
	'a user who can only read a post gets its rows, marked so',
	function () {
		$GLOBALS['gpxrm_test_caps'] = array( 'read_post:100' );

		$found = gpxrm_rows( array( 100 => gpxrm_block_map( ',"addedWaypoints":[12]' ) ) );

		expect( $found['rows'][0]['can_edit'] )->toBeFalse();
	}
);

// The scan goes straight to the database, so it ignores read permission on
// its own. Without this check a private post's title would show.
test(
	'a post the user cannot read gives no row, only a count of its maps',
	function () {
		$GLOBALS['gpxrm_test_caps'] = array();

		$found = gpxrm_rows( array( 100 => gpxrm_block_map( ',"waypointPosts":{"0":12,"1":12}' ) . gpxrm_block_map( ',"addedWaypoints":[12]' ) ) );

		expect( $found['rows'] )->toBe( array() );
		expect( $found['hidden'] )->toBe( 2 );
	}
);

test(
	'only posts with a row are primed',
	function () {
		gpxrm_rows(
			array(
				100 => gpxrm_block_map( ',"addedWaypoints":[12]' ),
				101 => gpxrm_block_map(),
			)
		);

		expect( $GLOBALS['gpxrm_test_primed'] )->toBe( array( 100 ) );
	}
);

test(
	'a post that cannot be read is counted, not taken for one without maps',
	function () {
		$found = Waypoint_Map_Rows::from_contents(
			12,
			array( 100 => '[gpx_route_map id=5]' ),
			new class() extends Gpxrm\Post_Maps {
				/**
				 * As when the content is too long for the pattern.
				 *
				 * @param string $text Ignored.
				 * @return array<int, array<array{0: string, 1: int}>>|null
				 */
				protected function shortcode_matches( string $text ): ?array {
					unset( $text );
					return null;
				}
			}
		);

		expect( $found['unreadable'] )->toBe( 1 );
	}
);

test(
	'rows come a page at a time, and a page past the end is the last',
	function ( int $asked, int $first, int $page ) {
		$rows = array_fill( 0, Waypoint_Map_Rows::PER_PAGE + 1, array() );
		foreach ( array_keys( $rows ) as $index ) {
			$rows[ $index ] = array( 'position' => $index );
		}

		$part = Waypoint_Map_Rows::page( $rows, $asked );

		expect( $part['rows'][0]['position'] )->toBe( $first );
		expect( $part['page'] )->toBe( $page );
		expect( $part['pages'] )->toBe( 2 );
	}
)->with(
	array(
		'the first'     => array( 1, 0, 1 ),
		'the second'    => array( 2, Waypoint_Map_Rows::PER_PAGE, 2 ),
		'past the end'  => array( 9, Waypoint_Map_Rows::PER_PAGE, 2 ),
		'before the start' => array( 0, 0, 1 ),
	)
);

test(
	'no rows is one empty page',
	function () {
		expect( Waypoint_Map_Rows::page( array(), 1 ) )->toBe(
			array(
				'rows'  => array(),
				'page'  => 1,
				'pages' => 1,
			)
		);
	}
);

test(
	'the box says where the waypoint shows',
	function ( string $where, int $position, bool $has_coordinates, string $want ) {
		expect(
			Waypoint_Maps_Box::where(
				array(
					'where'    => $where,
					'position' => $position,
				),
				$has_coordinates
			)
		)->toBe( $want );
	}
)->with(
	array(
		'linked in the block' => array( 'own', 2, true, 'Waypoint 3 of the GPX file' ),
		'linked in the file'  => array( 'file', 0, true, 'Waypoint 1 of the GPX file, linked in the file' ),
		'added'               => array( 'added', -1, true, 'At its own coordinates' ),
		'added, none yet'     => array( 'added', -1, false, 'At its own coordinates, once this waypoint has them' ),
	)
);

/**
 * A row as the box prints it.
 *
 * @param array<string, mixed> $changes What differs from a plain row.
 * @return string
 */
function gpxrm_box_row( array $changes = array() ): string {
	ob_start();
	( new Waypoint_Maps_Box() )->row(
		$changes + array(
			'host'     => 100,
			'number'   => 1,
			'of'       => 1,
			'file'     => 'https://example.test/uploads/route.gpx',
			'where'    => 'own',
			'position' => 0,
			'can_edit' => true,
		),
		true
	);

	return (string) ob_get_clean();
}

test(
	'a post the user can edit links to its editor',
	function () {
		expect( gpxrm_box_row() )->toContain( '<a href="https://example.test/edit/100">Host</a>' );
	}
);

test(
	'a published post the user cannot edit links to its page',
	function () {
		expect( gpxrm_box_row( array( 'can_edit' => false ) ) )->toContain( '<a href="https://example.test/read/100">Host</a>' );
	}
);

test(
	'any other post the user cannot edit is only named',
	function () {
		$GLOBALS['gpxrm_test_posts'][100]->post_status = 'private';

		$html = gpxrm_box_row( array( 'can_edit' => false ) );

		expect( $html )->toContain( 'Host' );
		expect( $html )->not->toContain( '<a ' );
	}
);

test(
	'a row names the post type, its status and the file',
	function () {
		expect( gpxrm_box_row() )->toContain( 'Post · Publish · route.gpx' );
	}
);

test(
	'a row says which map it is only when a post has several',
	function () {
		expect( gpxrm_box_row() )->not->toContain( 'Map 1 of' );
		expect(
			gpxrm_box_row(
				array(
					'number' => 2,
					'of'     => 3,
				)
			)
		)->toContain( 'Map 2 of 3' );
	}
);

test(
	'a map in a synced pattern says it shows wherever the pattern is used',
	function () {
		$GLOBALS['gpxrm_test_posts'][100]->post_type = 'wp_block';

		expect( gpxrm_box_row() )->toContain( 'In a synced pattern, which shows wherever the pattern is used.' );
	}
);
