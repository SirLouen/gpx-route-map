<?php
/**
 * Checks the writing of map links on a real WordPress, which Pest cannot run,
 * as it has no wp_insert_post(). Not shipped.
 *
 * Run with Waypoint posts on: wp eval-file tools/verify-map-links.php
 *
 * For each role and each state of a post it makes the post, stores content that
 * WordPress's filters would change, writes one change through the real writer,
 * then compares the row byte by byte. It removes all it made. Prints PASS or
 * FAIL for each, and a count at the end.
 *
 * @package GpxRouteMap
 */

use Gpxrm\Map_Host_Writer;
use Gpxrm\Waypoints;

$GLOBALS['gpxrm_verify'] = array( 0, 0 );

/**
 * Print a result, and count it.
 *
 * @param string $name What was checked.
 * @param bool   $pass Whether it held.
 * @param string $note More about it.
 * @return void
 */
function gpxrm_verify( string $name, bool $pass, string $note = '' ): void {
	$GLOBALS['gpxrm_verify'][0]++;
	$GLOBALS['gpxrm_verify'][1] += $pass ? 0 : 1;
	echo ( $pass ? 'PASS  ' : 'FAIL  ' ), $name, '' === $note ? '' : "  ($note)", "\n";
}

/**
 * A post's row, straight from the database.
 *
 * @param int $id Post ID.
 * @return array<string, string>
 */
function gpxrm_verify_row( int $id ): array {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $id ), ARRAY_A );
}

/**
 * A post's revisions, oldest first.
 *
 * @param int $id Post ID.
 * @return array<int, array<string, string>>
 */
function gpxrm_verify_revisions( int $id ): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare( "SELECT post_title, post_excerpt, post_content FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' ORDER BY ID ASC", $id ), ARRAY_A );
}

if ( ! Waypoints::enabled() ) {
	WP_CLI::error( 'Turn Waypoint posts on first.' );
}

$title   = 'Plain <span class="brand">Title</span>';
$excerpt = 'Excerpt <iframe src="https://example.test/e"></iframe>';
$before  = implode(
	"\n",
	array(
		'<!-- wp:html --><iframe src="https://example.test/x"></iframe><script>var a=1;</script><!-- /wp:html -->',
		'<!-- wp:paragraph {"style":{"css":"color:red"}} --><p>caf\u00e9 C:\\dir &#146; x\u002d\u002dy</p><!-- /wp:paragraph -->',
		'<!-- wp:x {"settings":{},"list":{"0":"a","1":"b"}} /-->',
		'<!-- wp:group --><div class="wp-block-group"><!-- wp:gpx-route-map/map {"gpxId":77,"height":300} /--></div><!-- /wp:group -->',
		'<!-- wp:gpx-route-map/map {"gpxId":77,"height":500,"waypointPosts":[WAYPOINT,40],"stats":{"distance":1.5,"waypoints":3}} /-->',
		'[gpx_route_map id=77]',
	)
);

// A role like Editor without unfiltered_html, as Editors are on a multisite.
$capabilities = get_role( 'editor' )->capabilities;
unset( $capabilities['unfiltered_html'] );
add_role( 'gpxrm_verify_editor', 'Verify editor', $capabilities );

$users = array();
foreach ( array( 'administrator', 'editor', 'author', 'gpxrm_verify_editor' ) as $role ) {
	$users[ $role ] = wp_insert_user(
		array(
			'user_login' => 'gpxrm_verify_' . $role,
			'user_pass'  => wp_generate_password(),
			'user_email' => 'gpxrm_verify_' . $role . '@example.test',
			'role'       => $role,
		)
	);
}
$contributor = wp_insert_user(
	array(
		'user_login' => 'gpxrm_verify_contributor',
		'user_pass'  => wp_generate_password(),
		'user_email' => 'gpxrm_verify_contributor@example.test',
		'role'       => 'contributor',
	)
);

wp_set_current_user( $users['administrator'] );
$waypoint = wp_insert_post(
	array(
		'post_type'   => Waypoints::POST_TYPE,
		'post_title'  => 'gpxrm verify waypoint',
		'post_status' => 'publish',
	)
);
update_post_meta( $waypoint, Waypoints::META_LAT, '50.75' );
update_post_meta( $waypoint, Waypoints::META_LON, '5.93' );

