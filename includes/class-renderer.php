<?php
/**
 * Shared HTML renderer for the block and the shortcode.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the map markup and enqueues the frontend assets.
 */
class Renderer {

	const BLOCK_NAME = 'gpx-route-map/map';

	/**
	 * Default raster tile template. Filterable so site owners can point the
	 * plugin at their own tile server (see the OpenStreetMap tile usage policy).
	 *
	 * @return string
	 */
	public static function default_tile_url(): string {
		$default = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
		$url     = apply_filters( 'gpxrm_tile_url', $default );
		return is_string( $url ) ? $url : $default;
	}

	/**
	 * Default attribution HTML shown in the map's attribution control.
	 *
	 * @return string
	 */
	public static function default_attribution(): string {
		$default = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
		$value   = apply_filters( 'gpxrm_tile_attribution', $default );
		return is_string( $value ) ? $value : $default;
	}

	/**
	 * Render the map for a set of block/shortcode attributes.
	 *
	 * @param array<string, mixed> $atts               Raw attributes.
	 * @param string               $wrapper_attributes Pre-built wrapper attributes (block context).
	 * @return string
	 */
	public static function render( array $atts, string $wrapper_attributes = '' ): string {
		$a = self::normalize( $atts );

		if ( '' === $a['gpx_url'] ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<div class="gpxrm-notice">' . esc_html__( 'GPX Route Map: no GPX file selected.', 'gpx-route-map' ) . '</div>';
			}
			return '';
		}

