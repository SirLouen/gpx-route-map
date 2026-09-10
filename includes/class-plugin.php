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
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
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
	 * The settings page slug, used as the menu slug and the settings section page.
	 */
	const SETTINGS_PAGE = 'gpx-route-map';

	/**
	 * The plugin's own option group.
	 *
	 * Deliberately not the core "general" group: options.php overwrites every
	 * option in the submitted group that is absent from the request, so a field
	 * that is only rendered conditionally would be wiped on an unrelated save.
	 */
	const OPTION_GROUP = 'gpxrm_options';

	/**
	 * Add the settings page under Settings.
	 *
	 * A single options page belongs in a submenu rather than a top-level menu.
	 *
	 * @return void
	 */
	public function register_settings_page(): void {
		$hook = add_options_page(
			__( 'GPX Route Map', 'gpx-route-map' ),
			__( 'GPX Route Map', 'gpx-route-map' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( $this, 'render_settings_page' )
		);

		if ( $hook ) {
			add_action( 'admin_footer-' . $hook, array( $this, 'print_settings_script' ) );
		}
	}

	/**
	 * Output the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		// add_options_page() already gates the menu, but the Handbook pattern is
		// to re-check on render rather than rely on the menu alone.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Defaults for every GPX map on this site. Individual maps can override them.', 'gpx-route-map' ); ?></p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::SETTINGS_PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register the settings, their sections and their fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		add_settings_section(
			'gpxrm_display',
			__( 'Display', 'gpx-route-map' ),
			'__return_false',
			self::SETTINGS_PAGE
		);

		$panels = array(
			'gpxrm_show_stats'     => array( __( 'Stats bar', 'gpx-route-map' ), 'show' ),
			'gpxrm_show_elevation' => array( __( 'Elevation profile', 'gpx-route-map' ), 'show' ),
			// Off by default so updating never adds a button to existing maps.
			'gpxrm_show_download'  => array( __( 'Download button', 'gpx-route-map' ), 'hide' ),
		);

		register_setting(
			self::OPTION_GROUP,
			'gpxrm_units',
			array(
				'type'              => 'string',
				'default'           => 'metric',
				'sanitize_callback' => array( $this, 'sanitize_units' ),
				'show_in_rest'      => true,
			)
		);

		foreach ( $panels as $option => $field ) {
			register_setting(
				self::OPTION_GROUP,
				$option,
				array(
					'type'              => 'string',
					'default'           => $field[1],
					'sanitize_callback' => array( $this, 'sanitize_visibility' ),
					'show_in_rest'      => true,
				)
			);
		}

		register_setting(
			self::OPTION_GROUP,
			'gpxrm_stat_fields',
			array(
				'type'              => 'string',
				'default'           => implode( ',', Renderer::STAT_FIELDS ),
				'sanitize_callback' => array( $this, 'sanitize_stat_fields' ),
				'show_in_rest'      => true,
			)
		);

		/*
		 * Fields render in the order they are added, so they are laid out
		 * explicitly here rather than as a side effect of registration. "Stats
		 * shown" sits directly under "Stats bar" because it only qualifies that
		 * setting, and it is hidden along with it.
		 */
		add_settings_field(
			'gpxrm_units',
			__( 'Units', 'gpx-route-map' ),
			array( $this, 'render_units_field' ),
			self::SETTINGS_PAGE,
			'gpxrm_display',
			array( 'label_for' => 'gpxrm_units' )
		);

		add_settings_field(
			'gpxrm_show_stats',
			$panels['gpxrm_show_stats'][0],
			array( $this, 'render_visibility_field' ),
			self::SETTINGS_PAGE,
			'gpxrm_display',
			array(
				'label_for' => 'gpxrm_show_stats',
				'option'    => 'gpxrm_show_stats',
			)
		);

		add_settings_field(
			'gpxrm_stat_fields',
			__( 'Stats shown', 'gpx-route-map' ),
			array( $this, 'render_stat_fields_field' ),
			self::SETTINGS_PAGE,
			'gpxrm_display',
			// Lets the row be hidden while the bar itself is switched off.
			array( 'class' => 'gpxrm-stat-fields-row' )
		);

		add_settings_field(
			'gpxrm_show_elevation',
			$panels['gpxrm_show_elevation'][0],
			array( $this, 'render_visibility_field' ),
			self::SETTINGS_PAGE,
			'gpxrm_display',
			array(
				'label_for' => 'gpxrm_show_elevation',
				'option'    => 'gpxrm_show_elevation',
			)
		);

		add_settings_field(
			'gpxrm_show_download',
			$panels['gpxrm_show_download'][0],
			array( $this, 'render_visibility_field' ),
			self::SETTINGS_PAGE,
			'gpxrm_display',
			array(
				'label_for' => 'gpxrm_show_download',
				'option'    => 'gpxrm_show_download',
			)
		);

		$this->register_tile_settings();
	}

	/**
	 * Register the map tile provider settings.
	 *
	 * @return void
	 */
	private function register_tile_settings(): void {
		add_settings_section(
			'gpxrm_tiles',
			__( 'Map tiles', 'gpx-route-map' ),
			array( $this, 'render_tiles_intro' ),
			self::SETTINGS_PAGE
		);

		register_setting(
			self::OPTION_GROUP,
			'gpxrm_tile_provider',
			array(
				'type'              => 'string',
				'default'           => 'osm',
				'sanitize_callback' => array( $this, 'sanitize_tile_provider' ),
				'show_in_rest'      => true,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'gpxrm_thunderforest_key',
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( $this, 'sanitize_thunderforest_key' ),
				// Not exposed over REST: the editor has no use for it.
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			'gpxrm_thunderforest_style',
			array(
				'type'              => 'string',
				'default'           => 'outdoors',
				'sanitize_callback' => array( $this, 'sanitize_thunderforest_style' ),
				'show_in_rest'      => false,
			)
		);

		add_settings_field(
			'gpxrm_tile_provider',
			__( 'Tile provider', 'gpx-route-map' ),
			array( $this, 'render_tile_provider_field' ),
			self::SETTINGS_PAGE,
			'gpxrm_tiles',
			array( 'label_for' => 'gpxrm_tile_provider' )
		);

		add_settings_field(
			'gpxrm_thunderforest_key',
			__( 'Thunderforest API key', 'gpx-route-map' ),
			array( $this, 'render_thunderforest_key_field' ),
			self::SETTINGS_PAGE,
			'gpxrm_tiles',
			array(
				'label_for' => 'gpxrm_thunderforest_key',
				'class'     => 'gpxrm-thunderforest-row',
			)
		);

		add_settings_field(
			'gpxrm_thunderforest_style',
			__( 'Thunderforest style', 'gpx-route-map' ),
			array( $this, 'render_thunderforest_style_field' ),
			self::SETTINGS_PAGE,
			'gpxrm_tiles',
			array(
				'label_for' => 'gpxrm_thunderforest_style',
				'class'     => 'gpxrm-thunderforest-row',
			)
		);
	}

	/**
	 * Hide the stat list while the stats bar is switched off, since choosing
	 * what goes in a bar that is not rendered has no meaning.
	 *
	 * @return void
	 */
	public function print_settings_script(): void {
		?>
		<script>
			( function () {
				function bind( selectId, rowSelector, shouldHide ) {
					var control = document.getElementById( selectId );
					var rows = document.querySelectorAll( rowSelector );
					if ( ! control || ! rows.length ) {
						return;
					}
					function sync() {
						var hide = shouldHide( control.value );
						Array.prototype.forEach.call( rows, function ( row ) {
							row.hidden = hide;
						} );
					}
					control.addEventListener( 'change', sync );
					sync();
				}

				// Choosing what a hidden stats bar contains means nothing.
				bind( 'gpxrm_show_stats', '.gpxrm-stat-fields-row', function ( value ) {
					return 'hide' === value;
				} );

				// The key and style only apply to Thunderforest.
				bind( 'gpxrm_tile_provider', '.gpxrm-thunderforest-row', function ( value ) {
					return 'thunderforest' !== value;
				} );
			}() );
		</script>
		<?php
	}

	/**
	 * Labels for each stat, used by the settings checkboxes.
	 *
	 * @return array<string, string>
	 */
	public static function stat_field_labels(): array {
		return array(
			'distance'  => __( 'Distance', 'gpx-route-map' ),
			'gain'      => __( 'Elevation gain', 'gpx-route-map' ),
			'loss'      => __( 'Elevation loss', 'gpx-route-map' ),
			'max'       => __( 'Max elevation', 'gpx-route-map' ),
			'waypoints' => __( 'Waypoints', 'gpx-route-map' ),
		);
	}

	/**
	 * Store the checked stats as a comma separated list of known keys.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_stat_fields( $value ): string {
		$submitted = is_array( $value ) ? $value : explode( ',', is_string( $value ) ? $value : '' );
		$wanted    = array();

		foreach ( $submitted as $key ) {
			if ( is_string( $key ) ) {
				$wanted[] = strtolower( trim( $key ) );
			}
		}

		return implode( ',', array_intersect( Renderer::STAT_FIELDS, $wanted ) );
	}

	/**
	 * Output a checkbox per stat.
	 *
	 * @return void
	 */
	public function render_stat_fields_field(): void {
		$current = Renderer::default_stat_fields();

		echo '<fieldset>';
		printf(
			'<legend class="screen-reader-text">%s</legend>',
			esc_html__( 'Stats shown', 'gpx-route-map' )
		);

		foreach ( self::stat_field_labels() as $key => $label ) {
			printf(
				'<label for="gpxrm_stat_%1$s" style="display:block;margin-bottom:4px"><input type="checkbox" id="gpxrm_stat_%1$s" name="gpxrm_stat_fields[]" value="%1$s"%2$s> %3$s</label>',
				esc_attr( $key ),
				checked( in_array( $key, $current, true ), true, false ),
				esc_html( $label )
			);
		}

		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Which figures the stats bar lists. To remove the bar altogether, set Stats bar to Hidden.', 'gpx-route-map' ) . '</p>';
	}

	/**
	 * Explanation shown under the Map tiles heading.
	 *
	 * @return void
	 */
	public function render_tiles_intro(): void {
		echo '<p>' . esc_html__( 'Where the map images come from. Visitors load these directly from the provider, so the provider receives their IP address and the area of the map being viewed.', 'gpx-route-map' ) . '</p>';
	}

	/**
	 * Keep the provider to one the plugin knows about.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_tile_provider( $value ): string {
		return ( is_string( $value ) && 'thunderforest' === $value ) ? 'thunderforest' : 'osm';
	}

	/**
	 * Validate the Thunderforest API key.
	 *
	 * Their keys are 32 alphanumeric characters. Anything else is rejected
	 * rather than quietly stored, so a mistyped key does not silently produce a
	 * map of blank tiles.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_thunderforest_key( $value ): string {
		$key = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $key || preg_match( '/^[A-Za-z0-9]{16,64}$/', $key ) ) {
			return $key;
		}

		add_settings_error(
			'gpxrm_thunderforest_key',
			'gpxrm_thunderforest_key_invalid',
			__( 'That does not look like a Thunderforest API key. Keys are 32 letters and numbers, with nothing else in between.', 'gpx-route-map' ),
			'error'
		);

		return Renderer::thunderforest_key();
	}

	/**
	 * Keep the style to one the plugin offers.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_thunderforest_style( $value ): string {
		$styles = Renderer::thunderforest_styles();

		return ( is_string( $value ) && isset( $styles[ $value ] ) ) ? $value : 'outdoors';
	}

	/**
	 * Output the tile provider control.
	 *
	 * @return void
	 */
	public function render_tile_provider_field(): void {
		$current = Renderer::tile_provider();
		$choices = array(
			'osm'           => __( 'OpenStreetMap (no account needed)', 'gpx-route-map' ),
			'thunderforest' => __( 'Thunderforest (API key required)', 'gpx-route-map' ),
		);

		echo '<select name="gpxrm_tile_provider" id="gpxrm_tile_provider">';
		foreach ( $choices as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'OpenStreetMap works with no setup. Thunderforest offers outdoor and cycling styles, and needs an account.', 'gpx-route-map' ) . '</p>';
	}

	/**
	 * Output the Thunderforest key field.
	 *
	 * Deliberately a plain text field: the key travels to the browser with every
	 * map, so presenting it as a secret would misrepresent how it works.
	 *
	 * @return void
	 */
	public function render_thunderforest_key_field(): void {
		printf(
			'<input type="text" class="regular-text code" name="gpxrm_thunderforest_key" id="gpxrm_thunderforest_key" value="%s" autocomplete="off" spellcheck="false">',
			esc_attr( Renderer::thunderforest_key() )
		);

		echo '<p class="description">';
		printf(
			/* translators: %s: link to the Thunderforest sign-up page. */
			esc_html__( 'Get a free key at %s. The free plan covers 150,000 map tiles a month, which is roughly 8,000 map views.', 'gpx-route-map' ),
			'<a href="https://www.thunderforest.com/pricing/" target="_blank" rel="noopener noreferrer">thunderforest.com</a>'
		);
		echo '<br>';
		esc_html_e( 'The key is visible in your pages, because visitors\' browsers load the tiles. Thunderforest cannot restrict a key to your domain, so keep an eye on your usage.', 'gpx-route-map' );
		echo '</p>';
	}

	/**
	 * Output the Thunderforest style control.
	 *
	 * @return void
	 */
	public function render_thunderforest_style_field(): void {
		$current = Renderer::thunderforest_style();

		echo '<select name="gpxrm_thunderforest_style" id="gpxrm_thunderforest_style">';
		foreach ( Renderer::thunderforest_styles() as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Keep a stored panel visibility to a known value.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public function sanitize_visibility( $value ): string {
		return ( is_string( $value ) && 'hide' === $value ) ? 'hide' : 'show';
	}

	/**
	 * Output a show/hide control for one panel.
	 *
	 * @param array<string, string> $args Field args, including the option name.
	 * @return void
	 */
	public function render_visibility_field( array $args ): void {
		$option = $args['option'] ?? '';
		switch ( $option ) {
			case 'gpxrm_show_elevation':
				$current = Renderer::default_show_elevation();
				break;
			case 'gpxrm_show_download':
				$current = Renderer::default_show_download();
				break;
			default:
				$current = Renderer::default_show_stats();
				break;
		}

		$choices = array(
			'show' => __( 'Shown', 'gpx-route-map' ),
			'hide' => __( 'Hidden', 'gpx-route-map' ),
		);

		printf( '<select name="%1$s" id="%1$s">', esc_attr( $option ) );
		foreach ( $choices as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current ? 'show' : 'hide', $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
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
	 *   stats     Show the stats bar: "true"/"false" (defaults to the site setting).
	 *   elevation Show the elevation profile: "true"/"false" (defaults to the site setting).
	 *   download  Show the GPX download button: "true"/"false" (defaults to the site setting).
	 *   fields    Which stats to list, e.g. "distance,gain"; "none" hides them all.
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
				'stats'     => '',
				'elevation' => '',
				'download'  => '',
				'fields'    => '',
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
			'showDownload'  => $atts['download'],
			'statFields'    => $atts['fields'],
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
