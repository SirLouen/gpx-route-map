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
	 * Every stat the bar can show, in the order they are displayed.
	 */
	const STAT_FIELDS = array( 'distance', 'gain', 'loss', 'max', 'waypoints' );

	/**
	 * Map height bounds, in pixels. Below the minimum the controls and the
	 * elevation profile stop fitting; above the maximum a map stops being a
	 * sensible thing to put in a post.
	 */
	const MIN_HEIGHT     = 200;
	const MAX_HEIGHT     = 1200;
	const DEFAULT_HEIGHT = 480;

	/**
	 * OpenStreetMap's standard tiles, used when no provider is configured.
	 */
	const OSM_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

	/**
	 * Attribution for OpenStreetMap's own tiles.
	 */
	const OSM_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

	/**
	 * Attribution Thunderforest requires. Their terms ask for credit to both
	 * Thunderforest and OpenStreetMap as working links, and say it may not be
	 * removed, so it is applied automatically rather than left to the site owner.
	 */
	const THUNDERFOREST_ATTRIBUTION = 'Maps &copy; <a href="https://www.thunderforest.com">Thunderforest</a>, Data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>';

	/**
	 * The Thunderforest styles offered, keyed by their tile API slug.
	 *
	 * Note the OpenCycleMap slug is "cycle"; "opencyclemap" is only the name of
	 * its page on their site and is not a valid tile style.
	 *
	 * @return array<string, string>
	 */
	public static function thunderforest_styles(): array {
		return array(
			'outdoors'  => __( 'Outdoors (hiking)', 'gpx-route-map' ),
			'cycle'     => __( 'OpenCycleMap (cycling)', 'gpx-route-map' ),
			'landscape' => __( 'Landscape (terrain)', 'gpx-route-map' ),
			'atlas'     => __( 'Atlas (general)', 'gpx-route-map' ),
		);
	}

	/**
	 * The configured tile provider.
	 *
	 * @return string 'osm' or 'thunderforest'.
	 */
	public static function tile_provider(): string {
		$stored = get_option( 'gpxrm_tile_provider', 'osm' );
		return ( is_string( $stored ) && 'thunderforest' === $stored ) ? 'thunderforest' : 'osm';
	}

	/**
	 * The stored Thunderforest API key.
	 *
	 * @return string
	 */
	public static function thunderforest_key(): string {
		$stored = get_option( 'gpxrm_thunderforest_key', '' );
		return is_string( $stored ) ? trim( $stored ) : '';
	}

	/**
	 * The stored Thunderforest style slug.
	 *
	 * @return string
	 */
	public static function thunderforest_style(): string {
		$stored = get_option( 'gpxrm_thunderforest_style', 'outdoors' );
		$styles = self::thunderforest_styles();

		return ( is_string( $stored ) && isset( $styles[ $stored ] ) ) ? $stored : 'outdoors';
	}

	/**
	 * Default raster tile template. Filterable so site owners can point the
	 * plugin at their own tile server (see the OpenStreetMap tile usage policy).
	 *
	 * @return string
	 */
	public static function default_tile_url(): string {
		$default = self::OSM_TILE_URL;

		if ( 'thunderforest' === self::tile_provider() ) {
			$key = self::thunderforest_key();
			// Without a key the request would just 401, so fall back to OSM and
			// leave the site with a working map.
			if ( '' !== $key ) {
				$default = sprintf(
					// {ratio} is MapLibre's retina placeholder and expands to
					// "@2x"; Leaflet's {r} would be sent through literally.
					'https://api.thunderforest.com/%1$s/{z}/{x}/{y}{ratio}.png?apikey=%2$s',
					self::thunderforest_style(),
					rawurlencode( $key )
				);
			}
		}

		$url = apply_filters( 'gpxrm_tile_url', $default );
		return is_string( $url ) ? $url : $default;
	}

	/**
	 * Whether a tile template points at Thunderforest.
	 *
	 * @param string $tile_url Tile URL template.
	 * @return bool
	 */
	private static function is_thunderforest_url( string $tile_url ): bool {
		$host = (string) wp_parse_url( $tile_url, PHP_URL_HOST );

		return '' !== $host && ( 'thunderforest.com' === $host || str_ends_with( $host, '.thunderforest.com' ) );
	}

	/**
	 * Attribution HTML for the tiles actually in use.
	 *
	 * Derived from the URL rather than the site setting so that a map pointed at
	 * Thunderforest by hand still carries the credit their terms require.
	 *
	 * @param string $tile_url Tile URL template in use.
	 * @return string
	 */
	public static function attribution_for( string $tile_url ): string {
		$default = self::is_thunderforest_url( $tile_url )
			? self::THUNDERFOREST_ATTRIBUTION
			: self::OSM_ATTRIBUTION;

		/**
		 * Filters the attribution HTML shown on the map.
		 *
		 * @param string $default  Attribution HTML.
		 * @param string $tile_url Tile URL template in use.
		 */
		return apply_filters( 'gpxrm_tile_attribution', $default, $tile_url );
	}

	/**
	 * Default attribution HTML shown in the map's attribution control.
	 *
	 * @return string
	 */
	public static function default_attribution(): string {
		return self::attribution_for( self::default_tile_url() );
	}

	/**
	 * The site-wide unit system, filterable.
	 *
	 * @return string 'metric' or 'imperial'.
	 */
	public static function default_units(): string {
		$stored = get_option( 'gpxrm_units', 'metric' );
		$value  = ( is_string( $stored ) && 'imperial' === $stored ) ? 'imperial' : 'metric';

		/**
		 * Filters the site-wide unit system for GPX maps.
		 *
		 * @param string $value 'metric' or 'imperial'.
		 */
		$value = apply_filters( 'gpxrm_units', $value );

		return 'imperial' === $value ? 'imperial' : 'metric';
	}

	/**
	 * The site-wide map height in pixels, filterable.
	 *
	 * Maps that do not set their own height use this, so a site can size every
	 * map from one place instead of repeating the value on each block.
	 *
	 * @return int Height in pixels, between MIN_HEIGHT and MAX_HEIGHT.
	 */
	public static function default_height(): int {
		$stored = get_option( 'gpxrm_height', self::DEFAULT_HEIGHT );
		$value  = is_numeric( $stored ) ? (int) $stored : self::DEFAULT_HEIGHT;

		/**
		 * Filters the site-wide map height.
		 *
		 * @param int $value Height in pixels.
		 */
		$value = apply_filters( 'gpxrm_height', self::clamp( $value, self::MIN_HEIGHT, self::MAX_HEIGHT ) );

		return self::clamp( (int) $value, self::MIN_HEIGHT, self::MAX_HEIGHT );
	}

	/**
	 * Normalize an optional per-viewport height.
	 *
	 * 0 is the "nothing set" sentinel, meaning the band follows the next larger
	 * one. Anything else is clamped into the supported range.
	 *
	 * @param mixed $value Raw value.
	 * @return int Height in pixels, or 0 to inherit.
	 */
	public static function optional_height( $value ): int {
		if ( ! is_numeric( $value ) || (int) $value <= 0 ) {
			return 0;
		}

		return self::clamp( (int) $value, self::MIN_HEIGHT, self::MAX_HEIGHT );
	}

	/**
	 * The site-wide heights for every viewport band.
	 *
	 * Tablet and mobile are 0 unless the site sets them, so a site that never
	 * touches them behaves exactly as it did before they existed.
	 *
	 * @return array{base: int, tablet: int, mobile: int}
	 */
	public static function default_heights(): array {
		$heights = array(
			'base'   => self::default_height(),
			'tablet' => self::optional_height( get_option( 'gpxrm_height_tablet', 0 ) ),
			'mobile' => self::optional_height( get_option( 'gpxrm_height_mobile', 0 ) ),
		);

		/**
		 * Filters the site-wide heights for every viewport band.
		 *
		 * The base height also passes through `gpxrm_height` on its own, which
		 * predates this filter and keeps working unchanged.
		 *
		 * @param array{base: int, tablet: int, mobile: int} $heights Heights in pixels.
		 */
		$filtered = apply_filters( 'gpxrm_heights', $heights );

		return array(
			'base'   => self::clamp( (int) $filtered['base'], self::MIN_HEIGHT, self::MAX_HEIGHT ),
			'tablet' => self::optional_height( $filtered['tablet'] ),
			'mobile' => self::optional_height( $filtered['mobile'] ),
		);
	}

	/**
	 * Read a stored 'show'/'hide' option.
	 *
	 * @param string $option   Option name.
	 * @param string $fallback Value to assume when the option is unset.
	 * @return bool
	 */
	private static function stored_visibility( string $option, string $fallback = 'show' ): bool {
		$stored = get_option( $option, $fallback );
		if ( ! is_string( $stored ) ) {
			$stored = $fallback;
		}
		return 'hide' !== $stored;
	}

	/**
	 * Whether the stats bar shows unless a map says otherwise.
	 *
	 * @return bool
	 */
	public static function default_show_stats(): bool {
		/**
		 * Filters whether the stats bar is shown by default.
		 *
		 * @param bool $show True to show the stats bar.
		 */
		return apply_filters( 'gpxrm_show_stats', self::stored_visibility( 'gpxrm_show_stats' ) );
	}

	/**
	 * Whether the elevation profile shows unless a map says otherwise.
	 *
	 * @return bool
	 */
	public static function default_show_elevation(): bool {
		/**
		 * Filters whether the elevation profile is shown by default.
		 *
		 * @param bool $show True to show the elevation profile.
		 */
		return apply_filters( 'gpxrm_show_elevation', self::stored_visibility( 'gpxrm_show_elevation' ) );
	}

	/**
	 * Whether the download button shows unless a map says otherwise.
	 *
	 * Unlike the other panels this is off by default, so updating the plugin
	 * does not add a button to maps that never had one.
	 *
	 * @return bool
	 */
	public static function default_show_download(): bool {
		/**
		 * Filters whether the GPX download button is shown by default.
		 *
		 * @param bool $show True to show the download button.
		 */
		return apply_filters( 'gpxrm_show_download', self::stored_visibility( 'gpxrm_show_download', 'hide' ) );
	}

	/**
	 * Keep only known stat keys, in their canonical display order.
	 *
	 * @param array<int, mixed> $keys Candidate keys.
	 * @return array<int, string>
	 */
	private static function filter_stat_fields( array $keys ): array {
		$wanted = array();
		foreach ( $keys as $key ) {
			if ( is_string( $key ) ) {
				$wanted[] = strtolower( trim( $key ) );
			}
		}

		return array_values( array_intersect( self::STAT_FIELDS, $wanted ) );
	}

	/**
	 * The stats shown unless a map says otherwise.
	 *
	 * @return array<int, string>
	 */
	public static function default_stat_fields(): array {
		$stored = get_option( 'gpxrm_stat_fields', implode( ',', self::STAT_FIELDS ) );
		$fields = is_string( $stored )
			? self::filter_stat_fields( explode( ',', $stored ) )
			: self::STAT_FIELDS;

		/**
		 * Filters which stats the bar shows by default.
		 *
		 * @param array<int, string> $fields Stat keys, from Renderer::STAT_FIELDS.
		 */
		$filtered = apply_filters( 'gpxrm_stat_fields', $fields );

		// Re-filtered so a hook cannot introduce an unknown key or reorder them.
		$filtered = self::filter_stat_fields( $filtered );

		// This list only says which stats appear, never whether the bar does;
		// hiding it is the separate "Stats bar" setting's job. An empty list
		// would be a second way to hide it, so it falls back to all.
		return array() === $filtered ? self::STAT_FIELDS : $filtered;
	}

	/**
	 * Resolve which stats one map shows.
	 *
	 * An empty attribute defers to the site setting. The result is never empty:
	 * whether the bar appears at all is the "Stats bar" setting's job, so a list
	 * that names nothing recognisable falls back rather than hiding it.
	 *
	 * @param mixed              $raw          Raw attribute, a comma separated list.
	 * @param array<int, string> $site_default Fields to use when the attribute defers.
	 * @return array<int, string>
	 */
	private static function normalize_stat_fields( $raw, array $site_default ): array {
		if ( ! is_string( $raw ) ) {
			return $site_default;
		}

		$value = strtolower( trim( $raw ) );
		if ( '' === $value || 'default' === $value ) {
			return $site_default;
		}

		$fields = self::filter_stat_fields( explode( ',', $value ) );

		return array() === $fields ? $site_default : $fields;
	}

	/**
	 * Resolve a show/hide attribute that may defer to the site setting.
	 *
	 * Accepts the current tri-state strings ('', 'show', 'hide'), the booleans
	 * saved by blocks from before the site setting existed, and the
	 * 'true'/'false' strings the shortcode has always used.
	 *
	 * @param mixed $raw          Raw attribute value.
	 * @param bool  $site_default Value to use when the attribute defers.
	 * @return bool
	 */
	private static function normalize_visibility( $raw, bool $site_default ): bool {
		if ( is_bool( $raw ) ) {
			return $raw;
		}

		if ( is_string( $raw ) ) {
			$value = strtolower( trim( $raw ) );
			if ( '' === $value || 'default' === $value ) {
				return $site_default;
			}
			if ( in_array( $value, array( 'hide', 'hidden', 'false', '0', 'no', 'off' ), true ) ) {
				return false;
			}
			if ( in_array( $value, array( 'show', 'shown', 'true', '1', 'yes', 'on' ), true ) ) {
				return true;
			}
		}

		return $site_default;
	}

	/**
	 * Conversion factors and unit labels for a unit system.
	 *
	 * Statistics are always stored in kilometres and metres; these factors turn
	 * them into whatever the site displays. This is the single definition of the
	 * conversion, shared with the front-end script through a data attribute so
	 * the browser never carries its own copy.
	 *
	 * @param string $units 'metric' or 'imperial'.
	 * @return array{distFactor: float, distLabel: string, eleFactor: float, eleLabel: string}
	 */
	public static function units_config( string $units ): array {
		if ( 'imperial' === $units ) {
			return array(
				'distFactor' => 0.621371,
				/* translators: Abbreviation for miles, shown after a distance. */
				'distLabel'  => _x( 'mi', 'unit', 'gpx-route-map' ),
				'eleFactor'  => 3.28084,
				/* translators: Abbreviation for feet, shown after an elevation. */
				'eleLabel'   => _x( 'ft', 'unit', 'gpx-route-map' ),
			);
		}

		return array(
			'distFactor' => 1.0,
			/* translators: Abbreviation for kilometres, shown after a distance. */
			'distLabel'  => _x( 'km', 'unit', 'gpx-route-map' ),
			'eleFactor'  => 1.0,
			/* translators: Abbreviation for metres, shown after an elevation. */
			'eleLabel'   => _x( 'm', 'unit', 'gpx-route-map' ),
		);
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
		$units = self::units_config( $a['units'] );

		$download = $a['show_download']
			? sprintf( ' data-gpxrm-download="%s"', esc_attr( self::download_filename( $a['gpx_url'] ) ) )
			: '';

		// Attribution follows whichever tiles this map actually loads, so a
		// per-map custom provider still gets the credit it requires.
		$tile_url = '' !== $a['tile_url'] ? $a['tile_url'] : self::default_tile_url();

		$map = sprintf(
			'<div class="gpxrm-map" style="%1$s" data-gpxrm-gpx="%2$s" data-gpxrm-tile-url="%3$s" data-gpxrm-attribution="%4$s" data-gpxrm-max-zoom="%5$d" data-gpxrm-i18n="%6$s" data-gpxrm-units="%7$s"%8$s role="application" aria-label="%9$s">%10$s</div>',
			esc_attr( self::height_style( $a ) ),
			esc_url( $a['gpx_url'] ),
			esc_attr( $tile_url ),
			esc_attr( self::attribution_for( $tile_url ) ),
			$a['max_zoom'],
			esc_attr( self::view_messages_json() ),
			esc_attr( (string) wp_json_encode( $units ) ),
			$download,
			esc_attr__( 'Interactive route map', 'gpx-route-map' ),
			self::placeholder_html()
		);

		$stats_html     = $a['show_stats'] ? self::stats_html( $stats, $units, $a['stat_fields'] ) : '';
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
	 * @return array{gpx_url: string, height: int, height_tablet: int, height_mobile: int, show_stats: bool, show_elevation: bool, show_download: bool, max_zoom: int, tile_url: string, stats: array{distance: float, gain: float, loss: float, max: float, waypoints: int}|null, units: string, stat_fields: array<int, string>}
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

		// A height of 0 (or none at all) means "use whatever the site says", so
		// changing the site setting resizes every map that has not set its own.
		$site   = self::default_heights();
		$height = self::optional_height( $atts['height'] ?? 0 );
		$height = $height > 0 ? $height : $site['base'];

		$tablet = self::optional_height( $atts['heightTablet'] ?? 0 );
		$mobile = self::optional_height( $atts['heightMobile'] ?? 0 );

		// Cascade rather than disjoint bands: a band with nothing of its own
		// follows the next larger one, which is what people expect when they
		// set only a phone height.
		$tablet   = $tablet > 0 ? $tablet : $site['tablet'];
		$tablet   = $tablet > 0 ? $tablet : $height;
		$mobile   = $mobile > 0 ? $mobile : $site['mobile'];
		$mobile   = $mobile > 0 ? $mobile : $tablet;
		$max_zoom = ( isset( $atts['maxZoom'] ) && is_numeric( $atts['maxZoom'] ) ) ? (int) $atts['maxZoom'] : 17;
		$tile_url = self::sanitize_tile_url( $atts['tileUrl'] ?? '' );

		return array(
			'gpx_url'        => $gpx_url,
			'height'         => self::clamp( $height, self::MIN_HEIGHT, self::MAX_HEIGHT ),
			'height_tablet'  => $tablet,
			'height_mobile'  => $mobile,
			'show_stats'     => self::normalize_visibility( $atts['showStats'] ?? '', self::default_show_stats() ),
			'show_elevation' => self::normalize_visibility( $atts['showElevation'] ?? '', self::default_show_elevation() ),
			'show_download'  => self::normalize_visibility( $atts['showDownload'] ?? '', self::default_show_download() ),
			'stat_fields'    => self::normalize_stat_fields( $atts['statFields'] ?? '', self::default_stat_fields() ),
			'max_zoom'       => self::clamp( $max_zoom, 1, 22 ),
			'tile_url'       => $tile_url,
			'stats'          => self::normalize_stats( $atts['stats'] ?? null ),
			'units'          => self::normalize_units( $atts['units'] ?? '' ),
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
	 * Resolve the unit system for one map: an explicit per-map choice wins,
	 * otherwise the site setting applies.
	 *
	 * @param mixed $raw Raw `units` attribute ('metric', 'imperial' or '').
	 * @return string 'metric' or 'imperial'.
	 */
	private static function normalize_units( $raw ): string {
		$value = is_string( $raw ) ? strtolower( trim( $raw ) ) : '';
		if ( 'metric' === $value || 'imperial' === $value ) {
			return $value;
		}
		return self::default_units();
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
				'download' => __( 'Download GPX file', 'gpx-route-map' ),
				'maplibre' => __( 'Map failed to load. Click to retry.', 'gpx-route-map' ),
			)
		);
	}

	/**
	 * A sensible filename for the downloaded GPX file.
	 *
	 * @param string $gpx_url GPX URL.
	 * @return string
	 */
	private static function download_filename( string $gpx_url ): string {
		$path = (string) wp_parse_url( $gpx_url, PHP_URL_PATH );
		$name = basename( $path );

		return ( '' !== $name && preg_match( '/\.gpx$/i', $name ) ) ? $name : 'route.gpx';
	}

	/**
	 * Stats bar markup. Values are computed once in the editor and stored on the
	 * block; the front-end JS refreshes them live after it parses the GPX.
	 *
	 * @param array{distance: float, gain: float, loss: float, max: float, waypoints: int}|null $stats  Stored stats or null.
	 * @param array{distFactor: float, distLabel: string, eleFactor: float, eleLabel: string}   $units  Unit conversion.
	 * @param array<int, string>                                                                $fields Stats to include.
	 * @return string
	 */
	private static function stats_html( ?array $stats, array $units, array $fields ): string {
		$distance  = static function ( float $km ) use ( $units ): string {
			return number_format_i18n( $km * $units['distFactor'], 2 ) . ' ' . $units['distLabel'];
		};
		$elevation = static function ( float $metres ) use ( $units ): string {
			return number_format_i18n( round( $metres * $units['eleFactor'] ) ) . ' ' . $units['eleLabel'];
		};

		$rows = array(
			'distance'  => array( __( 'Distance', 'gpx-route-map' ), null === $stats ? '—' : $distance( $stats['distance'] ) ),
			'gain'      => array( __( 'Elevation gain', 'gpx-route-map' ), null === $stats ? '—' : '+' . $elevation( $stats['gain'] ) ),
			'loss'      => array( __( 'Elevation loss', 'gpx-route-map' ), null === $stats ? '—' : '−' . $elevation( $stats['loss'] ) ),
			'max'       => array( __( 'Max elevation', 'gpx-route-map' ), null === $stats ? '—' : $elevation( $stats['max'] ) ),
			'waypoints' => array( __( 'Waypoints', 'gpx-route-map' ), null === $stats ? '—' : number_format_i18n( $stats['waypoints'] ) ),
		);

		$items = '';
		foreach ( $rows as $key => $row ) {
			if ( ! in_array( $key, $fields, true ) ) {
				continue;
			}
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
	 * The map element's style attribute.
	 *
	 * A map whose bands all resolve to the same number keeps today's literal
	 * `height`, byte for byte, so every already-published post renders exactly
	 * as before and never depends on the stylesheet having been printed.
	 *
	 * A map that does differ per band switches to custom properties instead.
	 * It has to: an inline `height` beats any stylesheet rule regardless of
	 * specificity, so leaving one here would make the media queries dead and
	 * nothing in the test suite would notice.
	 *
	 * @param array{height: int, height_tablet: int, height_mobile: int} $a Normalized attributes.
	 * @return string
	 */
	private static function height_style( array $a ): string {
		if ( $a['height'] === $a['height_tablet'] && $a['height'] === $a['height_mobile'] ) {
			return sprintf( 'height:%dpx', $a['height'] );
		}

		return sprintf(
			'--gpxrm-h:%1$dpx;--gpxrm-h-t:%2$dpx;--gpxrm-h-m:%3$dpx',
			$a['height'],
			$a['height_tablet'],
			$a['height_mobile']
		);
	}

	/**
	 * Keep a value inside an inclusive range.
	 *
	 * @param int $value Value to clamp.
	 * @param int $min   Lowest allowed value.
	 * @param int $max   Highest allowed value.
	 * @return int
	 */
	private static function clamp( int $value, int $min, int $max ): int {
		return max( $min, min( $max, $value ) );
	}
}
