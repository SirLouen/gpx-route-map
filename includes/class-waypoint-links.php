<?php
/**
 * The waypoints a block or a shortcode links to Waypoint posts.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Links read from a block's `waypointPosts` attribute or a shortcode's
 * `waypoints`, by each waypoint's position among the GPX file's <wpt>
 * elements, and the posts a block's `addedWaypoints` or a shortcode's
 * `waypoints` add to a map at their own coordinates. A GPX file's own links
 * are read by Gpx_File_Links.
 */
final class Waypoint_Links {

	/**
	 * A post ID from a stored, submitted or file value: a whole number above
	 * zero, else 0.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function post_id( $value ): int {
		if ( is_int( $value ) ) {
			return max( 0, $value );
		}

		if ( ! is_string( $value ) ) {
			return 0;
		}

		$value = trim( $value );

		return 1 === preg_match( '/^[0-9]{1,15}$/', $value ) ? (int) $value : 0;
	}

	/**
	 * The waypoints a block links to posts: its `waypointPosts` attribute,
	 * the post ID by each waypoint's position among the file's <wpt>
	 * elements, counting from 0.
	 *
	 * @param mixed $raw Raw attribute value.
	 * @return array<int, int> Post IDs by waypoint position, in order.
	 */
	public static function from_attribute( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$links = array();
		foreach ( $raw as $index => $post ) {
			// PHP turns a key such as "3" into the number already.
			$position = is_int( $index ) ? $index : -1;
			$id       = self::post_id( $post );
			if ( $position >= 0 && $id > 0 ) {
				$links[ $position ] = $id;
			}
		}

		ksort( $links );

		return array_slice( $links, 0, Waypoints::MAX_LINKS, true );
	}

	/**
	 * The waypoints a shortcode links to posts: its `waypoints` attribute, as
	 * "number:post ID" pairs separated by commas, numbering the file's
	 * waypoints from 1, e.g. "1:12, 3:40".
	 *
	 * @param string $value Attribute value.
	 * @return array<int, int> Post IDs by waypoint position, counting from 0.
	 */
	public static function from_shortcode( string $value ): array {
		$links = array();
		foreach ( explode( ',', $value ) as $pair ) {
			$parts = explode( ':', $pair );
			if ( 2 !== count( $parts ) ) {
				continue;
			}

			$number = self::post_id( $parts[0] );
			$id     = self::post_id( $parts[1] );
			if ( $number > 0 && $id > 0 ) {
				$links[ $number - 1 ] = $id;
			}
		}

		return self::from_attribute( $links );
	}

	/**
	 * The posts a block adds to its map, at their own coordinates: its
	 * `addedWaypoints` attribute, a list of post IDs. The attribute's type
	 * lets a bare string through, which is none.
	 *
	 * @param mixed $raw Raw attribute value.
	 * @return array<int, int> Post IDs, each once, in order.
	 */
	public static function added_from_attribute( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array_filter( array_map( array( self::class, 'post_id' ), array_values( $raw ) ) );

		return array_slice( array_values( array_unique( $ids ) ), 0, Waypoints::MAX_LINKS );
	}

	/**
	 * The posts a shortcode adds to its map: an @ before the post ID, among
	 * the pairs of its `waypoints` attribute, e.g. "1:12, @27".
	 *
	 * @param string $value Attribute value.
	 * @return array<int, int> Post IDs, each once, in order.
	 */
	public static function added_from_shortcode( string $value ): array {
		$ids = array();
		foreach ( explode( ',', $value ) as $token ) {
			$token = trim( $token );
			if ( '@' === substr( $token, 0, 1 ) ) {
				$ids[] = substr( $token, 1 );
			}
		}

		return self::added_from_attribute( $ids );
	}
}
