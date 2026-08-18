<?php
/**
 * Plugin bootstrap: registers the block, shortcode and translations.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin into WordPress.
 */
class Plugin {

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'upload_mimes', array( $this, 'allow_gpx_upload' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_gpx_filetype_check' ), 10, 4 );
	}

	/**
	 * Load the plugin's bundled translations from /languages.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'gpx-route-map',
			false,
			dirname( plugin_basename( GPXRM_PLUGIN_DIR . 'gpx-route-map.php' ) ) . '/languages'
		);
	}

	/**
	 * Register the unit setting on Settings -> General.
	 *
	 * A single setting does not warrant its own admin page, so it joins the
	 * existing General screen.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'general',
			'gpxrm_units',
			array(
				'type'              => 'string',
				'default'           => 'metric',
				'sanitize_callback' => array( $this, 'sanitize_units' ),
				'show_in_rest'      => true,
			)
		);

		add_settings_field(
			'gpxrm_units',
			__( 'GPX map units', 'gpx-route-map' ),
			array( $this, 'render_units_field' ),
			'general',
			'default',
			array( 'label_for' => 'gpxrm_units' )
		);
	}

	/**
	 * Keep the stored unit system to a known value.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_units( $value ): string {
		return ( is_string( $value ) && 'imperial' === $value ) ? 'imperial' : 'metric';
	}

	/**
	 * Output the unit setting control.
	 *
	 * @return void
	 */
	public function render_units_field(): void {
		$current = Renderer::default_units();
		$choices = array(
			'metric'   => __( 'Metric (km / m)', 'gpx-route-map' ),
			'imperial' => __( 'Imperial (mi / ft)', 'gpx-route-map' ),
		);

		echo '<select name="gpxrm_units" id="gpxrm_units">';
		foreach ( $choices as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Units used for distance and elevation on GPX maps. Individual maps can override this.', 'gpx-route-map' ) . '</p>';
	}

	/**
	 * Allow .gpx uploads in the Media Library.
	 *
	 * @param array<string, string> $mimes Existing MIME types.
	 * @return array<string, string>
	 */
	public function allow_gpx_upload( array $mimes ): array {
		$mimes['gpx'] = 'application/gpx+xml';
		return $mimes;
	}

	/**
	 * Correct filetype detection for .gpx files.
	 *
	 * @param array<string, string|false> $data     File data (ext, type, proper_filename).
	 * @param string                      $file     Full path to the file.
	 * @param string                      $filename The name of the file.
	 * @param string[]|null               $mimes    Allowed MIME types keyed by extension.
	 * @return array<string, string|false>
	 */
	public function fix_gpx_filetype_check( array $data, string $file, string $filename, $mimes ): array {
		if ( '.gpx' !== strtolower( substr( $filename, -4 ) ) ) {
			return $data;
		}

		if ( ! self::looks_like_gpx( $file ) ) {
			return $data;
		}

		$data['ext']  = 'gpx';
		$data['type'] = 'application/gpx+xml';

		return $data;
	}

	/**
	 * Sniff the head of a file for a `<gpx` root element (allowing a BOM,
	 * XML prolog, comments and whitespace before it).
	 *
	 * @param string $file Full path to the file.
	 * @return bool
	 */
	public static function looks_like_gpx( string $file ): bool {
		if ( '' === $file || ! is_readable( $file ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Sniffing a local upload.
		$head = file_get_contents( $file, false, null, 0, 512 );
		if ( false === $head ) {
			return false;
		}

		return (bool) preg_match( '/<gpx[\s>]/', $head );
	}

	/**
	 * Register the block from its compiled metadata.
	 *
	 * @return void
	 */
	public function register_block(): void {
		$build = GPXRM_PLUGIN_DIR . 'build';

		if ( ! file_exists( $build . '/block.json' ) ) {
			// Not built yet (e.g. `pnpm run build` hasn't run). Skip quietly.
			return;
		}

		$block_type = register_block_type( $build );

		if ( $block_type instanceof \WP_Block_Type ) {
			foreach ( $block_type->editor_script_handles as $handle ) {
				wp_set_script_translations( $handle, 'gpx-route-map', GPXRM_PLUGIN_DIR . 'languages' );
			}
		}
	}

	/**
	 * Register the [gpx_route_map] shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode(): void {
		add_shortcode( 'gpx_route_map', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Shortcode handler.
	 *
	 * Supported attributes:
	 *   gpx       Attachment ID or absolute URL of the .gpx file.
	 *   id        Attachment ID (alias for a numeric gpx).
	 *   height    Map height in pixels (default 480).
	 *   stats     Show the stats bar (default true).
	 *   elevation Show the elevation profile (default true).
	 *   maxzoom   Maximum zoom level (default 17).
	 *   tile      Override raster tile URL template.
	 *   units     "metric" or "imperial" (defaults to the site setting).
	 *
	 * @param array<int|string, string>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'gpx'       => '',
				'id'        => '',
				'height'    => 480,
				'stats'     => 'true',
				'elevation' => 'true',
				'maxzoom'   => 17,
				'tile'      => '',
				'units'     => '',
			),
			is_array( $atts ) ? $atts : array(),
			'gpx_route_map'
		);

		$mapped = array(
			'height'        => (int) $atts['height'],
			'showStats'     => $atts['stats'],
			'showElevation' => $atts['elevation'],
			'maxZoom'       => (int) $atts['maxzoom'],
			'tileUrl'       => (string) $atts['tile'],
			'units'         => (string) $atts['units'],
		);

		if ( is_numeric( $atts['id'] ) ) {
			$mapped['gpxId'] = (int) $atts['id'];
		} elseif ( is_numeric( $atts['gpx'] ) ) {
			$mapped['gpxId'] = (int) $atts['gpx'];
		} else {
			$mapped['gpxUrl'] = (string) $atts['gpx'];
		}

		return Renderer::render( $mapped );
	}
}
