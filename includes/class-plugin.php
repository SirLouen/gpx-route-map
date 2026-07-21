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
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_filter( 'upload_mimes', array( $this, 'allow_gpx_upload' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_gpx_filetype_check' ), 10, 4 );
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

		register_block_type( $build );
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
