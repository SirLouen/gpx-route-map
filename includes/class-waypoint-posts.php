<?php
/**
 * The Waypoint posts a map links its waypoints to.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Waypoint posts a map links its waypoints to, and what it shows for
 * them: only for published waypoints without a password.
 *
 * @phpstan-import-type Marker from Waypoints
 */
final class Waypoint_Posts {

	/**
	 * Post IDs by waypoint position.
	 *
	 * @var array<int, int>
	 */
	private $links;

	/**
	 * The posts a map links its waypoints to.
	 *
	 * @param array<int, int> $links Post IDs by waypoint position.
	 */
	public function __construct( array $links ) {
		$this->links = $links;
	}

	/**
	 * What the map shows for each waypoint linked to a post it may show, by
	 * the waypoint's position.
	 *
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Marker>
	 */
	public function markers(): array {
		$by_id   = $this->markers_by_id();
		$markers = array();
		foreach ( $this->links as $index => $id ) {
			if ( isset( $by_id[ $id ] ) && array() !== $by_id[ $id ] ) {
				$markers[ $index ] = $by_id[ $id ];
			}
		}

		return $markers;
	}

	/**
	 * What the map shows for each linked post it may show, by post ID.
	 *
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Marker>
	 */
	private function markers_by_id(): array {
		$ids = array_values( array_unique( $this->links ) );
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

		$by_id = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$by_id[ $post->ID ] = ( new Waypoint_Post( $post ) )->marker();
			}
		}

		return $by_id;
	}
}
