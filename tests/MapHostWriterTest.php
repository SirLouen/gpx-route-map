<?php
/**
 * Writing a change to the post that holds a map, against stubs whose save runs
 * the filter a real save runs, over what WordPress's content filters leave of
 * a post. The real thing is tools/verify-map-links.php.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Map_Host_Writer;
use Gpxrm\Waypoints;

const GPXRM_HOST_CONTENT = '<!-- wp:gpx-route-map/map {"gpxId":5,"waypointPosts":{"0":12}} /-->';

/**
 * Write one change to the host.
 *
 * @param array<int, array<string, mixed>> $changes The changes.
 * @param string|null                      $seen    The hash the form holds.
 * @return array<string, mixed>
 */
function gpxrm_write( array $changes = array(), ?string $seen = null ): array {
	return ( new Map_Host_Writer() )->write(
		100,
		$seen ?? sha1( $GLOBALS['gpxrm_test_posts'][100]->post_content ),
		12,
		array() === $changes
			? array(
				array(
					'ordinal'  => 0,
					'op'       => 'remove-position',
					'position' => 0,
				),
			)
			: $changes
	);
}

beforeEach(
	function () {
		$host               = new WP_Post( 100, 'Plain <span>Title</span>' );
		$host->post_content = GPXRM_HOST_CONTENT;
		$host->post_excerpt = 'Excerpt <iframe></iframe>';
		$host->post_name    = 'a-slug';
		$host->post_status  = 'draft';

		$GLOBALS['gpxrm_test_posts']       = array( 100 => $host );
		$GLOBALS['gpxrm_test_filters']     = array();
		$GLOBALS['gpxrm_test_caps']        = array( 'edit_post:100' );
		$GLOBALS['gpxrm_test_saves']       = array();
		$GLOBALS['gpxrm_test_revisions']   = array();
		$GLOBALS['gpxrm_test_save_throws'] = false;
		$GLOBALS['gpxrm_test_user']        = 1;
		$GLOBALS['gpxrm_test_users']       = array( 2 => 'Sam' );
		$GLOBALS['gpxrm_test_meta']        = array(
			12 => array(
				Waypoints::META_LAT => '50.75',
				Waypoints::META_LON => '5.93',
			),
		);
	}
);

afterEach(
	function () {
		$GLOBALS['gpxrm_test_caps']        = array();
		$GLOBALS['gpxrm_test_filters']     = array();
		$GLOBALS['gpxrm_test_save_throws'] = false;
	}
);

test(
	'a change lands in the post, and nothing else about it moves',
	function () {
		expect( gpxrm_write() )->toBe( array( 'code' => 'done' ) );

		$host = $GLOBALS['gpxrm_test_posts'][100];

		expect( $host->post_content )->toBe( '<!-- wp:gpx-route-map/map {"gpxId":5} /-->' );
		expect( $host->post_title )->toBe( 'Plain <span>Title</span>' );
		expect( $host->post_excerpt )->toBe( 'Excerpt <iframe></iframe>' );
		expect( $host->post_name )->toBe( 'a-slug' );
		expect( $host->post_status )->toBe( 'draft' );
	}
);

test(
	'the modified date is the one thing a save may move',
	function () {
		gpxrm_write();

		expect( $GLOBALS['gpxrm_test_posts'][100]->post_modified )->toBe( 'now' );
	}
);

test(
	'revisions keep the post before and after, as they stood',
	function () {
		gpxrm_write();

		// Slashed, as WordPress hands a post's fields to the database.
		expect( array_map( 'stripslashes', array_column( $GLOBALS['gpxrm_test_revisions'], 'post_content' ) ) )->toBe(
			array(
				GPXRM_HOST_CONTENT,
				'<!-- wp:gpx-route-map/map {"gpxId":5} /-->',
			)
		);
		expect( array_unique( array_column( $GLOBALS['gpxrm_test_revisions'], 'post_title' ) ) )->toBe( array( addslashes( 'Plain <span>Title</span>' ) ) );
		expect( array_unique( array_column( $GLOBALS['gpxrm_test_revisions'], 'post_excerpt' ) ) )->toBe( array( addslashes( 'Excerpt <iframe></iframe>' ) ) );
	}
);

// Text keeps its backslashes through a save only if it is slashed for it.
test(
	'a post\'s backslashes come through a save',
	function () {
		$GLOBALS['gpxrm_test_posts'][100]->post_content = '<!-- wp:paragraph {"a":"caf\u00e9"} --><p>C:\\dir</p><!-- /wp:paragraph -->' . GPXRM_HOST_CONTENT;

		gpxrm_write();

		expect( $GLOBALS['gpxrm_test_posts'][100]->post_content )->toBe( '<!-- wp:paragraph {"a":"caf\u00e9"} --><p>C:\\dir</p><!-- /wp:paragraph --><!-- wp:gpx-route-map/map {"gpxId":5} /-->' );
	}
);

