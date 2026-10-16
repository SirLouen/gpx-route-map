<?php
/**
 * Plugin Name:       GPX Route Map
 * Plugin URI:        https://github.com/SirLouen/gpx-route-map
 * Description:       Attach a GPX track to any post or page and render it as an interactive OpenStreetMap map with waypoints, distance and an elevation profile.
 * Version:           2.0.0
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            SirLouen <sir.louen@gmail.com>
 * Author URI:        https://github.com/SirLouen
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gpx-route-map
 * Domain Path:       /languages
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GPXRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once GPXRM_PLUGIN_DIR . 'includes/class-renderer.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-dashicons.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-gpx-link-reader.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoints.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoint-links.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoint-values.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoint-description.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-gpx-file-links.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoint-marker.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoint-post.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoint-posts.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoint-map-markers.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoint-settings.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoint-meta-box.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoint-columns.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-waypoint-edit-screen.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Boot the plugin.
 *
 * @return void
 */
function gpxrm_bootstrap(): void {
	( new \Gpxrm\Plugin() )->register();
}

gpxrm_bootstrap();
