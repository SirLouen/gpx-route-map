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
 * @param array<string, mixed> $changes         What differs from a plain row.
 * @param bool                 $has_coordinates Whether the waypoint has coordinates.
 * @return string
 */
function gpxrm_box_row( array $changes = array(), bool $has_coordinates = true ): string {
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
			'kind'     => 'block',
			'ordinal'  => 2,
			'hash'     => str_repeat( 'c', 40 ),
		),
		$has_coordinates
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

/**
 * The box as it prints what was found for a waypoint.
 *
 * @param array<string, int> $found    How many are hidden and unreadable.
 * @param WP_Post            $waypoint The waypoint.
 * @return string
 */
function gpxrm_box( array $found, WP_Post $waypoint ): string {
	ob_start();
	( new Waypoint_Maps_Box() )->output(
		$found + array(
			'rows'       => array(),
			'hidden'     => 0,
			'unreadable' => 0,
			'addable'    => array(),
		),
		$waypoint
	);

	return (string) ob_get_clean();
}

test(
	'a box with nothing found says so',
	function () {
		expect( gpxrm_box( array(), new WP_Post( 12, 'Summit' ) ) )->toContain( 'No map shows this waypoint yet.' );
	}
);

test(
	'a box does not say no map shows the waypoint when it could not tell',
	function ( array $found ) {
		expect( gpxrm_box( $found, new WP_Post( 12, 'Summit' ) ) )->not->toContain( 'No map shows this waypoint yet.' );
	}
)->with(
	array(
		'a post it could not read'    => array( array( 'unreadable' => 1 ) ),
		'a post the user cannot read' => array( array( 'hidden' => 1 ) ),
	)
);

// A map links a post before it is published, and shows it once it is.
test(
	'a waypoint maps do not show yet says so',
	function ( string $status, string $password ) {
		$waypoint              = new WP_Post( 12, 'Summit', $password );
		$waypoint->post_status = $status;

		expect( gpxrm_box( array(), $waypoint ) )->toContain( 'Maps show this waypoint only once it is published without a password.' );
	}
)->with(
	array(
		'a draft'         => array( 'draft', '' ),
		'pending'         => array( 'pending', '' ),
		'scheduled'       => array( 'future', '' ),
		'private'         => array( 'private', '' ),
		'with a password' => array( 'publish', 'secret' ),
	)
);

test(
	'a published waypoint needs no such word',
	function () {
		expect( gpxrm_box( array(), new WP_Post( 12, 'Summit' ) ) )->not->toContain( 'Maps show this waypoint only' );
	}
);

test(
	'each row says which map it is, by its place among the post\'s blocks and the post\'s hash',
	function () {
		$content = '[gpx_route_map id=5]' . gpxrm_block_map() . gpxrm_block_map( ',"addedWaypoints":[12]' );

		$found = gpxrm_rows( array( 100 => $content ) );

		expect( $found['rows'][0]['kind'] )->toBe( 'block' );
		expect( $found['rows'][0]['ordinal'] )->toBe( 1 );
		expect( $found['rows'][0]['hash'] )->toBe( sha1( $content ) );
	}
);

test(
	'a row in a block offers to change, and says who it is about',
	function () {
		$html = gpxrm_box_row();

		expect( $html )->toContain( 'name="gpxrm_maps[100:b:2:' . str_repeat( 'c', 40 ) . ':p0]"' );
		expect( $html )->toContain( '<option value="keep">Keep</option>' );
		expect( $html )->toContain( '<option value="own">At its own coordinates</option>' );
		expect( $html )->toContain( '<option value="remove">Remove from this map</option>' );
	}
);

test(
	'a waypoint without coordinates cannot be moved to them',
	function () {
		expect( gpxrm_box_row( array(), false ) )->not->toContain( 'value="own"' );
	}
);

test(
	'a post added to a map can only be kept or removed',
	function () {
		$html = gpxrm_box_row(
			array(
				'where'    => 'added',
				'position' => -1,
			)
		);

		expect( $html )->toContain( ':a]"' );
		expect( $html )->toContain( 'value="remove"' );
		expect( $html )->not->toContain( 'value="own"' );
	}
);

test(
	'a row that cannot be changed from here says why, in plain text',
	function ( array $row, string $want ) {
		$html = gpxrm_box_row( $row );

		expect( $html )->toContain( $want );
		expect( $html )->not->toContain( '<select' );
	}
)->with(
	array(
		'a post the user cannot edit' => array( array( 'can_edit' => false ), 'You can&#039;t edit this post.' ),
		'a shortcode'                 => array( array( 'kind' => 'shortcode' ), 'In a shortcode. Edit it in the post.' ),
		'a link in the file'          => array( array( 'where' => 'file' ), 'Set in the GPX file. Override it in the map&#039;s block, or edit the file.' ),
	)
);

