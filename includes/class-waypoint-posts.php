<?php
/**
 * The Waypoint posts a map links its waypoints to, or adds to it.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Waypoint posts a map links its waypoints to or adds at their own
 * coordinates, and what it shows for them: only for published waypoints
 * without a password.
 *
 * @phpstan-import-type Marker from Waypoints
 * @phpstan-import-type Added from Waypoints
 */
final class Waypoint_Posts {

	/**
	 * Post IDs by waypoint position.
	 *
	 * @var array<int, int>
	 */
	private $links;

	/**
	 * Post IDs added at their own coordinates, in order.
	 *
	 * @var array<int, int>
	 */
	private $added;

	/**
	 * What the map shows for each post, by post ID, once read.
	 *
	 * @var array<int, array{marker: array<string, mixed>, coordinates: array{lat: float, lon: float}|null}>|null
	 * @phpstan-var array<int, array{marker: Marker, coordinates: array{lat: float, lon: float}|null}>|null
	 */
	private $posts = null;

	/**
	 * The posts a map links its waypoints to, or adds to it.
	 *
	 * @param array<int, int> $links Post IDs by waypoint position.
	 * @param array<int, int> $added Post IDs added at their own coordinates.
	 */
	public function __construct( array $links, array $added = array() ) {
		$this->links = $links;
		$this->added = $added;
	}

	/**
	 * What the map shows for each waypoint linked to a post it may show, by
	 * the waypoint's position.
	 *
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Marker>
	 */
	public function markers(): array {
		$posts   = $this->posts();
		$markers = array();
		foreach ( $this->links as $index => $id ) {
			if ( isset( $posts[ $id ] ) && array() !== $posts[ $id ]['marker'] ) {
				$markers[ $index ] = $posts[ $id ]['marker'];
			}
		}

		return $markers;
	}

	/**
	 * What the map shows for each added post it may show, in order. Only for
	 * a post with coordinates, and whatever else it has: unlike a waypoint of
	 * the file, it has nothing to fall back on, so it always has a name.
	 *
	 * Where a waypoint of the file links to the same post, `at` holds those
	 * waypoints' positions: only the map knows which of them it draws, so it
	 * decides where the post goes.
	 *
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Added>
	 */
	public function added(): array {
		$posts = $this->posts();
		$added = array();
		foreach ( $this->added as $id ) {
			$coordinates = $posts[ $id ]['coordinates'] ?? null;
			if ( null === $coordinates ) {
				continue;
			}

			$entry = $coordinates + $posts[ $id ]['marker'] + array( 'name' => __( 'Waypoint', 'gpx-route-map' ) );
			$at    = array_keys( $this->links, $id, true );
			if ( array() !== $at ) {
				$entry['at'] = $at;
			}
			$added[] = $entry;
		}

		return $added;
	}

	/**
	 * What the map shows for each post, by post ID. One query for all of a
	 * map's posts, however it uses them.
	 *
	 * @return array<int, array{marker: array<string, mixed>, coordinates: array{lat: float, lon: float}|null}>
	 * @phpstan-return array<int, array{marker: Marker, coordinates: array{lat: float, lon: float}|null}>
	 */
	private function posts(): array {
		if ( null === $this->posts ) {
			$this->posts = $this->read_posts();
		}

		return $this->posts;
	}

	/**
	 * Read what the map shows for each post it may show.
	 *
	 * @return array<int, array{marker: array<string, mixed>, coordinates: array{lat: float, lon: float}|null}>
	 * @phpstan-return array<int, array{marker: Marker, coordinates: array{lat: float, lon: float}|null}>
	 */
	private function read_posts(): array {
		$ids = array_values( array_unique( array_merge( array_values( $this->links ), $this->added ) ) );
		if ( array() === $ids ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Waypoints::POST_TYPE,
				'post_status'            => 'publish',
				'post__in'               => $ids,
				'posts_per_page'         => count( $ids ),
				'has_password'           => false,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		update_post_thumbnail_cache( $query );

		$posts = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$waypoint           = new Waypoint_Post( $post );
				$posts[ $post->ID ] = array(
					'marker'      => $waypoint->marker(),
					'coordinates' => $waypoint->coordinates(),
				);
			}
		}

		return $posts;
	}
}
