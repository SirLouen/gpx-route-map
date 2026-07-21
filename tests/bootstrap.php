<?php
/**
 * Test bootstrap for standalone tests.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-gpxstats.php';
require_once dirname( __DIR__ ) . '/includes/class-renderer.php';
require_once dirname( __DIR__ ) . '/includes/class-plugin.php';