test(
	'a map in a synced pattern is changed in the pattern',
	function () {
		$GLOBALS['gpxrm_test_posts'][100]->post_type = 'wp_block';

		$html = gpxrm_box_row();

		expect( $html )->toContain( 'In a synced pattern. Edit it in the pattern.' );
		expect( $html )->not->toContain( '<select' );
	}
);

test(
	'block maps that do not show the waypoint can take it',
	function () {
		$content = '[gpx_route_map id=5]'
			. gpxrm_block_map( ',"height":300' )
			. gpxrm_block_map( ',"addedWaypoints":[12]' )
			. gpxrm_block_map( ',"waypointPosts":{"0":12}' )
			. '<!-- wp:gpx-route-map/map {"height":200} /-->';

		$found = gpxrm_rows( array( 100 => $content ) );

		expect( $found['addable'] )->toBe(
			array(
				array(
					'host'    => 100,
					'ordinal' => 0,
					'hash'    => sha1( $content ),
					'number'  => 2,
					'of'      => 5,
					'file'    => 'https://example.test/uploads/route.gpx',
				),
			)
		);
	}
);

/**
 * A map the box could offer.
 *
 * @param int $host The post.
 * @return array<string, mixed>
 */
function gpxrm_addable_map( int $host ): array {
	return array(
		'host'    => $host,
		'ordinal' => 0,
		'hash'    => str_repeat( 'd', 40 ),
		'number'  => 1,
		'of'      => 1,
		'file'    => 'https://example.test/uploads/route.gpx',
	);
}

test(
	'the box offers maps in posts the user can edit, other than patterns',
	function () {
		$GLOBALS['gpxrm_test_caps']                  = array( 'edit_post:100', 'edit_post:102' );
		$GLOBALS['gpxrm_test_evicted']               = array();
		$GLOBALS['gpxrm_test_posts'][102]            = new WP_Post( 102, 'Pattern' );
		$GLOBALS['gpxrm_test_posts'][102]->post_type = 'wp_block';

		$maps = Waypoint_Map_Rows::addable( array( gpxrm_addable_map( 100 ), gpxrm_addable_map( 101 ), gpxrm_addable_map( 102 ) ) );

		expect( array_column( $maps, 'host' ) )->toBe( array( 100 ) );
	}
);

test(
	'the box lets go of posts it will not offer',
	function () {
		$GLOBALS['gpxrm_test_evicted'] = array();

		Waypoint_Map_Rows::addable( array( gpxrm_addable_map( 100 ), gpxrm_addable_map( 101 ) ) );

		expect( $GLOBALS['gpxrm_test_evicted']['posts'] )->toBe( array( 101 ) );
	}
);

test(
	'the box offers no more maps than it can list',
	function () {
		$GLOBALS['gpxrm_test_caps'] = array( 'edit_post' );

		$maps = array_map( 'gpxrm_addable_map', range( 1, Waypoint_Map_Rows::MAX_ADDABLE + Waypoint_Map_Rows::ADDABLE_CHUNK + 5 ) );

		$GLOBALS['gpxrm_test_primed'] = array();

		expect( Waypoint_Map_Rows::addable( $maps ) )->toHaveCount( Waypoint_Map_Rows::MAX_ADDABLE );
		// It stopped once it had enough.
		expect( $GLOBALS['gpxrm_test_primed'] )->toHaveCount( Waypoint_Map_Rows::ADDABLE_CHUNK );
	}
);

test(
	'a waypoint with coordinates can be added to a map, named for the user',
	function () {
		$GLOBALS['gpxrm_test_meta'][12] = array(
			Gpxrm\Waypoints::META_LAT => '50.75',
			Gpxrm\Waypoints::META_LON => '5.93',
		);

		$map = array(
			'number' => 2,
			'of'     => 3,
		) + gpxrm_addable_map( 100 );

		$html = gpxrm_box( array( 'addable' => array( $map ) ), new WP_Post( 12, 'Summit' ) );

		expect( $html )->toContain( 'name="gpxrm_maps_add"' );
		expect( $html )->toContain( 'value="100:b:0:' . str_repeat( 'd', 40 ) . '"' );
		expect( $html )->toContain( 'Host · route.gpx · Map 2 of 3' );
		expect( $html )->toContain( 'name="gpxrm_maps_nonce"' );
	}
);

test(
	'a waypoint without coordinates is told to get them first',
	function () {
		$html = gpxrm_box( array( 'addable' => array( gpxrm_addable_map( 100 ) ) ), new WP_Post( 12, 'Summit' ) );

		expect( $html )->not->toContain( 'gpxrm_maps_add' );
		expect( $html )->toContain( 'Add coordinates above to add this waypoint to a map from here.' );
	}
);
