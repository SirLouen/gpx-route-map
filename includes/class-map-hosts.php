<?php
/**
 * The posts that can hold a map.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds the posts that hold a map, by looking through their content.
 *
 * Costs a read of every post holding a map, about 0.4 s at 5,000 posts and 3 s
 * at 50,000, so it runs only for a Waypoint's own screen, never on a save or a
 * list. Templates are left out: the Site Editor takes no post lock, and one
 * saved for another theme would look live.
 */
final class Map_Hosts {

	/**
	 * How many posts to hold in memory at a time.
	 */
	const CHUNK = 200;

	/**
	 * The post types people edit, without the ones that hold no maps.
	 *
	 * @return array<int, string>
	 */
	public static function types(): array {
		return array_values(
			array_diff(
				get_post_types( array( 'show_ui' => true ) ),
				array( 'attachment', Waypoints::POST_TYPE, 'wp_navigation' )
			)
		);
	}

	/**
	 * The statuses a person can set, which leaves out the Trash, auto-drafts
	 * and revisions.
	 *
	 * @return array<int, string>
	 */
	public static function statuses(): array {
		return array_values( get_post_stati( array( 'internal' => false ) ) );
	}

	/**
	 * The content of each post holding a map, by post ID, most recently
	 * changed first. Read straight from the database, in chunks, as a site's
	 * posts would not fit in the post cache: that is what keeps memory flat.
	 * It takes no account of who may read them: see Waypoint_Map_Rows.
	 *
	 * @return \Generator<int, string>
	 */
	public static function contents(): \Generator {
		/**
		 * The database.
		 *
		 * @var \wpdb $wpdb
		 */
		global $wpdb;

		foreach ( array_chunk( self::ids(), self::CHUNK ) as $ids ) {
			$list = implode( ', ', $ids );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- See above. Only whole numbers are in the list.
			$rows = (array) $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE ID IN ({$list})", ARRAY_A );

			// In any order the database likes.
			$by_id = array_column( $rows, 'post_content', 'ID' );
			foreach ( $ids as $id ) {
				$content = $by_id[ $id ] ?? null;
				if ( is_string( $content ) ) {
					yield $id => $content;
				}
			}
		}
	}

	/**
	 * The IDs of the posts whose content holds a map, or looks as if it does.
	 *
	 * No "<!-- " in the block's pattern: the parser accepts more spaces there.
	 *
	 * @return array<int, int>
	 */
	private static function ids(): array {
		/**
		 * The database.
		 *
		 * @var \wpdb $wpdb
		 */
		global $wpdb;

		$quoted = static function ( string $value ) use ( $wpdb ): string {
			return (string) $wpdb->prepare( '%s', $value );
		};

		$types     = implode( ', ', array_map( $quoted, self::types() ) );
		$statuses  = implode( ', ', array_map( $quoted, self::statuses() ) );
		$block     = $quoted( '%' . $wpdb->esc_like( 'wp:' . Map_Tokens::BLOCK ) . '%' );
		$shortcode = $quoted( '%' . $wpdb->esc_like( '[' . Post_Maps::SHORTCODE ) . '%' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- See contents(). Each value is prepared above.
		$ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type IN ({$types}) AND post_status IN ({$statuses})
			AND ( post_content LIKE {$block} OR post_content LIKE {$shortcode} )
			ORDER BY post_modified_gmt DESC, ID DESC"
		);
		// phpcs:enable

		return array_values( array_map( array( Waypoint_Links::class, 'post_id' ), $ids ) );
	}
}
