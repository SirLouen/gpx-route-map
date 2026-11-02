<?php
/**
 * What an Update tells the user about the maps it changed, in words a site
 * owner would use.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Waypoint_Maps_Report;

/**
 * The message for these results, as text.
 *
 * @param array<int, array<string, mixed>> $entries Results.
 * @return string
 */
function gpxrm_report( array $entries ): string {
	$GLOBALS['gpxrm_test_notices'] = array();
	foreach ( $entries as $entry ) {
		Waypoint_Maps_Report::record( 12, $entry );
	}

	$_GET['post'] = '12';
	( new Waypoint_Maps_Report() )->show();

	return html_entity_decode( strip_tags( $GLOBALS['gpxrm_test_notices'][0][0] ?? '' ), ENT_QUOTES );
}

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_transients'] = array();
		$GLOBALS['gpxrm_test_notices']    = array();
		$GLOBALS['gpxrm_test_user']       = 1;
		$GLOBALS['gpxrm_test_users']      = array( 2 => 'Sam' );
		$GLOBALS['gpxrm_test_caps']       = array( 'edit_post:100', 'read_post:100' );
		$GLOBALS['gpxrm_test_posts']      = array( 100 => new WP_Post( 100, 'Host' ) );
		$GLOBALS['gpxrm_test_screen']     = (object) array( 'post_type' => 'gpxrm_waypoint' );

		$GLOBALS['gpxrm_test_no_revisions'] = false;
	}
);

afterEach(
	function () {
		$GLOBALS['gpxrm_test_caps']   = array();
		$GLOBALS['gpxrm_test_screen'] = null;
		$_GET                         = array();
	}
);

test(
	'each result has its own line',
	function ( array $entry, string $want ) {
		$GLOBALS['gpxrm_test_no_revisions'] = true;

		expect(
			gpxrm_report(
				array(
					$entry + array(
						'host'    => 100,
						'changes' => 2,
					),
				)
			)
		)->toContain( $want );
	}
)->with(
	array(
		'done'           => array( array( 'code' => 'done' ), 'Map updated in “Host”.' ),
		'denied'         => array( array( 'code' => 'denied' ), '“Host” was not changed: you don\'t have permission to edit it.' ),
		'locked'         => array(
			array(
				'code' => 'locked',
				'user' => 2,
			),
			'“Host” was not changed: Sam is editing it. Try again in a few minutes.',
		),
		'locked by nobody known' => array(
			array(
				'code' => 'locked',
				'user' => 99,
			),
			'“Host” was not changed: Someone is editing it. Try again in a few minutes.',
		),
		'open'           => array( array( 'code' => 'open' ), '“Host” was not changed: you have it open in another tab or window. Save and close it, then click Update here. If you just closed it, try again in a few minutes.' ),
		'changed'        => array( array( 'code' => 'changed' ), '“Host” was not changed: it was edited after you opened this screen. Check the Maps box and try again.' ),
		'gone'           => array( array( 'code' => 'gone' ), 'The map is no longer in “Host”.' ),
		'trash'          => array( array( 'code' => 'trash' ), '“Host” was not changed: it is in the Trash. Restore it first.' ),
		'no-coordinates' => array( array( 'code' => 'no-coordinates' ), '“Host” was not changed: this waypoint needs coordinates first.' ),
		'unsafe'         => array( array( 'code' => 'unsafe' ), '“Host” was not changed: its map can\'t be changed from here. Change it in the map\'s block.' ),
		'failed'         => array( array( 'code' => 'failed' ), '“Host” was not changed: WordPress could not save it. Try again.' ),
	)
);

test(
	'a post that no longer exists is not named',
	function () {
		$text = gpxrm_report(
			array(
				array(
					'code'    => 'gone',
					'host'    => 500,
					'changes' => 1,
				),
			)
		);

		expect( $text )->toContain( 'The map is no longer in a post that exists.' );
		expect( $GLOBALS['gpxrm_test_notices'][0][1]['type'] )->toBe( 'warning' );
	}
);

test(
	'it says how to undo a change only where that can be done',
	function () {
		$entry = array(
			'code'    => 'done',
			'host'    => 100,
			'changes' => 1,
		);

		expect( gpxrm_report( array( $entry ) ) )->toContain( 'To undo it, go back to the earlier version of “Host”.' );

		$GLOBALS['gpxrm_test_no_revisions'] = true;

		expect( gpxrm_report( array( $entry ) ) )->not->toContain( 'To undo it' );
	}
);

test(
	'a title the user may not read never shows',
	function () {
		$GLOBALS['gpxrm_test_caps']                   = array();
		$GLOBALS['gpxrm_test_posts'][100]->post_title = 'Secret';

		$text = gpxrm_report(
			array(
				array(
					'code'    => 'denied',
					'host'    => 100,
					'changes' => 1,
				),
			)
		);

		expect( $text )->toContain( 'A post you can\'t view was not changed' );
		expect( $text )->not->toContain( 'Secret' );
	}
);

