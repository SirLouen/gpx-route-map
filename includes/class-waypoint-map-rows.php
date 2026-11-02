<?php
/**
 * The maps that show a Waypoint post, as its Maps box lists them.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every place a map shows a Waypoint post, and only what the user may see.
 *
 * The posts come straight from the database, which ignores who may read them,
 * so the checks here are what keep the titles of private posts from showing.
 *
 * @phpstan-import-type Map from Post_Maps
 * @phpstan-type Place array{where: string, position: int}
 * @phpstan-type Found array{where: string, position: int, number: int, of: int, file: string, kind: string, ordinal: int, hash: string}
 * @phpstan-type Row array{where: string, position: int, number: int, of: int, file: string, kind: string, ordinal: int, hash: string, host: int, can_edit: bool}
 * @phpstan-type Addable array{host: int, ordinal: int, hash: string, number: int, of: int, file: string}
 */
final class Waypoint_Map_Rows {

	/**
	 * How many rows one page of the box shows.
	 */
	const PER_PAGE = 100;

	/**
	 * How many maps the box offers to add a Waypoint post to.
	 */
	const MAX_ADDABLE = 200;

	/**
	 * How many posts to hold in memory at a time when asking which can be
	 * edited.
	 */
	const ADDABLE_CHUNK = 200;

	/**
	 * The rows for a Waypoint post, from every post holding a map.
	 *
	 * @param int $waypoint The Waypoint post.
	 * @return array{rows: array<int, array<string, mixed>>, hidden: int, unreadable: int, addable: array<int, array<string, mixed>>}
	 * @phpstan-return array{rows: array<int, Row>, hidden: int, unreadable: int, addable: array<int, Addable>}
	 */
	public static function for_waypoint( int $waypoint ): array {
		return self::from_contents( $waypoint, Map_Hosts::contents() );
	}

	/**
	 * The rows for a Waypoint post, from these posts.
	 *
	 * @param int            $waypoint The Waypoint post.
	 * @param iterable       $contents Post content, by post ID.
	 * @param Post_Maps|null $reader   What reads each post's maps.
	 * @phpstan-param iterable<int, string> $contents
	 * @return array{rows: array<int, array<string, mixed>>, hidden: int, unreadable: int, addable: array<int, array<string, mixed>>} `hidden`
	 *         counts maps in posts the user cannot read, `unreadable` posts that could not be
	 *         read at all, and `addable` is what addable() goes through.
	 * @phpstan-return array{rows: array<int, Row>, hidden: int, unreadable: int, addable: array<int, Addable>}
	 */
	public static function from_contents( int $waypoint, iterable $contents, ?Post_Maps $reader = null ): array {
		$reader     = $reader ?? new Post_Maps();
		$by_host    = array();
		$addable    = array();
		$unreadable = 0;
		foreach ( $contents as $host => $content ) {
			$maps = $reader->read( $content );
			if ( null === $maps ) {
				++$unreadable;
				continue;
			}

			$hash   = sha1( $content );
			$places = self::places_in( $maps, $waypoint, $hash );
			if ( array() !== $places ) {
				$by_host[ $host ] = $places;
			}

			array_push( $addable, ...self::addable_in( $maps, $waypoint, $host, $hash ) );
		}

		// Otherwise each host below is a query of its own.
		_prime_post_caches( array_keys( $by_host ), false, false );

		return self::visible( $by_host ) + array(
			'unreadable' => $unreadable,
			'addable'    => $addable,
		);
	}

	/**
	 * The places a post's maps show a Waypoint post. A map with no file shows
	 * nothing, so it has none.
	 *
	 * @param array<int, array<string, mixed>> $maps     The post's maps.
	 * @param int                              $waypoint The Waypoint post.
	 * @param string                           $hash     The hash of the post's content.
	 * @phpstan-param array<int, Map> $maps
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Found>
	 */
	private static function places_in( array $maps, int $waypoint, string $hash ): array {
		$found = array();
		foreach ( array_values( $maps ) as $index => $map ) {
			if ( '' === $map['file_url'] ) {
				continue;
			}

			foreach ( self::places( $map, $waypoint ) as $place ) {
				$found[] = $place + array(
					'number'  => $index + 1,
					'of'      => count( $maps ),
					'file'    => $map['file_url'],
					'kind'    => $map['kind'],
					'ordinal' => $map['ordinal'],
					'hash'    => $hash,
				);
			}
		}

		return $found;
	}

