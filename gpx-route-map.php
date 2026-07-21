<?php
/**
 * Plugin Name:       GPX Route Map
 * Plugin URI:        https://github.com/SirLouen/gpx-route-map
 * Description:       Attach a GPX track to any post or page and render it as an interactive OpenStreetMap map with waypoints, distance and an elevation profile.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            SirLouen <sir.louen@gmail.com>
 * Author URI:        https://github.com/SirLouen
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gpx-route-map
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GPXRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once GPXRM_PLUGIN_DIR . 'includes/class-gpxstats.php';
require_once GPXRM_PLUGIN_DIR . 'includes/class-renderer.php';
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
