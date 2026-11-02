<?php
/**
 * Writing a change to the post that holds a map.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes one post's map changes from a Waypoint's screen, in one save, and
 * nothing else about that post.
 *
 * A normal save is no use here. For a user without unfiltered_html it filters
 * the whole post, so changing a link would strip the iframes, scripts, block
 * custom CSS and title markup of a post they never opened, and for anyone it
 * can publish a missed schedule, move a draft's date and clear a slug. So the
 * save puts every field of the post and its revisions back as stored. That
 * skips the filters for this one save, which is safe only because the new
 * bytes are whole numbers built on the server: everything else was stored
 * already. Nothing posted may ever reach the content.
 *
 * @phpstan-type Change array{ordinal: int, op: string, position: int}
 * @phpstan-type Result array{code: string, user?: int}
 */
class Map_Host_Writer {

	/**
	 * The fields a save may move.
	 */
	const MOVES = array( 'post_modified', 'post_modified_gmt' );

	/**
	 * Write a post's changes.
	 *
	 * @param int                              $host     The post.
	 * @param string                           $seen     The hash of its content the form was drawn from.
	 * @param int                              $waypoint The Waypoint post.
	 * @param array<int, array<string, mixed>> $changes  Its changes.
	 * @phpstan-param array<int, Change> $changes
	 * @return array{code: string, user?: int}
	 * @phpstan-return Result
	 */
	public function write( int $host, string $seen, int $waypoint, array $changes ): array {
		clean_post_cache( $host );
		$post = get_post( $host );
		if ( ! $post instanceof \WP_Post ) {
			return array( 'code' => 'gone' );
		}

		$refusal = self::refusal( $post, $seen, $waypoint, $changes );
		if ( null !== $refusal ) {
			return $refusal;
		}

		$content = self::changed_content( $post->post_content, $waypoint, $changes );
		if ( null === $content ) {
			return array( 'code' => 'unsafe' );
		}

		if ( $content === $post->post_content ) {
			return array( 'code' => 'done' );
		}

		$this->save( $host, $content );

		return self::result( $host, $content );
	}

	/**
	 * Why a post cannot take these changes, if it cannot. What a post is comes
	 * first, as WordPress answers yes to edit_post on a revision, and the save
	 * would go into it.
	 *
	 * @param \WP_Post                         $post     The post.
	 * @param string                           $seen     The hash in the form.
	 * @param int                              $waypoint The Waypoint post.
	 * @param array<int, array<string, mixed>> $changes  Its changes.
	 * @phpstan-param array<int, Change> $changes
	 * @return array{code: string, user?: int}|null
	 * @phpstan-return Result|null
	 */
	private static function refusal( \WP_Post $post, string $seen, int $waypoint, array $changes ): ?array {
		$place = self::place_refusal( $post );
		if ( '' !== $place ) {
			return array( 'code' => $place );
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return array( 'code' => 'denied' );
		}

		$lock = self::lock_refusal( $post->ID );
		if ( null !== $lock ) {
			return $lock;
		}

		if ( ! hash_equals( $seen, sha1( $post->post_content ) ) ) {
			return array( 'code' => 'changed' );
		}

		return self::needs_coordinates( $changes ) && null === Waypoint_Coordinates::of( $waypoint )
			? array( 'code' => 'no-coordinates' )
			: null;
	}

	/**
	 * Why maps in this post cannot be changed from here, if they cannot.
	 * Synced patterns can be open in the Site Editor, which takes no lock.
	 *
	 * @param \WP_Post $post The post.
	 * @return string 'trash', 'gone', or '' for none.
	 */
	private static function place_refusal( \WP_Post $post ): string {
		if ( 'trash' === $post->post_status ) {
			return 'trash';
		}

		$types  = array_diff( Map_Hosts::types(), array( 'wp_block' ) );
		$can_be = in_array( $post->post_type, $types, true ) && in_array( $post->post_status, Map_Hosts::statuses(), true );

		return $can_be ? '' : 'gone';
	}

	/**
	 * Refuse a post that is open in an editor, by anyone. Read here rather
	 * than through wp_check_post_lock(), which leaves out the user's own lock:
	 * saving a tab they left open would undo the change without a word, and a
	 * draft's autosave would do it with no click at all.
	 *
	 * @param int $host The post.
	 * @return array{code: string, user?: int}|null
	 * @phpstan-return Result|null
	 */
	private static function lock_refusal( int $host ): ?array {
		$stored = get_post_meta( $host, '_edit_lock', true );
		$lock   = explode( ':', is_string( $stored ) ? $stored : '' );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core's own filter, in wp_check_post_lock().
		$window = apply_filters( 'wp_check_post_lock_window', 150 );
		if ( (int) $lock[0] <= time() - ( is_numeric( $window ) ? (int) $window : 150 ) ) {
			return null;
		}

		$last = get_post_meta( $host, '_edit_last', true );
		$user = isset( $lock[1] ) ? (int) $lock[1] : ( is_numeric( $last ) ? (int) $last : 0 );

		return get_current_user_id() === $user
			? array( 'code' => 'open' )
			: array(
				'code' => 'locked',
				'user' => $user,
			);
	}