test(
	'a post the user can edit is linked, one they can only read is not',
	function () {
		$entry = array(
			'code'    => 'denied',
			'host'    => 100,
			'changes' => 1,
		);

		gpxrm_report( array( $entry ) );
		expect( $GLOBALS['gpxrm_test_notices'][0][0] )->toContain( '<a href="https://example.test/edit/100">“Host”</a>' );

		$GLOBALS['gpxrm_test_caps'] = array( 'read_post:100' );

		gpxrm_report( array( $entry ) );
		expect( $GLOBALS['gpxrm_test_notices'][0][0] )->not->toContain( '<a ' );
	}
);

test(
	'every change landing is a success, in one notice',
	function () {
		gpxrm_report(
			array(
				array(
					'code'    => 'done',
					'host'    => 100,
					'changes' => 2,
				),
				array(
					'code'    => 'done',
					'host'    => 100,
					'changes' => 1,
				),
			)
		);

		expect( $GLOBALS['gpxrm_test_notices'] )->toHaveCount( 1 );
		expect( $GLOBALS['gpxrm_test_notices'][0][1]['type'] )->toBe( 'success' );
		expect( $GLOBALS['gpxrm_test_notices'][0][1]['attributes'] )->toBe( array( 'role' => 'alert' ) );
		expect( $GLOBALS['gpxrm_test_notices'][0][1]['paragraph_wrap'] )->toBeFalse();
	}
);

test(
	'anything not landing is a warning that opens with what was saved',
	function () {
		$text = gpxrm_report(
			array(
				array(
					'code'    => 'done',
					'host'    => 100,
					'changes' => 2,
				),
				array(
					'code'    => 'changed',
					'host'    => 100,
					'changes' => 1,
				),
			)
		);

		expect( $GLOBALS['gpxrm_test_notices'] )->toHaveCount( 1 );
		expect( $GLOBALS['gpxrm_test_notices'][0][1]['type'] )->toBe( 'warning' );
		expect( $text )->toStartWith( 'This waypoint was saved, but 1 of 3 map changes was not made.' );
	}
);

test(
	'the count reads properly for more than one change',
	function () {
		$text = gpxrm_report(
			array(
				array(
					'code'    => 'changed',
					'host'    => 100,
					'changes' => 2,
				),
			)
		);

		expect( $text )->toStartWith( 'This waypoint was saved, but 2 of 2 map changes were not made.' );
	}
);

test(
	'the words a site owner would not use stay out of it',
	function ( array $entry ) {
		$GLOBALS['gpxrm_test_posts'][100]->post_title = 'Route';

		$text = gpxrm_report(
			array(
				$entry + array(
					'host'    => 100,
					'changes' => 1,
				),
			)
		);

		expect( strtolower( $text ) )->not->toMatch( '/\b(lock|revision|kses|filter|transient|attribute|host|markup)\b/' );
	}
)->with(
	array(
		'done'    => array( array( 'code' => 'done' ) ),
		'open'    => array( array( 'code' => 'open' ) ),
		'unsafe'  => array( array( 'code' => 'unsafe' ) ),
		'locked'  => array(
			array(
				'code' => 'locked',
				'user' => 2,
			),
		),
	)
);

test(
	'it says so once',
	function () {
		gpxrm_report(
			array(
				array(
					'code'    => 'done',
					'host'    => 100,
					'changes' => 1,
				),
			)
		);

		$GLOBALS['gpxrm_test_notices'] = array();
		( new Waypoint_Maps_Report() )->show();

		expect( $GLOBALS['gpxrm_test_notices'] )->toBe( array() );
		expect( $GLOBALS['gpxrm_test_transients'] )->toBe( array() );
	}
);

test(
	'it waits for that Waypoint\'s own screen',
	function () {
		Waypoint_Maps_Report::record(
			12,
			array(
				'code'    => 'done',
				'host'    => 100,
				'changes' => 1,
			)
		);

		$_GET['post'] = '13';
		( new Waypoint_Maps_Report() )->show();
		expect( $GLOBALS['gpxrm_test_notices'] )->toBe( array() );

		$_GET['post']                 = '12';
		$GLOBALS['gpxrm_test_screen'] = (object) array( 'post_type' => 'post' );
		( new Waypoint_Maps_Report() )->show();
		expect( $GLOBALS['gpxrm_test_notices'] )->toBe( array() );

		$GLOBALS['gpxrm_test_screen'] = (object) array( 'post_type' => 'gpxrm_waypoint' );
		( new Waypoint_Maps_Report() )->show();
		expect( $GLOBALS['gpxrm_test_notices'] )->toHaveCount( 1 );
	}
);

test(
	'each user reads only their own results',
	function () {
		Waypoint_Maps_Report::record(
			12,
			array(
				'code'    => 'done',
				'host'    => 100,
				'changes' => 1,
			)
		);

		$GLOBALS['gpxrm_test_user'] = 2;
		$_GET['post']               = '12';
		( new Waypoint_Maps_Report() )->show();

		expect( $GLOBALS['gpxrm_test_notices'] )->toBe( array() );
	}
);
