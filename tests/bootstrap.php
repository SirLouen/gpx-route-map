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
	 * Return the text unchanged, unless a test stands in a translation through
	 * the gettext_with_context filter, as WordPress's own _x() allows.
	 *
	 * @param string $text    Text to translate.
	 * @param string $context Disambiguating context.
	 * @param string $domain  Text domain.
	 * @return string
	 */
	function _x( string $text, string $context, string $domain = 'default' ): string {
		return (string) apply_filters( 'gettext_with_context', $text, $text, $context, $domain );
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
	 * Return the text unchanged, unless a test stands in a translation through
	 * the gettext filter, as WordPress's own __() allows.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		return (string) apply_filters( 'gettext', $text, $text, $domain );
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
	 * Like the real one, it leaves entities that are already in the text
	 * alone rather than encoding their ampersand again.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8', false );
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
	 * Escaping stand-in. Like WordPress's, it leaves an entity that is already
	 * in the text alone and escapes the "&" of one it does not recognise.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8', false );
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * Merge shortcode attributes over their defaults, as WordPress does:
	 * attributes without a default are dropped.
	 *
	 * @param array<string, mixed> $pairs     Defaults.
	 * @param mixed                $atts      Attributes given.
	 * @param string               $shortcode Shortcode name.
	 * @return array<string, mixed>
	 */
	function shortcode_atts( array $pairs, $atts, string $shortcode = '' ): array {
		$atts = (array) $atts;
		$out  = array();
		foreach ( $pairs as $name => $default ) {
			$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
		}
		return $out;
	}
}

if ( ! function_exists( 'selected' ) ) {
	/**
	 * Mark an option as WordPress's selected() does.
	 *
	 * @param mixed $selected One value to compare.
	 * @param mixed $current  The other value to compare.
	 * @param bool  $display  Whether to echo the attribute too.
	 * @return string
	 */
	function selected( $selected, $current = true, bool $display = true ): string {
		$result = (string) $selected === (string) $current ? " selected='selected'" : '';
		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $result;
	}
}

if ( ! function_exists( 'checked' ) ) {
	/**
	 * Mark a checkbox as WordPress's checked() does.
	 *
	 * @param mixed $checked One value to compare.
	 * @param mixed $current The other value to compare.
	 * @param bool  $display Whether to echo the attribute too.
	 * @return string
	 */
	function checked( $checked, $current = true, bool $display = true ): string {
		$result = (string) $checked === (string) $current ? " checked='checked'" : '';
		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $result;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/**
	 * Format a number as WordPress does in an English locale.
	 *
	 * @param float|int|string $number   Number to format.
	 * @param int              $decimals Decimal places.
	 * @return string
	 */
	function number_format_i18n( $number, int $decimals = 0 ): string {
		return number_format( (float) $number, $decimals );
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
	 * @param mixed $data  Data to encode.
	 * @param int   $flags json_encode() flags.
	 * @return string|false
	 */
	function wp_json_encode( $data, int $flags = 0 ) {
		return json_encode( $data, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
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