test(
	'the save names no page template, which keeps the one the page has',
	function () {
		gpxrm_write();

		expect( $GLOBALS['gpxrm_test_saves'][0]['page_template'] )->toBe( '' );
	}
);

test(
	'the post is read afresh, and the user is recorded as its last editor',
	function () {
		$GLOBALS['gpxrm_test_cleaned'] = array();
		$GLOBALS['gpxrm_test_user']    = 7;

		gpxrm_write();

		expect( $GLOBALS['gpxrm_test_cleaned'] )->toBe( array( 100, 100 ) );
		expect( $GLOBALS['gpxrm_test_meta'][100]['_edit_last'] )->toBe( 7 );
	}
);

test(
	'all of a post\'s changes are one save',
	function () {
		$GLOBALS['gpxrm_test_posts'][100]->post_content = GPXRM_HOST_CONTENT . GPXRM_HOST_CONTENT;

		$result = gpxrm_write(
			array(
				array(
					'ordinal'  => 0,
					'op'       => 'remove-position',
					'position' => 0,
				),
				array(
					'ordinal'  => 1,
					'op'       => 'own',
					'position' => 0,
				),
			)
		);

		expect( $result )->toBe( array( 'code' => 'done' ) );
		expect( $GLOBALS['gpxrm_test_saves'] )->toHaveCount( 1 );
		expect( $GLOBALS['gpxrm_test_posts'][100]->post_content )->toBe( '<!-- wp:gpx-route-map/map {"gpxId":5} /--><!-- wp:gpx-route-map/map {"gpxId":5,"addedWaypoints":[12]} /-->' );
	}
);

test(
	'a change that changes nothing saves nothing',
	function () {
		$GLOBALS['gpxrm_test_posts'][100]->post_content = '<!-- wp:gpx-route-map/map {"gpxId":5,"addedWaypoints":[12]} /-->';

		$result = gpxrm_write(
			array(
				array(
					'ordinal'  => 0,
					'op'       => 'remove-added',
					'position' => -1,
				),
				array(
					'ordinal'  => 0,
					'op'       => 'add',
					'position' => -1,
				),
			)
		);

		expect( $result )->toBe( array( 'code' => 'done' ) );
		expect( $GLOBALS['gpxrm_test_saves'] )->toBe( array() );
	}
);

test(
	'the pin goes once the save is over, even after a throw',
	function () {
		$GLOBALS['gpxrm_test_save_throws'] = true;

		gpxrm_write();

		expect( $GLOBALS['gpxrm_test_filters']['wp_insert_post_data'] )->toBe( array() );
	}
);

test(
	'a hook that throws after the row is written does not undo the result',
	function () {
		$GLOBALS['gpxrm_test_save_throws'] = true;

		expect( gpxrm_write() )->toBe( array( 'code' => 'done' ) );
	}
);

test(
	'a save that leaves the row as it was is reported as failed',
	function () {
		$writer = new class() extends Map_Host_Writer {
			/**
			 * A save that goes nowhere.
			 *
			 * @param int    $host    The post.
			 * @param string $content Its new content.
			 * @return void
			 */
			protected function save( int $host, string $content ): void {
				unset( $host, $content );
			}
		};

		expect(
			$writer->write(
				100,
				sha1( GPXRM_HOST_CONTENT ),
				12,
				array(
					array(
						'ordinal'  => 0,
						'op'       => 'remove-position',
						'position' => 0,
					),
				)
			)
		)->toBe( array( 'code' => 'failed' ) );
	}
);

test(
	'a post that is gone, or not one maps are edited in, is refused',
	function ( array $changes ) {
		foreach ( $changes as $field => $value ) {
			$GLOBALS['gpxrm_test_posts'][100]->$field = $value;
		}

		expect( gpxrm_write() )->toBe( array( 'code' => 'gone' ) );
		expect( $GLOBALS['gpxrm_test_saves'] )->toBe( array() );
	}
)->with(
	array(
		'a revision'         => array( array( 'post_type' => 'revision' ) ),
		'a synced pattern'   => array( array( 'post_type' => 'wp_block' ) ),
		'a Waypoint'         => array( array( 'post_type' => 'gpxrm_waypoint' ) ),
		'an attachment'      => array( array( 'post_type' => 'attachment' ) ),
		'an auto-draft'      => array( array( 'post_status' => 'auto-draft' ) ),
		'an inherited state' => array( array( 'post_status' => 'inherit' ) ),
	)
);

