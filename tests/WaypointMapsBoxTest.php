<?php
/**
 * How a Waypoint's Maps box prints its rows, and what it offers.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Waypoint_Maps_Box;
use Gpxrm\Waypoints;

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
	'moving a link to the waypoint\'s coordinates waits for it to have a place',
	function () {
		expect( gpxrm_box_row( array(), false ) )->toContain( '<option value="own" disabled' );
		expect( gpxrm_box_row() )->not->toContain( '<option value="own" disabled' );
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
	'a waypoint with coordinates can be added to a map, named for the user',
	function () {
		$GLOBALS['gpxrm_test_meta'][12] = array(
			Waypoints::META_LAT => '50.75',
			Waypoints::META_LON => '5.93',
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

// Asking for coordinates first, and an Update to see the list, hid the list
// from anyone who had not found that out.
test(
	'the maps to add a waypoint to show whether or not it has a place yet',
	function () {
		$html = gpxrm_box( array( 'addable' => array( gpxrm_addable_map( 100 ) ) ), new WP_Post( 12, 'Summit' ) );

		expect( $html )->toContain( 'name="gpxrm_maps_add"' );
		expect( $html )->toContain( 'Host · route.gpx' );
	}
);

// A new waypoint has a Publish button, and a draft a Save Draft one.
test(
	'the box names no button a waypoint may not have',
	function () {
		$html = gpxrm_box( array( 'addable' => array( gpxrm_addable_map( 100 ) ) ), new WP_Post( 12, 'Summit' ) );

		expect( $html )->not->toContain( 'Click Update' );
		expect( $html )->toContain( 'when you save' );
	}
);

test(
	'each map to add to says where its route is, for the map above to show',
	function () {
		$html = gpxrm_box( array( 'addable' => array( gpxrm_addable_map( 100 ) ) ), new WP_Post( 12, 'Summit' ) );

		expect( $html )->toContain( 'data-gpxrm-gpx="https://example.test/uploads/route.gpx"' );
	}
);

test(
	'choosing a map without a place has something to ask for it, hidden until then',
	function () {
		$html = gpxrm_box( array( 'addable' => array( gpxrm_addable_map( 100 ) ) ), new WP_Post( 12, 'Summit' ) );

		expect( $html )->toMatch( '/<p class="[^"]*gpxrm-maps-add-place[^"]*" hidden>/' );
	}
);

test(
	'the box holds the map, with the site\'s tiles and the fields the form sends',
	function () {
		$GLOBALS['gpxrm_test_meta'][12] = array(
			Waypoints::META_LAT => '50.75',
			Waypoints::META_LON => '5.93',
		);

		$html = gpxrm_box( array(), new WP_Post( 12, 'Summit' ) );

		expect( $html )->toContain( 'class="gpxrm-picker"' );
		expect( $html )->toContain( 'class="gpxrm-picker-map"' );
		expect( $html )->toContain( 'data-gpxrm-tile-url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"' );
		expect( $html )->toContain( 'data-gpxrm-tile-max-zoom="19"' );
		expect( $html )->toContain( 'data-gpxrm-map-ui=' );
		expect( $html )->toContain( 'name="gpxrm_lat" value="50.75"' );
		expect( $html )->toContain( 'name="gpxrm_lon" value="5.93"' );
	}
);

// A number field in a browser turns a typed 50,75 into 5075, and calls it valid.
test(
	'the fields take text, so a decimal comma stays one',
	function () {
		$html = gpxrm_box( array(), new WP_Post( 12, 'Summit' ) );

		expect( $html )->toContain( '<input type="text" id="gpxrm_lat"' );
		expect( $html )->toContain( '<input type="text" id="gpxrm_lon"' );
	}
);

test(
	'the words for when the map cannot load wait until then',
	function () {
		expect( gpxrm_box( array(), new WP_Post( 12, 'Summit' ) ) )->toMatch( '/<p class="[^"]*gpxrm-picker-failed[^"]*" hidden>/' );
	}
);

test(
	'a waypoint not saved yet is offered the same as any other',
	function () {
		$GLOBALS['wpdb']                      = new wpdb();
		$GLOBALS['wpdb']->rows                = array( 100 => '<!-- wp:gpx-route-map/map {"gpxId":5} /-->' );
		$GLOBALS['gpxrm_test_attachments'][5] = 'https://example.test/uploads/route.gpx';
		$new_waypoint                         = new WP_Post( 12, '' );
		$new_waypoint->post_status            = 'auto-draft';

		ob_start();
		( new Waypoint_Maps_Box() )->render( $new_waypoint );
		$html = (string) ob_get_clean();

		expect( $html )->toContain( 'name="gpxrm_maps_add"' );
		expect( $html )->toContain( 'class="gpxrm-picker"' );
		expect( $html )->not->toContain( 'Save the waypoint first' );
	}
);

test(
	'a waypoint not saved yet is not told it is unpublished',
	function () {
		$new_waypoint              = new WP_Post( 12, '' );
		$new_waypoint->post_status = 'auto-draft';

		expect( gpxrm_box( array(), $new_waypoint ) )->not->toContain( 'Maps show this waypoint only' );
	}
);

test(
	'a block link that repeats the file\'s link offers nothing that could not work',
	function () {
		$html = gpxrm_box_row( array( 'where' => 'file' ) );

		expect( $html )->toContain( 'Set in the GPX file.' );
		expect( $html )->not->toContain( '<select' );
	}
);
