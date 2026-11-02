<?php
/**
 * A stand-in for WP_Query, for the queries a map makes for its Waypoint posts.
 * Required by bootstrap.php.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_Query' ) ) {
	/**
	 * Finds, among the posts a test made, those without a password that are
	 * asked for, and keeps what each query asked for in
	 * $GLOBALS['gpxrm_test_queries'].
	 */
	class WP_Query {

		/**
		 * The posts found.
		 *
		 * @var array<int, WP_Post>
		 */
		public $posts = array();

		/**
		 * A query.
		 *
		 * @param array{post__in: array<int, int>} $args Query.
		 */
		public function __construct( array $args ) {
			$GLOBALS['gpxrm_test_queries'][] = $args['post__in'];
			foreach ( $args['post__in'] as $id ) {
				$post = $GLOBALS['gpxrm_test_posts'][ $id ] ?? null;
				if ( null !== $post && '' === $post->post_password ) {
					$this->posts[] = $post;
				}
			}
		}
	}
}