test(
	'a post that no longer exists is refused',
	function () {
		$seen = sha1( GPXRM_HOST_CONTENT );

		unset( $GLOBALS['gpxrm_test_posts'][100] );

		expect( gpxrm_write( array(), $seen ) )->toBe( array( 'code' => 'gone' ) );
	}
);

test(
	'a post in the Trash is refused',
	function () {
		$GLOBALS['gpxrm_test_posts'][100]->post_status = 'trash';

		expect( gpxrm_write() )->toBe( array( 'code' => 'trash' ) );
	}
);

// WordPress answers yes to edit_post on a revision, and on your own trashed
// post, so what the post is has to be asked first.
test(
	'what a post is comes before whether the user can edit it',
	function () {
		$GLOBALS['gpxrm_test_caps']                  = array();
		$GLOBALS['gpxrm_test_posts'][100]->post_type = 'revision';

		expect( gpxrm_write() )->toBe( array( 'code' => 'gone' ) );
	}
);

test(
	'a user who cannot edit the post is refused',
	function () {
		$GLOBALS['gpxrm_test_caps'] = array();

		expect( gpxrm_write() )->toBe( array( 'code' => 'denied' ) );
	}
);

test(
	'a post someone else has open is refused, and says who',
	function () {
		$GLOBALS['gpxrm_test_meta'][100]['_edit_lock'] = time() . ':2';

		expect( gpxrm_write() )->toBe(
			array(
				'code' => 'locked',
				'user' => 2,
			)
		);
		expect( $GLOBALS['gpxrm_test_saves'] )->toBe( array() );
	}
);

// Core's own check waves this through, but saving that tab would undo the
// change without a word.
test(
	'a post the user has open themselves is refused',
	function () {
		$GLOBALS['gpxrm_test_meta'][100]['_edit_lock'] = time() . ':1';

		expect( gpxrm_write() )->toBe( array( 'code' => 'open' ) );
	}
);

test(
	'a lock with no user goes by who edited the post last',
	function () {
		$GLOBALS['gpxrm_test_meta'][100]['_edit_lock'] = (string) time();
		$GLOBALS['gpxrm_test_meta'][100]['_edit_last'] = 2;

		expect( gpxrm_write() )->toBe(
			array(
				'code' => 'locked',
				'user' => 2,
			)
		);
	}
);

test(
	'a lock that has lapsed lets the change through',
	function () {
		$GLOBALS['gpxrm_test_meta'][100]['_edit_lock'] = ( time() - 151 ) . ':2';

		expect( gpxrm_write() )->toBe( array( 'code' => 'done' ) );
	}
);

test(
	'a post changed since the form was drawn is refused, then accepted afresh',
	function () {
		$stale = sha1( 'what the post held before' );

		expect( gpxrm_write( array(), $stale ) )->toBe( array( 'code' => 'changed' ) );
		expect( $GLOBALS['gpxrm_test_saves'] )->toBe( array() );
		expect( gpxrm_write() )->toBe( array( 'code' => 'done' ) );
	}
);

test(
	'a change that needs coordinates is refused without them',
	function ( string $op ) {
		$GLOBALS['gpxrm_test_meta'][12] = array();

		expect(
			gpxrm_write(
				array(
					array(
						'ordinal'  => 0,
						'op'       => $op,
						'position' => 0,
					),
				)
			)
		)->toBe( array( 'code' => 'no-coordinates' ) );
	}
)->with( array( 'own', 'add' ) );

test(
	'a change that does not need coordinates goes through without them',
	function () {
		$GLOBALS['gpxrm_test_meta'][12] = array();

		expect( gpxrm_write() )->toBe( array( 'code' => 'done' ) );
	}
);

test(
	'a map that cannot be changed safely leaves the whole post as it was',
	function () {
		$result = gpxrm_write(
			array(
				array(
					'ordinal'  => 0,
					'op'       => 'remove-position',
					'position' => 0,
				),
				array(
					'ordinal'  => 7,
					'op'       => 'add',
					'position' => -1,
				),
			)
		);

		expect( $result )->toBe( array( 'code' => 'unsafe' ) );
		expect( $GLOBALS['gpxrm_test_saves'] )->toBe( array() );
		expect( $GLOBALS['gpxrm_test_posts'][100]->post_content )->toBe( GPXRM_HOST_CONTENT );
	}
);
