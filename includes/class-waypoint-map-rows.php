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
 * @phpstan-type Row array{where: string, position: int, host: int, number: int, of: int, file: string, can_edit: bool}
 */
final class Waypoint_Map_Rows {

	/**
	 * How many rows one page of the box shows.
	 */
	const PER_PAGE = 100;

	/**
	 * The rows for a Waypoint post, from every post holding a map.
	 *
	 * @param int $waypoint The Waypoint post.
	 * @return array{rows: array<int, array<string, mixed>>, hidden: int, unreadable: int}
	 * @phpstan-return array{rows: array<int, Row>, hidden: int, unreadable: int}
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
	 * @return array{rows: array<int, array<string, mixed>>, hidden: int, unreadable: int} `hidden`
	 *         counts maps in posts the user cannot read, and `unreadable` posts that could not be
	 *         read at all.
	 * @phpstan-return array{rows: array<int, Row>, hidden: int, unreadable: int}
	 */
	public static function from_contents( int $waypoint, iterable $contents, ?Post_Maps $reader = null ): array {
		$reader     = $reader ?? new Post_Maps();
		$by_host    = array();
		$unreadable = 0;
		foreach ( $contents as $host => $content ) {
			$maps = $reader->read( $content );
			if ( null === $maps ) {
				++$unreadable;
				continue;
			}

			$places = self::places_in( $maps, $waypoint );
			if ( array() !== $places ) {
				$by_host[ $host ] = $places;
			}
		}

		// Otherwise each host below is a query of its own.
		_prime_post_caches( array_keys( $by_host ), false, false );

		return self::visible( $by_host ) + array( 'unreadable' => $unreadable );
	}

	/**
	 * The places a post's maps show a Waypoint post. A map with no file shows
	 * nothing, so it has none.
	 *
	 * @param array<int, array<string, mixed>> $maps     The post's maps.
	 * @param int                              $waypoint The Waypoint post.
	 * @phpstan-param array<int, Map> $maps
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, array{where: string, position: int, number: int, of: int, file: string}>
	 */
	private static function places_in( array $maps, int $waypoint ): array {
		$found = array();
		foreach ( array_values( $maps ) as $index => $map ) {
			if ( '' === $map['file_url'] ) {
				continue;
			}

			foreach ( self::places( $map, $waypoint ) as $place ) {
				$found[] = $place + array(
					'number' => $index + 1,
					'of'     => count( $maps ),
					'file'   => $map['file_url'],
				);
			}
		}

		return $found;
	}

	/**
	 * Where one map shows a Waypoint post: at each waypoint of the file linked
	 * to it, which a link in the block can take from the file, and at its own
	 * coordinates when added.
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
			$places[] = array(
				'where'    => isset( $map['own'][ $position ] ) ? 'own' : 'file',
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
	 * @phpstan-param array<int, array<int, array{where: string, position: int, number: int, of: int, file: string}>> $by_host
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
