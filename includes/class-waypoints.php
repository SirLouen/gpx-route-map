<?php
/**
 * Waypoint posts: a description, a photo or an icon for the waypoints of GPX
 * files.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The opt-in Waypoint post type: the switch that turns it on, its post type,
 * and the constants its parts share. Waypoint_Edit_Screen edits its posts,
 * Waypoint_Map_Markers tells a map what to show for them, and Waypoint_Links
 * and Gpx_File_Links find which of a map's waypoints link to which post.
 *
 * A map links a waypoint of its GPX file to a Waypoint post in two ways: its
 * block picks one for it, by the waypoint's position in the file, or the file
 * itself names the post, inside the waypoint's <extensions>. A pick made in
 * the block wins over the file.
 *
 * @phpstan-type Photo array{src: string, width: int, height: int, alt: string, srcset?: string}
 * @phpstan-type Marker array{name?: string, desc?: string, photo?: Photo, marker?: string, thumb?: string, icon?: string, color?: string, background?: string}
 */
class Waypoints {

	const POST_TYPE = 'gpxrm_waypoint';

	/**
	 * The setting that turns the feature on. Off until a site turns it on.
	 */
	const OPTION = 'gpxrm_waypoints';

	/**
	 * The namespace of the element a GPX file links a waypoint with, inside
	 * the waypoint's <extensions>, which GPX keeps for other namespaces:
	 * <gpxrm:waypoint xmlns:gpxrm="…">123</gpxrm:waypoint>. Mirrored in
	 * src/view/map-core.ts.
	 */
	const GPX_NAMESPACE = 'https://wordpress.org/plugins/gpx-route-map/';

	/**
	 * The local name of that element.
	 */
	const GPX_ELEMENT = 'waypoint';

	const META_DESCRIPTION = '_gpxrm_description';
	const META_MARKER      = '_gpxrm_marker';
	const META_ICON        = '_gpxrm_icon';
	const META_COLOR       = '_gpxrm_icon_color';
	const META_BACKGROUND  = '_gpxrm_icon_background';

	/**
	 * Where a GPX attachment keeps the links read from its file.
	 */
	const META_FILE_LINKS = '_gpxrm_file_links';

	/**
	 * The markers a post can choose. Anything else keeps the marker the map
	 * gives the waypoint from its GPX file.
	 */
	const MARKERS = array( 'image', 'icon' );

	const DEFAULT_ICON = 'location';

	/**
	 * An icon marker's colours when none is chosen, as the map draws it:
	 * white on the track's colour (TRACK_COLOR in src/view/map-core.ts).
	 */
	const DEFAULT_COLOR      = '#ffffff';
	const DEFAULT_BACKGROUND = '#2e7d32';

	/**
	 * The most waypoints one map links. Far beyond any real file, it only
	 * bounds what a hand-written attribute or file can ask the server for.
	 */
	const MAX_LINKS = 1000;

	const NONCE        = 'gpxrm_waypoint_nonce';
	const NONCE_ACTION = 'gpxrm_waypoint_save';

	/**
	 * Register the hooks. Each one checks whether the feature is on when it
	 * runs, so turning it on or off takes effect on the next request.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		( new Waypoint_Edit_Screen() )->register();
	}

	/**
	 * Whether the site has turned Waypoint posts on.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$stored = get_option( self::OPTION, false );

		// update_option() stores true as '1'.
		return true === $stored || 1 === $stored || '1' === $stored;
	}

	/**
	 * Register the post type, when the feature is on.
	 *
	 * Not public: a waypoint has no page of its own, only what maps show for
	 * it. It is in the REST API so the block editor can list the waypoints a
	 * map can link to, and edited on the classic screen, whose boxes are all
	 * it needs.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		if ( ! self::enabled() ) {
			return;
		}

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Waypoints', 'gpx-route-map' ),
					'singular_name'         => __( 'Waypoint', 'gpx-route-map' ),
					'menu_name'             => __( 'Waypoints', 'gpx-route-map' ),
					'all_items'             => __( 'All Waypoints', 'gpx-route-map' ),
					'add_new'               => __( 'Add Waypoint', 'gpx-route-map' ),
					'add_new_item'          => __( 'Add Waypoint', 'gpx-route-map' ),
					'edit_item'             => __( 'Edit Waypoint', 'gpx-route-map' ),
					'new_item'              => __( 'New Waypoint', 'gpx-route-map' ),
					'search_items'          => __( 'Search Waypoints', 'gpx-route-map' ),
					'not_found'             => __( 'No waypoints found.', 'gpx-route-map' ),
					'not_found_in_trash'    => __( 'No waypoints found in Trash.', 'gpx-route-map' ),
					'item_published'        => __( 'Waypoint published.', 'gpx-route-map' ),
					'item_updated'          => __( 'Waypoint updated.', 'gpx-route-map' ),
					'featured_image'        => __( 'Photo', 'gpx-route-map' ),
					'set_featured_image'    => __( 'Set photo', 'gpx-route-map' ),
					'remove_featured_image' => __( 'Remove photo', 'gpx-route-map' ),
					'use_featured_image'    => __( 'Use as photo', 'gpx-route-map' ),
				),
				'description'         => __( 'Waypoints that GPX maps link to, each with its own description, photo or icon.', 'gpx-route-map' ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-location',
				'supports'            => array( 'title', 'thumbnail' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'rewrite'             => false,
				'query_var'           => false,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'delete_with_user'    => false,
			)
		);

		// The photo is the post's featured image, whose box only shows where
		// the theme supports one. This adds the post type to whatever the
		// theme declared, and changes nothing when it supports every type.
		add_theme_support( 'post-thumbnails', array( self::POST_TYPE ) );
	}
}
