<?php
/**
 * What an Update tells the user about the maps it changed.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps what became of each post an Update changed, across the redirect that
 * follows it, and says so once, in one notice, on that Waypoint's screen.
 *
 * WordPress says "Post updated." whatever happened to the maps, so when one
 * change did not land the notice opens by saying what was saved and what was
 * not. It speaks of posts and maps, as a site owner would.
 *
 * @phpstan-type Entry array{code: string, host: int, changes: int, user?: int}
 */
final class Waypoint_Maps_Report {

	/**
	 * Keep what became of one post. Called after each post, so a post that
	 * ends the request does not lose the ones before it.
	 *
	 * @param int                  $waypoint The Waypoint post.
	 * @param array<string, mixed> $entry    What became of it.
	 * @phpstan-param Entry $entry
	 * @return void
	 */
	public static function record( int $waypoint, array $entry ): void {
		$entries   = self::entries( $waypoint );
		$entries[] = $entry;

		set_transient( self::key( $waypoint ), $entries, HOUR_IN_SECONDS );
	}

	/**
	 * Show what the last Update did, on the screen of its Waypoint.
	 *
	 * @return void
	 */
	public function show(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only chooses which notice to show.
		$waypoint = isset( $_GET['post'] ) && is_string( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$screen   = get_current_screen();
		if ( null === $screen || Waypoints::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$entries = self::entries( $waypoint );
		if ( array() === $entries ) {
			return;
		}

		delete_transient( self::key( $waypoint ) );

		wp_admin_notice(
			self::message( $entries ),
			array(
				'type'           => 0 === self::not_made( $entries ) ? 'success' : 'warning',
				'attributes'     => array( 'role' => 'alert' ),
				'paragraph_wrap' => false,
			)
		);
	}

	/**
	 * Where a user's results for a Waypoint post are kept.
	 *
	 * @param int $waypoint The Waypoint post.
	 * @return string
	 */
	private static function key( int $waypoint ): string {
		return 'gpxrm_links_' . get_current_user_id() . '_' . $waypoint;
	}

	/**
	 * The results kept for a Waypoint post.
	 *
	 * @param int $waypoint The Waypoint post.
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Entry>
	 */
	private static function entries( int $waypoint ): array {
		/**
		 * What record() kept, or nothing.
		 *
		 * @var array<int, array{code: string, host: int, changes: int, user?: int}>|false $entries
		 */
		$entries = get_transient( self::key( $waypoint ) );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * How many changes did not land.
	 *
	 * @param array<int, array<string, mixed>> $entries The results.
	 * @phpstan-param array<int, Entry> $entries
	 * @return int
	 */
	private static function not_made( array $entries ): int {
		$not_made = 0;
		foreach ( $entries as $entry ) {
			$not_made += 'done' === $entry['code'] ? 0 : $entry['changes'];
		}

		return $not_made;
	}

	/**
	 * The notice: what was not made, when something was not, then a line for
	 * each post.
	 *
	 * @param array<int, array<string, mixed>> $entries The results.
	 * @phpstan-param array<int, Entry> $entries
	 * @return string Markup, escaped.
	 */
	private static function message( array $entries ): string {
		$not_made = self::not_made( $entries );
		$lines    = array();
		if ( $not_made > 0 ) {
			$lines[] = esc_html(
				sprintf(
					/* translators: 1: how many map changes were not made, 2: how many were asked for. */
					_n( 'This waypoint was saved, but %1$d of %2$d map changes was not made.', 'This waypoint was saved, but %1$d of %2$d map changes were not made.', $not_made, 'gpx-route-map' ),
					$not_made,
					array_sum( array_column( $entries, 'changes' ) )
				)
			);
		}

		foreach ( $entries as $entry ) {
			$lines[] = self::line( $entry );
		}

		return '<p>' . implode( '</p><p>', $lines ) . '</p>';
	}

	/**
	 * One post's line: what did not happen, why, and one next step.
	 *
	 * @param array<string, mixed> $entry What became of the post.
	 * @phpstan-param Entry $entry
	 * @return string Markup, escaped.
	 */
	private static function line( array $entry ): string {
		$host = $entry['host'];
		if ( ! get_post( $host ) instanceof \WP_Post ) {
			return esc_html__( 'The map is no longer in a post that exists.', 'gpx-route-map' );
		}

		$lines = self::lines();
		$line  = sprintf( esc_html( $lines[ $entry['code'] ] ?? $lines['failed'] ), self::name( $host ), esc_html( self::editor( $entry ) ) );

		return 'done' === $entry['code'] ? $line . self::undo_note( $host ) : $line;
	}

	/**
	 * What to say of each result. Each %s or %1$s is the post, as a name
	 * already in quotes.
	 *
	 * @return array<string, string>
	 */
	private static function lines(): array {
		return array(
			/* translators: %s: a post's title, in quotes. */
			'done'           => __( 'Map updated in %s.', 'gpx-route-map' ),
			/* translators: %s: a post's title, in quotes. */
			'denied'         => __( '%s was not changed: you don\'t have permission to edit it.', 'gpx-route-map' ),
			/* translators: 1: a post's title, in quotes, 2: the name of the person editing it. */
			'locked'         => __( '%1$s was not changed: %2$s is editing it. Try again in a few minutes.', 'gpx-route-map' ),
			/* translators: %s: a post's title, in quotes. */
			'open'           => __( '%s was not changed: you have it open in another tab or window. Save and close it, then click Update here. If you just closed it, try again in a few minutes.', 'gpx-route-map' ),
			/* translators: %s: a post's title, in quotes. */
			'changed'        => __( '%s was not changed: it was edited after you opened this screen. Check the Maps box and try again.', 'gpx-route-map' ),
			/* translators: %s: a post's title, in quotes. */
			'gone'           => __( 'The map is no longer in %s.', 'gpx-route-map' ),
			/* translators: %s: a post's title, in quotes. */
			'trash'          => __( '%s was not changed: it is in the Trash. Restore it first.', 'gpx-route-map' ),
			/* translators: %s: a post's title, in quotes. */
			'no-coordinates' => __( '%s was not changed: this waypoint needs coordinates first.', 'gpx-route-map' ),
			/* translators: %s: a post's title, in quotes. */
			'unsafe'         => __( '%s was not changed: its map can\'t be changed from here. Change it in the map\'s block.', 'gpx-route-map' ),
			/* translators: %s: a post's title, in quotes. */
			'failed'         => __( '%s was not changed: WordPress could not save it. Try again.', 'gpx-route-map' ),
		);
	}

	/**
	 * A post's name, escaped: in quotes, and a link if the user can edit it.
	 * Never the title of a post the user may not read.
	 *
	 * @param int $host The post.
	 * @return string
	 */
	private static function name( int $host ): string {
		if ( ! current_user_can( 'read_post', $host ) && ! current_user_can( 'edit_post', $host ) ) {
			return esc_html__( 'A post you can\'t view', 'gpx-route-map' );
		}

		$name = self::quoted( $host );
		$link = current_user_can( 'edit_post', $host ) ? get_edit_post_link( $host ) : '';

		return is_string( $link ) && '' !== $link ? sprintf( '<a href="%s">%s</a>', esc_url( $link ), $name ) : $name;
	}

	/**
	 * A post's title, escaped, in quotes.
	 *
	 * @param int $host The post.
	 * @return string
	 */
	private static function quoted( int $host ): string {
		return sprintf(
			/* translators: %s: a post's title. */
			esc_html__( '“%s”', 'gpx-route-map' ),
			_draft_or_post_title( $host )
		);
	}

	/**
	 * Who has a post open, by the name the user knows them by.
	 *
	 * @param array<string, mixed> $entry What became of the post.
	 * @phpstan-param Entry $entry
	 * @return string
	 */
	private static function editor( array $entry ): string {
		$user = get_userdata( $entry['user'] ?? 0 );

		return false === $user ? __( 'Someone', 'gpx-route-map' ) : $user->display_name;
	}

	/**
	 * How to undo a change, where a post keeps earlier versions.
	 *
	 * @param int $host The post.
	 * @return string Markup, escaped, with a space before it.
	 */
	private static function undo_note( int $host ): string {
		$post = get_post( $host );
		if ( ! $post instanceof \WP_Post || ! wp_revisions_enabled( $post ) || ! post_type_supports( $post->post_type, 'revisions' ) ) {
			return '';
		}

		return ' ' . sprintf(
			/* translators: %s: a post's title, in quotes. */
			esc_html__( 'To undo it, go back to the earlier version of %s.', 'gpx-route-map' ),
			self::quoted( $host )
		);
	}
}
