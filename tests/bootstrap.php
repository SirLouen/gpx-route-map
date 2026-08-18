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

/**
 * Minimal stubs so pure helpers can be unit tested without loading WordPress.
 */
if ( ! function_exists( '_x' ) ) {
	/**
	 * Return the text unchanged.
	 *
	 * @param string $text    Text to translate.
	 * @param string $context Disambiguating context.
	 * @param string $domain  Text domain.
	 * @return string
	 */
	function _x( string $text, string $context, string $domain = 'default' ): string {
		unset( $context, $domain );
		return $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Return the value unchanged; no hooks exist in these tests.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  $value     Value to filter.
	 * @return mixed
	 */
	function apply_filters( string $hook_name, $value ) {
		unset( $hook_name );
		return $value;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Read from a test-controlled option store.
	 *
	 * @param string $option        Option name.
	 * @param mixed  $default_value Fallback when unset.
	 * @return mixed
	 */
	function get_option( string $option, $default_value = false ) {
		return $GLOBALS['gpxrm_test_options'][ $option ] ?? $default_value;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-renderer.php';
require_once dirname( __DIR__ ) . '/includes/class-plugin.php';