$before = str_replace( 'WAYPOINT', (string) $waypoint, $before );
$after  = str_replace(
	array( '"gpxId":77,"height":500,"waypointPosts":[' . $waypoint . ',40],"stats":{"distance":1.5,"waypoints":3}}' ),
	array( '"gpxId":77,"height":500,"waypointPosts":{"1":40},"stats":{"distance":1.5,"waypoints":3},"addedWaypoints":[' . $waypoint . ']}' ),
	$before
);

$past   = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
$future = gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS );
$states = array(
	'published'         => array( 'post_status' => 'publish' ),
	'floating draft'    => array(
		'post_status'   => 'draft',
		'post_date'     => $past,
		'post_date_gmt' => '0000-00-00 00:00:00',
	),
	'dated draft'       => array(
		'post_status'   => 'draft',
		'post_date'     => $past,
		'post_date_gmt' => $past,
	),
	'pending'           => array(
		'post_status' => 'pending',
		'post_name'   => 'gpxrm-verify-pending-slug',
	),
	'contributor draft' => array(
		'post_status' => 'pending',
		'post_author' => $contributor,
		'post_name'   => 'gpxrm-verify-contributor-slug',
	),
	'scheduled'         => array(
		'post_status'   => 'future',
		'post_date'     => $future,
		'post_date_gmt' => $future,
	),
	'missed schedule'   => array(
		'post_status'   => 'future',
		'post_date'     => $past,
		'post_date_gmt' => $past,
	),
	'private'           => array( 'post_status' => 'private' ),
	'password'          => array(
		'post_status'   => 'publish',
		'post_password' => 'verify',
	),
	'page, gone theme'  => array(
		'post_status' => 'publish',
		'post_type'   => 'page',
	),
);

$made = array( $waypoint );
foreach ( $users as $role => $user ) {
	foreach ( $states as $state => $fields ) {
		// Authors edit their own posts, so each post is theirs.
		wp_set_current_user( $users['administrator'] );
		$id = wp_insert_post(
			wp_slash(
				array_merge(
					array(
						'post_title'   => $title,
						'post_excerpt' => $excerpt,
						'post_content' => 'to be replaced',
						'post_author'  => $user,
						'post_name'    => 'gpxrm-verify-' . wp_generate_password( 6, false, false ),
					),
					$fields
				)
			)
		);
		$made[] = $id;

		global $wpdb;
		// Stored as is: an insert would change what the filters change.
		$wpdb->update(
			$wpdb->posts,
			array_merge(
				array( 'post_content' => $before ),
				'missed schedule' === $state ? array( 'post_status' => 'future' ) : array()
			),
			array( 'ID' => $id )
		);
		if ( 'page, gone theme' === $state ) {
			update_post_meta( $id, '_wp_page_template', 'templates/gone-theme.php' );
		}
		update_post_meta( $id, '_wp_trash_meta_status', 'kept' );
		clean_post_cache( $id );

		// A schedule that was missed still has its overdue event.
		if ( 'missed schedule' === $state ) {
			wp_schedule_single_event( strtotime( $past . ' GMT' ), 'publish_future_post', array( $id ) );
		}

		$cron = wp_next_scheduled( 'publish_future_post', array( $id ) );
		$row  = gpxrm_verify_row( $id );

		wp_set_current_user( $user );
		$can = current_user_can( 'edit_post', $id );

		$result = ( new Map_Host_Writer() )->write(
			$id,
			sha1( $before ),
			$waypoint,
			array(
				array(
					'ordinal'  => 1,
					'op'       => 'own',
					'position' => 0,
				),
			)
		);

		$label = "$role, $state";
		if ( ! $can ) {
			gpxrm_verify( "$label: refused without edit_post", 'denied' === $result['code'] && gpxrm_verify_row( $id )['post_content'] === $before, $result['code'] );
			continue;
		}

		$now     = gpxrm_verify_row( $id );
		$changed = array_keys( array_diff_assoc( $now, $row ) );
		$moved   = array_diff( $changed, array( 'post_content', 'post_modified', 'post_modified_gmt' ) );

		gpxrm_verify( "$label: written", 'done' === $result['code'], $result['code'] );
		gpxrm_verify( "$label: the exact new bytes", $now['post_content'] === $after );
		gpxrm_verify( "$label: only content and modified date moved", array() === $moved, implode( ',', $moved ) );

		$revisions = gpxrm_verify_revisions( $id );
		gpxrm_verify(
			"$label: revisions old then new, byte for byte",
			2 === count( $revisions )
				&& $revisions[0]['post_content'] === $before
				&& $revisions[1]['post_content'] === $after
				&& $revisions[0]['post_title'] === $title
				&& $revisions[1]['post_excerpt'] === $excerpt,
			count( $revisions ) . ' revisions'
		);
		gpxrm_verify( "$label: its schedule stayed", $cron === wp_next_scheduled( 'publish_future_post', array( $id ) ), var_export( $cron, true ) );
		gpxrm_verify( "$label: its meta stayed", 'kept' === get_post_meta( $id, '_wp_trash_meta_status', true ) );
		if ( 'page, gone theme' === $state ) {
			gpxrm_verify( "$label: template kept", 'templates/gone-theme.php' === get_post_meta( $id, '_wp_page_template', true ) );
		}
	}
}

