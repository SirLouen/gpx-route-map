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

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * No-op stand-in; the renderer's capability and asset calls are not what
	 * these tests exercise.
	 *
	 * @param mixed ...$args Ignored.
	 * @return bool
	 */
	function current_user_can( ...$args ): bool {
		unset( $args );
		return false;
	}
}

if ( ! class_exists( '\WP_Block_Type_Registry' ) ) {
	/**
	 * Minimal registry so Renderer::assets_registered() reports a built plugin.
	 *
	 * Without it render() bails before emitting any markup, and the markup is
	 * exactly what the responsive-height assertions inspect.
	 */
	class WP_Block_Type_Registry {

		/**
		 * The shared instance.
		 *
		 * @return self
		 */
		public static function get_instance(): self {
			return new self();
		}

		/**
		 * Pretend every block is registered.
		 *
		 * @param string $name Block name.
		 * @return object
		 */
		public function get_registered( string $name ) {
			return (object) array( 'name' => $name );
		}
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escaping stand-in: these tests assert on structure, not on escaping.
	 *
	 * @param string $text Text to pass through.
	 * @return string
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Stand in for WordPress's non-display URL sanitizer.
	 *
	 * @param string $url URL to sanitize.
	 * @return string
	 */
	function esc_url_raw( string $url ): string {
		return $url;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Minimal stand-in for WordPress's URL escaper.
	 *
	 * @param string $url URL to escape.
	 * @return string
	 */
	function esc_url( string $url ): string {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Return the text unchanged; escaping is WordPress's job, not the logic's.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_attr__( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Stand in for WordPress's JSON encoder.
	 *
	 * @param mixed $data Data to encode.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * No-op: the renderer enqueues assets, which these tests do not exercise.
	 *
	 * @param string $handle Style handle.
	 * @return void
	 */
	function wp_enqueue_style( string $handle ): void {
		unset( $handle );
	}
}

if ( ! function_exists( 'generate_block_asset_handle' ) ) {
	/**
	 * Return a predictable handle without loading the block registry.
	 *
	 * @param string $block_name Block name.
	 * @param string $field_name Asset field.
	 * @return string
	 */
	function generate_block_asset_handle( string $block_name, string $field_name ): string {
		return str_replace( '/', '-', $block_name ) . '-' . $field_name;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-renderer.php';
require_once dirname( __DIR__ ) . '/includes/class-plugin.php';