	/**
	 * The maps of a post a Waypoint post could be added to: block maps with a
	 * file that do not show it already.
	 *
	 * @param array<int, array<string, mixed>> $maps     The post's maps.
	 * @param int                              $waypoint The Waypoint post.
	 * @param int                              $host     The post.
	 * @param string                           $hash     The hash of its content.
	 * @phpstan-param array<int, Map> $maps
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Addable>
	 */
	private static function addable_in( array $maps, int $waypoint, int $host, string $hash ): array {
		$found = array();
		foreach ( array_values( $maps ) as $index => $map ) {
			if ( 'block' !== $map['kind'] ) {
				continue;
			}

			if ( '' !== $map['file_url'] && array() === self::places( $map, $waypoint ) ) {
				$found[] = array(
					'host'    => $host,
					'ordinal' => $map['ordinal'],
					'hash'    => $hash,
					'number'  => $index + 1,
					'of'      => count( $maps ),
					'file'    => $map['file_url'],
				);
			}
		}

		return $found;
	}

	/**
	 * The maps the box offers: the first in the user can edit, leaving out
	 * synced patterns. In chunks, stopping once it has enough, and letting go
	 * of the posts it will not use, as a user who can edit few of a site's
	 * posts would otherwise hold them all in memory.
	 *
	 * @param array<int, array<string, mixed>> $maps The maps, most recently changed first.
	 * @phpstan-param array<int, Addable> $maps
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Addable>
	 */
	public static function addable( array $maps ): array {
		$found = array();
		foreach ( array_chunk( $maps, self::ADDABLE_CHUNK ) as $chunk ) {
			_prime_post_caches( array_values( array_unique( array_column( $chunk, 'host' ) ) ), false, false );
			foreach ( $chunk as $map ) {
				if ( self::can_add_to( $map['host'] ) ) {
					$found[] = $map;
				}
			}

			if ( count( $found ) >= self::MAX_ADDABLE ) {
				return array_slice( $found, 0, self::MAX_ADDABLE );
			}
		}

		return $found;
	}

	/**
	 * Whether the box offers a post's maps, letting go of the post if not.
	 *
	 * @param int $host The post.
	 * @return bool
	 */
	private static function can_add_to( int $host ): bool {
		if ( 'wp_block' !== get_post_type( $host ) && current_user_can( 'edit_post', $host ) ) {
			return true;
		}

		wp_cache_delete( $host, 'posts' );

		return false;
	}

	/**
	 * Where one map shows a Waypoint post: at each waypoint of the file linked
	 * to it, which a link in the block can take from the file, and at its own
	 * coordinates when added. A block link that repeats the file's counts as
	 * the file's: without it the file would link the post all the same, so
	 * removing it could not take the post off the map.
	 *
	 * @param array<string, mixed> $map      The map.
	 * @param int                  $waypoint The Waypoint post.
	 * @phpstan-param Map $map
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Place>
	 */
	private static function places( array $map, int $waypoint ): array {
		$places = array();
		foreach ( array_keys( $map['links'], $waypoint, true ) as $position ) {
			$in_file  = ( $map['file_links'][ $position ] ?? 0 ) === $waypoint;
			$places[] = array(
				'where'    => $in_file || ! isset( $map['own'][ $position ] ) ? 'file' : 'own',
				'position' => $position,
			);
		}

		if ( in_array( $waypoint, $map['added'], true ) ) {
			$places[] = array(
				'where'    => 'added',
				'position' => -1,
			);
		}

		return $places;
	}

	/**
	 * The rows the user may see. From a post they can neither edit nor read,
	 * only how many of its maps show the Waypoint post, and no title.
	 *
	 * @param array<int, array<int, array<string, mixed>>> $by_host Places, by post ID.
	 * @phpstan-param array<int, array<int, Found>> $by_host
	 * @return array{rows: array<int, array<string, mixed>>, hidden: int}
	 * @phpstan-return array{rows: array<int, Row>, hidden: int}
	 */
	private static function visible( array $by_host ): array {
		$rows   = array();
		$hidden = 0;
		foreach ( $by_host as $host => $places ) {
			$can_edit = current_user_can( 'edit_post', $host );
			if ( ! $can_edit && ! current_user_can( 'read_post', $host ) ) {
				$hidden += count( array_unique( array_column( $places, 'number' ) ) );
				continue;
			}

			foreach ( $places as $place ) {
				$rows[] = $place + array(
					'host'     => $host,
					'can_edit' => $can_edit,
				);
			}
		}

		return array(
			'rows'   => $rows,
			'hidden' => $hidden,
		);
	}

	/**
	 * One page of rows.
	 *
	 * @param array<int, array<string, mixed>> $rows The rows.
	 * @param int                              $page The page asked for.
	 * @return array{rows: array<int, array<string, mixed>>, page: int, pages: int} The rows of the
	 *         nearest page there is, its number and how many there are.
	 *
	 * @template T of array<string, mixed>
	 * @phpstan-param array<int, T> $rows
	 * @phpstan-return array{rows: array<int, T>, page: int, pages: int}
	 */
	public static function page( array $rows, int $page ): array {
		$pages = max( 1, (int) ceil( count( $rows ) / self::PER_PAGE ) );
		$page  = min( max( 1, $page ), $pages );

		return array(
			'rows'  => array_slice( $rows, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE ),
			'page'  => $page,
			'pages' => $pages,
		);
	}
}