		if ( ! self::assets_registered() ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<div class="gpxrm-notice">' . esc_html__( 'GPX Route Map: plugin assets are not built. Run "pnpm install && pnpm run build" in the plugin directory.', 'gpx-route-map' ) . '</div>';
			}
			return '';
		}

		self::enqueue_assets();

		if ( '' === $wrapper_attributes ) {
			$wrapper_attributes = 'class="wp-block-gpx-route-map-map gpxrm-shortcode"';
		}

		$stats = $a['show_stats'] ? $a['stats'] : null;

		$map = sprintf(
			'<div class="gpxrm-map" style="height:%1$dpx" data-gpxrm-gpx="%2$s" data-gpxrm-tile-url="%3$s" data-gpxrm-attribution="%4$s" data-gpxrm-max-zoom="%5$d" data-gpxrm-i18n="%6$s" role="application" aria-label="%7$s">%8$s</div>',
			$a['height'],
			esc_url( $a['gpx_url'] ),
			esc_attr( '' !== $a['tile_url'] ? $a['tile_url'] : self::default_tile_url() ),
			esc_attr( self::default_attribution() ),
			$a['max_zoom'],
			esc_attr( self::view_messages_json() ),
			esc_attr__( 'Interactive route map', 'gpx-route-map' ),
			self::placeholder_html()
		);

		$stats_html     = $a['show_stats'] ? self::stats_html( $stats ) : '';
		$elevation_html = $a['show_elevation'] ? self::elevation_html() : '';

		return sprintf(
			'<div %1$s><div class="gpxrm">%2$s%3$s%4$s</div></div>',
			$wrapper_attributes,
			$map,
			$stats_html,
			$elevation_html
		);
	}

	/**
	 * Normalize block attributes and shortcode atts into one shape.
	 *
	 * @param array<string, mixed> $atts Raw attributes.
	 * @return array{gpx_url: string, height: int, show_stats: bool, show_elevation: bool, max_zoom: int, tile_url: string, stats: array{distance: float, gain: float, loss: float, max: float, waypoints: int}|null}
	 */
	private static function normalize( array $atts ): array {
		$gpx_url = '';

		$attachment_id = ( isset( $atts['gpxId'] ) && is_numeric( $atts['gpxId'] ) ) ? (int) $atts['gpxId'] : 0;
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( is_string( $url ) ) {
				$gpx_url = $url;
			}
		}

		if ( '' === $gpx_url && isset( $atts['gpxUrl'] ) && is_string( $atts['gpxUrl'] ) && '' !== $atts['gpxUrl'] ) {
			$gpx_url = esc_url_raw( $atts['gpxUrl'] );
		}

		$height   = ( isset( $atts['height'] ) && is_numeric( $atts['height'] ) ) ? (int) $atts['height'] : 480;
		$max_zoom = ( isset( $atts['maxZoom'] ) && is_numeric( $atts['maxZoom'] ) ) ? (int) $atts['maxZoom'] : 17;
		$tile_url = self::sanitize_tile_url( $atts['tileUrl'] ?? '' );

		return array(
			'gpx_url'        => $gpx_url,
			'height'         => self::clamp( $height, 200, 1200 ),
			'show_stats'     => self::to_bool( $atts['showStats'] ?? true ),
			'show_elevation' => self::to_bool( $atts['showElevation'] ?? true ),
			'max_zoom'       => self::clamp( $max_zoom, 1, 22 ),
			'tile_url'       => $tile_url,
			'stats'          => self::normalize_stats( $atts['stats'] ?? null ),
		);
	}

	/**
	 * Validate a custom tile URL template.
	 *
	 * @param mixed $raw Raw attribute value.
	 * @return string The unmodified template, or '' when invalid.
	 */
	public static function sanitize_tile_url( $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$raw = trim( $raw );
		if ( '' === $raw || ! preg_match( '#^https?://#i', $raw ) ) {
			return '';
		}
		$probe = str_replace( array( '{z}', '{x}', '{y}', '{r}', '{s}' ), '0', $raw );
		if ( false === filter_var( $probe, FILTER_VALIDATE_URL ) ) {
			return '';
		}
		return $raw;
	}

	/**
	 * Validate the editor-computed stats stored on the block.
	 *
	 * @param mixed $raw Raw `stats` attribute value.
	 * @return array{distance: float, gain: float, loss: float, max: float, waypoints: int}|null
	 */
	private static function normalize_stats( $raw ): ?array {
		if ( ! is_array( $raw ) || ! isset( $raw['distance'] ) || ! is_numeric( $raw['distance'] ) ) {
			return null;
		}

		$to_float = static function ( $value ): float {
			return is_numeric( $value ) ? (float) $value : 0.0;
		};

		return array(
			'distance'  => $to_float( $raw['distance'] ),
			'gain'      => $to_float( $raw['gain'] ?? 0 ),
			'loss'      => $to_float( $raw['loss'] ?? 0 ),
			'max'       => $to_float( $raw['max'] ?? 0 ),
			'waypoints' => (int) $to_float( $raw['waypoints'] ?? 0 ),
		);
	}

	/**
	 * Loading placeholder shown until the map script initializes.
	 *
	 * @return string
	 */
	private static function placeholder_html(): string {
		return sprintf(
			'<div class="gpxrm-placeholder" aria-live="polite"><span class="gpxrm-spinner" role="status"><span class="screen-reader-text">%1$s</span></span><span class="gpxrm-hint" aria-hidden="true">%2$s</span></div>',
			esc_html__( 'Loading map…', 'gpx-route-map' ),
			esc_html__( 'Scroll or tap to load the interactive map', 'gpx-route-map' )
		);
	}

	/**
	 * JSON of the front-end view's user-facing strings, localized server-side.
	 *
	 * @return string
	 */
	private static function view_messages_json(): string {
		return (string) wp_json_encode(
			array(
				'load'     => __( 'Could not load GPX file.', 'gpx-route-map' ),
				'cors'     => __( 'Could not load GPX file: its host does not allow cross-origin (CORS) requests. Upload the file to this site instead.', 'gpx-route-map' ),
				'invalid'  => __( 'Invalid GPX file.', 'gpx-route-map' ),
				'nopoints' => __( 'No track or route points found in GPX file.', 'gpx-route-map' ),
			)
		);
	}

	/**
	 * Stats bar markup. Values are computed once in the editor and stored on the
	 * block; the front-end JS refreshes them live after it parses the GPX.
	 *
	 * @param array{distance: float, gain: float, loss: float, max: float, waypoints: int}|null $stats Stored stats or null.
	 * @return string
	 */
	private static function stats_html( ?array $stats ): string {
		$rows = array(
			'distance'  => array( __( 'Distance', 'gpx-route-map' ), null === $stats ? '—' : number_format_i18n( $stats['distance'], 2 ) . ' km' ),
			'gain'      => array( __( 'Elevation gain', 'gpx-route-map' ), null === $stats ? '—' : '+' . number_format_i18n( round( $stats['gain'] ) ) . ' m' ),
			'loss'      => array( __( 'Elevation loss', 'gpx-route-map' ), null === $stats ? '—' : '−' . number_format_i18n( round( $stats['loss'] ) ) . ' m' ),
			'max'       => array( __( 'Max elevation', 'gpx-route-map' ), null === $stats ? '—' : number_format_i18n( round( $stats['max'] ) ) . ' m' ),
			'waypoints' => array( __( 'Waypoints', 'gpx-route-map' ), null === $stats ? '—' : number_format_i18n( $stats['waypoints'] ) ),
		);

		$items = '';
		foreach ( $rows as $key => $row ) {
			$items .= sprintf(
				'<div class="gpxrm-stat"><dt class="gpxrm-stat-label">%1$s</dt><dd class="gpxrm-stat-value" data-gpxrm-stat="%2$s">%3$s</dd></div>',
				esc_html( $row[0] ),
				esc_attr( $key ),
				esc_html( $row[1] )
			);
		}

		return '<dl class="gpxrm-stats">' . $items . '</dl>';
	}

	/**
	 * Elevation profile markup.
	 *
	 * @return string
	 */
	private static function elevation_html(): string {
		return sprintf(
			'<figure class="gpxrm-elevation"><figcaption class="gpxrm-elevation-heading">%1$s</figcaption><canvas class="gpxrm-elevation-canvas" data-gpxrm-elevation role="img" aria-label="%2$s"></canvas></figure>',
			esc_html__( 'Elevation profile', 'gpx-route-map' ),
			esc_attr__( 'Elevation profile along the route', 'gpx-route-map' )
		);
	}

	/**
	 * Whether the block registered on init.
	 *
	 * @return bool
	 */
	private static function assets_registered(): bool {
		return class_exists( '\WP_Block_Type_Registry' )
			&& null !== \WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK_NAME );
	}

	/**
	 * Enqueue the shared view script module and style registered by the block.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if ( ! function_exists( 'generate_block_asset_handle' ) ) {
			return;
		}
		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module( generate_block_asset_handle( self::BLOCK_NAME, 'viewScriptModule' ) );
		}
		wp_enqueue_style( generate_block_asset_handle( self::BLOCK_NAME, 'style' ) );
	}

	/**
	 * Coerce a mixed value to boolean, honoring shortcode "false"/"0"/"no".
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) ) {
			return ! in_array( strtolower( trim( $value ) ), array( 'false', '0', 'no', 'off', '' ), true );
		}
		return (bool) $value;
	}

	/**
	 * Clamp a number to an inclusive range.
	 *
	 * @param int $value Value.
	 * @param int $min   Minimum.
	 * @param int $max   Maximum.
	 * @return int
	 */
	private static function clamp( int $value, int $min, int $max ): int {
		return max( $min, min( $max, $value ) );
	}
}
