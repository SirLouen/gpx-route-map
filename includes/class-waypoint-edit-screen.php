<?php
/**
 * The screens a Waypoint post is edited and listed on.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets up the screens Waypoint posts are edited and listed on: the classic
 * edit screen with Waypoint_Meta_Box, the list with Waypoint_Columns,
 * and the styles and script both use. Also loads the icon font into the block
 * editor's canvas, where the live preview draws icon markers.
 */
final class Waypoint_Edit_Screen {

	/**
	 * Register the hooks of the screens Waypoint posts are edited and listed
	 * on.
	 *
	 * @return void
	 */
	public function register(): void {
		$box     = new Waypoint_Meta_Box();
		$columns = new Waypoint_Columns();

		add_filter( 'use_block_editor_for_post_type', array( $this, 'use_block_editor' ), 10, 2 );
		add_action( 'add_meta_boxes_' . Waypoints::POST_TYPE, array( $box, 'add' ) );
		add_action( 'save_post_' . Waypoints::POST_TYPE, array( $box, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_filter( 'manage_' . Waypoints::POST_TYPE . '_posts_columns', array( $columns, 'columns' ) );
		add_action( 'manage_' . Waypoints::POST_TYPE . '_posts_custom_column', array( $columns, 'column' ), 10, 2 );
		add_action( 'enqueue_block_assets', array( $this, 'editor_canvas_assets' ) );
	}

	/**
	 * Edit waypoints on the classic screen.
	 *
	 * @param mixed $use_block_editor Whether the block editor would be used.
	 * @param mixed $post_type        Post type.
	 * @return bool
	 */
	public function use_block_editor( $use_block_editor, $post_type ): bool {
		return Waypoints::POST_TYPE === $post_type ? false : (bool) $use_block_editor;
	}

	/**
	 * The styles of the waypoint screens, and on the edit screen, the
	 * settings box's script: the colour pickers, the icon search, and a
	 * preview and an icon list that follow the choices.
	 *
	 * @param string $hook The admin page being loaded.
	 * @return void
	 */
	public function admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( null === $screen || Waypoints::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$style = $this->built( 'waypoint-edit.css' );
		if ( null !== $style ) {
			wp_enqueue_style( 'gpxrm-waypoints-admin', $style[0], array(), $style[1] );
			wp_style_add_data( 'gpxrm-waypoints-admin', 'rtl', 'replace' );
		}

		$script = $this->built( 'waypoint-edit.js' );
		if ( null !== $script && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'gpxrm-waypoint-edit', $script[0], array( 'jquery', 'wp-color-picker' ), $script[1], true );
		}
	}

	/**
	 * The address of a file bin/build.mjs builds from src/admin/, and its
	 * version, the time it was built.
	 *
	 * @param string $file The file's name in build/.
	 * @return array{0: string, 1: string}|null Address and version, or null
	 *                                          without a build.
	 */
	private function built( string $file ): ?array {
		$path = GPXRM_PLUGIN_DIR . 'build/' . $file;
		$time = is_readable( $path ) ? filemtime( $path ) : false;

		return false === $time ? null : array( plugins_url( 'build/' . $file, GPXRM_PLUGIN_DIR . 'gpx-route-map.php' ), (string) $time );
	}

	/**
	 * Load the icon font into the block editor's canvas, where the live
	 * preview draws icon markers. The front end loads it with the first map
	 * that needs it (see Renderer::render()).
	 *
	 * @return void
	 */
	public function editor_canvas_assets(): void {
		if ( is_admin() && Waypoints::enabled() ) {
			wp_enqueue_style( 'dashicons' );
		}
	}
}