// The guards, on a post of its own.
wp_set_current_user( $users['administrator'] );
$id     = wp_insert_post(
	array(
		'post_title'  => 'gpxrm verify guards',
		'post_status' => 'publish',
		'post_author' => $users['administrator'],
	)
);
$made[] = $id;
$wpdb->update( $wpdb->posts, array( 'post_content' => '<!-- wp:gpx-route-map/map {"gpxId":77} /-->' ), array( 'ID' => $id ) );
clean_post_cache( $id );

$content = gpxrm_verify_row( $id )['post_content'];
$add     = array(
	array(
		'ordinal'  => 0,
		'op'       => 'add',
		'position' => -1,
	),
);
$write   = static function ( int $host, string $seen ) use ( $waypoint, $add ): string {
	return ( new Map_Host_Writer() )->write( $host, $seen, $waypoint, $add )['code'];
};

gpxrm_verify( 'a stale hash is refused', 'changed' === $write( $id, sha1( 'old' ) ) );

update_post_meta( $id, '_edit_lock', time() . ':' . $users['administrator'] );
gpxrm_verify( 'the user\'s own open tab is refused', 'open' === $write( $id, sha1( $content ) ) );

update_post_meta( $id, '_edit_lock', time() . ':' . $users['editor'] );
gpxrm_verify( 'another user\'s lock is refused', 'locked' === $write( $id, sha1( $content ) ) );
delete_post_meta( $id, '_edit_lock' );

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
WP_Upgrader::create_lock( 'gpxrm_map_' . $id, Map_Host_Writer::LOCK_SECONDS );
gpxrm_verify( 'a post another Update is writing is refused', 'changed' === $write( $id, sha1( $content ) ) );
WP_Upgrader::release_lock( 'gpxrm_map_' . $id );

gpxrm_verify( 'a fresh hash goes through', 'done' === $write( $id, sha1( $content ) ) );
gpxrm_verify( 'the post is let go afterwards', false === get_option( 'gpxrm_map_' . $id . '.lock' ) );

$revisions = wp_get_post_revisions( $id );
gpxrm_verify( 'a revision is not a post to write to', 'gone' === $write( (int) array_key_first( $revisions ), sha1( $content ) ) );

wp_trash_post( $id );
gpxrm_verify( 'a post in the Trash is refused', 'trash' === $write( $id, sha1( $content ) ) );

// Remove what was made.
foreach ( $made as $post_id ) {
	wp_delete_post( $post_id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array_merge( array_values( $users ), array( $contributor ) ) as $user ) {
	wp_delete_user( $user );
}
remove_role( 'gpxrm_verify_editor' );

printf( "\n%d checks, %d failed\n", $GLOBALS['gpxrm_verify'][0], $GLOBALS['gpxrm_verify'][1] );
