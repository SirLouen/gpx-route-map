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

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Register a filter callback in a test-local hook registry.
	 *
	 * The plugin documents seven filters; without somewhere to register one
	 * they could not be tested at all.
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback  Callback.
	 * @return void
	 */
	function add_filter( string $hook_name, callable $callback ): void {
		$GLOBALS['gpxrm_test_filters'][ $hook_name ][] = $callback;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Run any callbacks a test registered for this hook.
	 *
	 * @param string $hook_name Hook name.
	 * @param mixed  $value     Value to filter.
	 * @param mixed  ...$args   Extra arguments passed to the callbacks.
	 * @return mixed
	 */
	function apply_filters( string $hook_name, $value, ...$args ) {
		foreach ( $GLOBALS['gpxrm_test_filters'][ $hook_name ] ?? array() as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Return the text unchanged.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'add_settings_error' ) ) {
	/**
	 * Record a settings error so validation can be tested without WordPress.
	 *
	 * @param string $setting Option name.
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param string $type    Message type.
	 * @return void
	 */
	function add_settings_error( string $setting, string $code, string $message, string $type = 'error' ): void {
		$GLOBALS['gpxrm_test_settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Stand in for WordPress's wrapper around parse_url().
	 *
	 * @param string $url       URL to parse.
	 * @param int    $component Component to return.
	 * @return mixed
	 */
	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
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

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Return the text unchanged; escaping is WordPress's job, not the logic's.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Minimal stand-in for WordPress's attribute escaper.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-renderer.php';
require_once dirname( __DIR__ ) . '/includes/class-plugin.php';
