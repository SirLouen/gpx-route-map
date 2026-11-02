<?php
/**
 * What the REST API says about a Waypoint post's coordinates.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A read-only field that gives the block editor a Waypoint's coordinates, to
 * add it to a map and say how far it lies from the route.
 *
 * A field of its own, not registered meta: without the custom-fields support
 * registered meta never appears, and with it visitors get the values, even of
 * a post with a password.
 */
final class Waypoint_Rest {

	/**
	 * Register the hook.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'add_field' ) );
	}

	/**
	 * Add the field, while the feature is on.
	 *
	 * @return void
	 */
	public function add_field(): void {
		if ( ! Waypoints::enabled() ) {
			return;
		}

		register_rest_field(
			Waypoints::POST_TYPE,
			'gpxrm_coords',
			array(
				'get_callback' => array( $this, 'coords' ),
				'schema'       => array(
					'type'     => array( 'object', 'null' ),
					// In the view context, as the block's panel lists posts in it.
					'context'  => array( 'view' ),
					'readonly' => true,
				),
			)
		);
	}

	/**
	 * The field's value: a post's coordinates, for those who can write posts,
	 * unless it has a password. The route's own check keeps other people's
	 * drafts out.
	 *
	 * @param array<string, mixed> $post The post as the API prepared it.
	 * @return array{lat: float, lon: float}|null
	 */
	public function coords( array $post ): ?array {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return null;
		}

		$id     = Waypoint_Links::post_id( $post['id'] ?? 0 );
		$record = get_post( $id );

		return $record instanceof \WP_Post && '' === $record->post_password ? Waypoint_Coordinates::of( $id ) : null;
	}
}
