<?php
/**
 * Saving the Maps box of a Waypoint post.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the Maps box's form into changes while the Waypoint saves, and writes
 * them once it has.
 *
 * Written after the Waypoint's own save, so the Waypoint's new coordinates
 * count, and with the request emptied, as another plugin's save_post on the
 * other post would take this form for that post's own. In one run that
 * overwrote one of its fields and wiped another.
 *
 * Only rows the user changed act, and the Waypoint is always the post being
 * saved, never anything in the form.
 *
 * @phpstan-import-type Change from Map_Host_Writer
 * @phpstan-type Host array{host: int, seen: string, changes: array<int, Change>}
 */
final class Waypoint_Maps_Save {

	/**
	 * The most one Update changes: a page of the box, and one map to add to.
	 */
	const MAX_CHANGES = 101;

	const ROW = '/^([1-9][0-9]*):b:([0-9]+):([0-9a-f]{40}):(a|p[0-9]+)$/D';
	const ADD = '/^([1-9][0-9]*):b:([0-9]+):([0-9a-f]{40})$/D';

	/**
	 * What writes a post.
	 *
	 * @var Map_Host_Writer
	 */
	private $writer;

	/**
	 * What each Waypoint being saved asks for, by its ID.
	 *
	 * @var array<int, array<int, array<string, mixed>>>
	 * @phpstan-var array<int, array<int, Host>>
	 */
	private $queue = array();

	/**
	 * What saves the box.
	 *
	 * @param Map_Host_Writer|null $writer What writes a post.
	 */
	public function __construct( ?Map_Host_Writer $writer = null ) {
		$this->writer = $writer ?? new Map_Host_Writer();
	}

	/**
	 * Note what the form asks for. Autosaves, quick edit, bulk edit, the REST
	 * API and the Trash all come without its nonce, so nothing happens for
	 * them.
	 *
	 * @param int $post_id The Waypoint post.
	 * @return void
	 */
	public function queue( int $post_id ): void {
		if ( ! Waypoint_Meta_Box::form_sent( Waypoints::MAPS_NONCE, Waypoints::MAPS_NONCE_ACTION, $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- form_sent() checks the nonce, and parse() takes only what matches its patterns.
		$rows = isset( $_POST['gpxrm_maps'] ) ? wp_unslash( $_POST['gpxrm_maps'] ) : array();

		$this->queue[ $post_id ] = self::parse( is_array( $rows ) ? $rows : array(), Waypoint_Meta_Box::posted( 'gpxrm_maps_add' ) );
	}

	/**
	 * Write what the form asked for, once: the queue goes first, so a plugin
	 * saving the Waypoint again changes nothing.
	 *
	 * @param int $post_id The post just saved.
	 * @return void
	 */
	public function write( int $post_id ): void {
		$hosts = $this->queue[ $post_id ] ?? array();
		unset( $this->queue[ $post_id ] );
		if ( array() === $hosts ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only set aside and put back.
		$post     = $_POST;
		$request  = $_REQUEST;
		$_POST    = array();
		$_REQUEST = array();
		try {
			foreach ( $hosts as $host ) {
				$this->write_host( $post_id, $host );
			}
		} finally {
			$_POST    = $post;
			$_REQUEST = $request;
		}
		// phpcs:enable
	}

	/**
	 * Write one post's changes. One that fails stops neither the others nor
	 * the Waypoint's own save.
	 *
	 * @param int                  $waypoint The Waypoint post.
	 * @param array<string, mixed> $host     The post and its changes.
	 * @phpstan-param Host $host
	 * @return void
	 */
	private function write_host( int $waypoint, array $host ): void {
		try {
			$result = $this->writer->write( $host['host'], $host['seen'], $waypoint, $host['changes'] );
		} catch ( \Throwable $thrown ) {
			unset( $thrown );
			$result = array( 'code' => 'failed' );
		}

		Waypoint_Maps_Report::record(
			$waypoint,
			$result + array(
				'host'    => $host['host'],
				'changes' => count( $host['changes'] ),
			)
		);
	}

	/**
	 * What a form asks for, by post. Anything that is not exactly what the box
	 * prints is ignored, and a row left at Keep asks for nothing.
	 *
	 * @param array<mixed> $rows The rows, as field name => choice.
	 * @param string       $add  The map to add the Waypoint to, or ''.
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Host>
	 */
	public static function parse( array $rows, string $add ): array {
		$changes = array();
		foreach ( $rows as $field => $choice ) {
			$change = self::row_change( (string) $field, $choice );
			if ( null !== $change ) {
				$changes[] = $change;
			}
		}

		if ( 1 === preg_match( self::ADD, $add, $map ) ) {
			$changes[] = self::change( $map, 'add', -1 );
		}

		return self::by_host( array_slice( $changes, 0, self::MAX_CHANGES ) );
	}

	/**
	 * The change one row asks for, if any.
	 *
	 * @param string $field  The row's field name.
	 * @param mixed  $choice What was chosen.
	 * @return array<string, mixed>|null
	 * @phpstan-return array{host: int, seen: string, ordinal: int, op: string, position: int}|null
	 */
	private static function row_change( string $field, $choice ): ?array {
		if ( 1 !== preg_match( self::ROW, $field, $row ) ) {
			return null;
		}

		if ( 'a' === $row[4] ) {
			return 'remove' === $choice ? self::change( $row, 'remove-added', -1 ) : null;
		}

		$position = (int) substr( $row[4], 1 );
		if ( 'remove' === $choice ) {
			return self::change( $row, 'remove-position', $position );
		}

		return 'own' === $choice ? self::change( $row, 'own', $position ) : null;
	}

	/**
	 * A change, from what a field names.
	 *
	 * @param array<int, string> $identity The host, the ordinal and the hash, from 1.
	 * @param string             $op       The change.
	 * @param int                $position The waypoint of the file.
	 * @return array{host: int, seen: string, ordinal: int, op: string, position: int}
	 */
	private static function change( array $identity, string $op, int $position ): array {
		return array(
			'host'     => (int) $identity[1],
			'seen'     => $identity[3],
			'ordinal'  => (int) $identity[2],
			'op'       => $op,
			'position' => $position,
		);
	}

	/**
	 * Group changes by the post they are for, and the content they were drawn
	 * from: all of a post's changes are one save.
	 *
	 * @param array<int, array<string, mixed>> $changes The changes.
	 * @phpstan-param array<int, array{host: int, seen: string, ordinal: int, op: string, position: int}> $changes
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Host>
	 */
	private static function by_host( array $changes ): array {
		$hosts = array();
		foreach ( $changes as $change ) {
			$key = $change['host'] . ':' . $change['seen'];

			$hosts[ $key ]['host']      = $change['host'];
			$hosts[ $key ]['seen']      = $change['seen'];
			$hosts[ $key ]['changes'][] = array(
				'ordinal'  => $change['ordinal'],
				'op'       => $change['op'],
				'position' => $change['position'],
			);
		}

		return array_values( $hosts );
	}
}
