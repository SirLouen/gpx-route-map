<?php
/**
 * Server-side render for the gpx-route-map/map block.
 *
 * WordPress passes $attributes, $content and $block into this scope. All output
 * is escaped inside the shared renderer.
 *
 * @package GpxRouteMap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Gpxrm\Renderer' ) ) {
	return;
}

/**
 * Block attributes injected by WordPress at render time.
 *
 * @var array<string, mixed> $attributes
 */
$gpxrm_markup = \Gpxrm\Renderer::render(
	is_array( $attributes ) ? $attributes : array(),
	get_block_wrapper_attributes()
);

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer output is fully escaped internally.
echo $gpxrm_markup;