	/**
	 * Whether any change shows the Waypoint post at its own coordinates.
	 *
	 * @param array<int, array<string, mixed>> $changes The changes.
	 * @phpstan-param array<int, Change> $changes
	 * @return bool
	 */
	private static function needs_coordinates( array $changes ): bool {
		return array() !== array_intersect( array_column( $changes, 'op' ), array( 'own', 'add' ) );
	}

	/**
	 * The content with every change made, or null when one of them is refused,
	 * which leaves the whole post as it is.
	 *
	 * @param string                           $content  Post content.
	 * @param int                              $waypoint The Waypoint post.
	 * @param array<int, array<string, mixed>> $changes  The changes.
	 * @phpstan-param array<int, Change> $changes
	 * @return string|null
	 */
	private static function changed_content( string $content, int $waypoint, array $changes ): ?string {
		foreach ( $changes as $change ) {
			$content = Map_Link_Edit::apply( $content, $change['ordinal'], $waypoint, $change['op'], $change['position'] );
			if ( null === $content ) {
				return null;
			}
		}

		return $content;
	}

	/**
	 * Save the new content, and nothing else, for every user: the filters
	 * change other bytes for admins too. A revision first, as a post made by
	 * an import has none of how it stood. Any throw is left for result() to
	 * judge by what the row holds: a plugin's hook can throw after the row is
	 * written.
	 *
	 * @param int    $host    The post.
	 * @param string $content Its new content.
	 * @return void
	 */
	protected function save( int $host, string $content ): void {
		$post = get_post( $host );
		$row  = get_post( $host, ARRAY_A );
		if ( ! $post instanceof \WP_Post || ! is_array( $row ) ) {
			return;
		}

		$revision_content = $post->post_content;

		// Put the post and its revisions back as stored.
		$pin = static function ( array $data, array $postarr ) use ( $post, $row, $content, &$revision_content ): array {
			if ( Waypoint_Links::post_id( $postarr['ID'] ?? 0 ) === $post->ID ) {
				return self::pinned_post( $data, $row, $content );
			}

			if ( 'revision' === ( $data['post_type'] ?? '' ) && Waypoint_Links::post_id( $data['post_parent'] ?? 0 ) === $post->ID ) {
				$data['post_content'] = wp_slash( $revision_content );
				$data['post_title']   = wp_slash( $post->post_title );
				$data['post_excerpt'] = wp_slash( $post->post_excerpt );
			}

			return $data;
		};

		add_filter( 'wp_insert_post_data', $pin, PHP_INT_MAX, 2 );
		try {
			wp_save_post_revision( $host );
			$revision_content = $content;

			// An empty page_template: the post's own would be checked against
			// the theme, and one from another theme errors after the row is
			// written, with no revision made.
			wp_update_post(
				wp_slash(
					array(
						'ID'            => $host,
						'post_content'  => $content,
						'page_template' => '',
					)
				),
				true
			);
		} catch ( \Throwable $thrown ) {
			unset( $thrown );
		} finally {
			remove_filter( 'wp_insert_post_data', $pin, PHP_INT_MAX );
		}
	}

	/**
	 * A post's fields for the database: as stored, with the new content.
	 *
	 * @param array<mixed> $data    The fields, as filtered.
	 * @param array<mixed> $row     The post as stored.
	 * @param string       $content Its new content.
	 * @return array<mixed>
	 */
	private static function pinned_post( array $data, array $row, string $content ): array {
		foreach ( array_keys( $data ) as $field ) {
			if ( array_key_exists( $field, $row ) && ! in_array( $field, self::MOVES, true ) ) {
				$data[ $field ] = is_string( $row[ $field ] ) ? wp_slash( $row[ $field ] ) : $row[ $field ];
			}
		}

		$data['post_content'] = wp_slash( $content );

		return $data;
	}

	/**
	 * What the save came to, from what the row holds, whatever it returned.
	 *
	 * @param int    $host    The post.
	 * @param string $content Its new content.
	 * @return array{code: string, user?: int}
	 * @phpstan-return Result
	 */
	private static function result( int $host, string $content ): array {
		clean_post_cache( $host );
		$saved = get_post( $host );
		if ( ! $saved instanceof \WP_Post || $saved->post_content !== $content ) {
			return array( 'code' => 'failed' );
		}

		update_post_meta( $host, '_edit_last', get_current_user_id() );

		return array( 'code' => 'done' );
	}
}
