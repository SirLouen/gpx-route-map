<?php
/**
 * Waypoint posts: a description, a photo or an icon for the waypoints of GPX
 * files.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The opt-in Waypoint post type, and what maps show for the waypoints linked
 * to its posts.
 *
 * A map links a waypoint of its GPX file to a Waypoint post in two ways: its
 * block picks one for it, by the waypoint's position in the file, or the file
 * itself names the post, inside the waypoint's <extensions>. A pick made in
 * the block wins over the file.
 *
 * @phpstan-type Photo array{src: string, width: int, height: int, alt: string, srcset?: string}
 * @phpstan-type Marker array{name?: string, desc?: string, photo?: Photo, marker?: string, thumb?: string, icon?: string, color?: string, background?: string}
 */
class Waypoints {

	const POST_TYPE = 'gpxrm_waypoint';

	/**
	 * The setting that turns the feature on. Off until a site turns it on.
	 */
	const OPTION = 'gpxrm_waypoints';

	/**
	 * The namespace of the element a GPX file links a waypoint with, inside
	 * the waypoint's <extensions>, which GPX keeps for other namespaces:
	 * <gpxrm:waypoint xmlns:gpxrm="…">123</gpxrm:waypoint>. Mirrored in
	 * src/view/map-core.ts.
	 */
	const GPX_NAMESPACE = 'https://wordpress.org/plugins/gpx-route-map/';

	/**
	 * The local name of that element.
	 */
	const GPX_ELEMENT = 'waypoint';

	const META_DESCRIPTION = '_gpxrm_description';
	const META_MARKER      = '_gpxrm_marker';
	const META_ICON        = '_gpxrm_icon';
	const META_COLOR       = '_gpxrm_icon_color';
	const META_BACKGROUND  = '_gpxrm_icon_background';

	/**
	 * Where a GPX attachment keeps the links read from its file.
	 */
	const META_FILE_LINKS = '_gpxrm_file_links';

	/**
	 * The markers a post can choose. Anything else keeps the marker the map
	 * gives the waypoint from its GPX file.
	 */
	const MARKERS = array( 'image', 'icon' );

	const DEFAULT_ICON = 'location';

	/**
	 * An icon marker's colours when none is chosen, as the map draws it:
	 * white on the track's colour (TRACK_COLOR in src/view/map-core.ts).
	 */
	const DEFAULT_COLOR      = '#ffffff';
	const DEFAULT_BACKGROUND = '#2e7d32';

	/**
	 * The most waypoints one map links. Far beyond any real file, it only
	 * bounds what a hand-written attribute or file can ask the server for.
	 */
	const MAX_LINKS = 1000;

	const NONCE        = 'gpxrm_waypoint_nonce';
	const NONCE_ACTION = 'gpxrm_waypoint_save';

	/**
	 * Register the hooks. Each one checks whether the feature is on when it
	 * runs, so turning it on or off takes effect on the next request.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'use_block_editor' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'enqueue_block_assets', array( $this, 'editor_canvas_assets' ) );
	}

	/**
	 * Whether the site has turned Waypoint posts on.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$stored = get_option( self::OPTION, false );

		// update_option() stores true as '1'.
		return true === $stored || 1 === $stored || '1' === $stored;
	}

	/**
	 * Register the post type, when the feature is on.
	 *
	 * Not public: a waypoint has no page of its own, only what maps show for
	 * it. It is in the REST API so the block editor can list the waypoints a
	 * map can link to, and edited on the classic screen, whose boxes are all
	 * it needs.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		if ( ! self::enabled() ) {
			return;
		}

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Waypoints', 'gpx-route-map' ),
					'singular_name'         => __( 'Waypoint', 'gpx-route-map' ),
					'menu_name'             => __( 'Waypoints', 'gpx-route-map' ),
					'all_items'             => __( 'All Waypoints', 'gpx-route-map' ),
					'add_new'               => __( 'Add Waypoint', 'gpx-route-map' ),
					'add_new_item'          => __( 'Add Waypoint', 'gpx-route-map' ),
					'edit_item'             => __( 'Edit Waypoint', 'gpx-route-map' ),
					'new_item'              => __( 'New Waypoint', 'gpx-route-map' ),
					'search_items'          => __( 'Search Waypoints', 'gpx-route-map' ),
					'not_found'             => __( 'No waypoints found.', 'gpx-route-map' ),
					'not_found_in_trash'    => __( 'No waypoints found in Trash.', 'gpx-route-map' ),
					'item_published'        => __( 'Waypoint published.', 'gpx-route-map' ),
					'item_updated'          => __( 'Waypoint updated.', 'gpx-route-map' ),
					'featured_image'        => __( 'Photo', 'gpx-route-map' ),
					'set_featured_image'    => __( 'Set photo', 'gpx-route-map' ),
					'remove_featured_image' => __( 'Remove photo', 'gpx-route-map' ),
					'use_featured_image'    => __( 'Use as photo', 'gpx-route-map' ),
				),
				'description'         => __( 'Waypoints that GPX maps link to, each with its own description, photo or icon.', 'gpx-route-map' ),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true,
				'menu_icon'           => 'dashicons-location',
				'supports'            => array( 'title', 'thumbnail' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'rewrite'             => false,
				'query_var'           => false,
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'delete_with_user'    => false,
			)
		);

		// The photo is the post's featured image, whose box only shows where
		// the theme supports one. This adds the post type to whatever the
		// theme declared, and changes nothing when it supports every type.
		add_theme_support( 'post-thumbnails', array( self::POST_TYPE ) );
	}

	/**
	 * Edit waypoints on the classic screen.
	 *
	 * @param mixed $use_block_editor Whether the block editor would be used.
	 * @param mixed $post_type        Post type.
	 * @return bool
	 */
	public function use_block_editor( $use_block_editor, $post_type ): bool {
		return self::POST_TYPE === $post_type ? false : (bool) $use_block_editor;
	}

	/**
	 * Add the box with the waypoint's own settings.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'gpxrm-waypoint',
			__( 'Waypoint', 'gpx-route-map' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * The markers a post can choose, with their labels and explanations.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function marker_choices(): array {
		return array(
			''      => array(
				__( 'Usual marker', 'gpx-route-map' ),
				__( 'The marker the map gives this waypoint, from its type in the GPX file.', 'gpx-route-map' ),
			),
			'image' => array(
				__( 'Photo', 'gpx-route-map' ),
				__( 'A thumbnail of the photo set in the Photo box. Without one, the usual marker.', 'gpx-route-map' ),
			),
			'icon'  => array(
				__( 'Icon', 'gpx-route-map' ),
				__( 'An icon of your choice, in your own colors.', 'gpx-route-map' ),
			),
		);
	}

	/**
	 * A stored text field of a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return string
	 */
	private static function meta_text( int $post_id, string $key ): string {
		$value = get_post_meta( $post_id, $key, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Output the waypoint's settings box.
	 *
	 * @param \WP_Post $post The waypoint.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$description = self::meta_text( $post->ID, self::META_DESCRIPTION );
		$marker      = self::sanitize_marker( self::meta_text( $post->ID, self::META_MARKER ) );
		$icon        = self::meta_text( $post->ID, self::META_ICON );
		$icon        = Dashicons::exists( $icon ) ? $icon : self::DEFAULT_ICON;
		$color       = self::hex_color( self::meta_text( $post->ID, self::META_COLOR ), self::DEFAULT_COLOR );
		$background  = self::hex_color( self::meta_text( $post->ID, self::META_BACKGROUND ), self::DEFAULT_BACKGROUND );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE );
		?>
		<div id="gpxrm-waypoint-box">
			<p>
				<label for="gpxrm_description"><strong><?php esc_html_e( 'Description', 'gpx-route-map' ); ?></strong></label>
				<textarea id="gpxrm_description" name="gpxrm_description" class="large-text" rows="4"><?php echo esc_textarea( $description ); ?></textarea>
				<span class="description"><?php esc_html_e( 'What visitors read when they open this waypoint on a map, in place of the description in the GPX file. Line breaks are kept.', 'gpx-route-map' ); ?></span>
			</p>

			<fieldset class="gpxrm-marker-choice">
				<legend><strong><?php esc_html_e( 'Marker', 'gpx-route-map' ); ?></strong></legend>
				<?php foreach ( self::marker_choices() as $value => $choice ) : ?>
					<p>
						<label>
							<input type="radio" name="gpxrm_marker" value="<?php echo esc_attr( $value ); ?>"<?php checked( $marker, $value ); ?>>
							<?php echo esc_html( $choice[0] ); ?>
						</label>
						<span class="description"><?php echo esc_html( $choice[1] ); ?></span>
					</p>
				<?php endforeach; ?>
			</fieldset>

			<div class="gpxrm-icon-settings"<?php echo 'icon' === $marker ? '' : ' hidden'; ?>>
				<fieldset>
					<legend><strong><?php esc_html_e( 'Icon', 'gpx-route-map' ); ?></strong></legend>
					<p>
						<label for="gpxrm_icon_search" class="screen-reader-text"><?php esc_html_e( 'Search icons', 'gpx-route-map' ); ?></label>
						<input type="search" id="gpxrm_icon_search" class="gpxrm-icon-search regular-text" placeholder="<?php esc_attr_e( 'Search icons', 'gpx-route-map' ); ?>" autocomplete="off">
					</p>
					<div class="gpxrm-icons">
						<?php foreach ( Dashicons::NAMES as $name ) : ?>
							<label class="gpxrm-icon-choice" data-icon="<?php echo esc_attr( $name ); ?>" title="<?php echo esc_attr( $name ); ?>">
								<input type="radio" class="screen-reader-text" name="gpxrm_icon" value="<?php echo esc_attr( $name ); ?>"<?php checked( $icon, $name ); ?>>
								<span class="gpxrm-icon-face" aria-hidden="true"><span class="dashicons dashicons-<?php echo esc_attr( $name ); ?>"></span></span>
								<span class="screen-reader-text"><?php echo esc_html( $name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<div class="gpxrm-colors">
					<?php
					// The picker hides its field until opened, so each name
					// sits outside it, and its button points to that name.
					$color_fields = array(
						'gpxrm_icon_color'      => array( __( 'Icon color', 'gpx-route-map' ), $color, self::DEFAULT_COLOR ),
						'gpxrm_icon_background' => array( __( 'Background color', 'gpx-route-map' ), $background, self::DEFAULT_BACKGROUND ),
					);
					foreach ( $color_fields as $name => $field ) :
						?>
						<div class="gpxrm-color-field">
							<span class="gpxrm-color-label" id="<?php echo esc_attr( $name . '_label' ); ?>"><?php echo esc_html( $field[0] ); ?></span>
							<input type="text" class="gpxrm-color" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $field[1] ); ?>" data-default-color="<?php echo esc_attr( $field[2] ); ?>" aria-labelledby="<?php echo esc_attr( $name . '_label' ); ?>">
						</div>
					<?php endforeach; ?>
				</div>
				<p>
					<?php esc_html_e( 'Preview:', 'gpx-route-map' ); ?>
					<?php
					// Its icon and colours follow the fields as they change.
					echo self::icon_marker_html( $icon, $color, $background ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
					?>
				</p>
			</div>

			<hr>
			<p>
				<?php
				printf(
					/* translators: %d: the waypoint's post ID. */
					esc_html__( 'Waypoint ID: %d', 'gpx-route-map' ),
					(int) $post->ID
				);
				?>
			</p>
			<p class="description">
				<?php esc_html_e( 'A map shows this waypoint when its block links one of the GPX file\'s waypoints to it. Or a GPX file in the Media Library can link to it itself: put this at the end of the waypoint\'s <wpt> element, or only its middle line inside the waypoint\'s <extensions> if it has one.', 'gpx-route-map' ); ?>
			</p>
			<textarea class="large-text code" rows="3" readonly aria-label="<?php esc_attr_e( 'GPX extension that links a waypoint to this one', 'gpx-route-map' ); ?>"><?php echo esc_textarea( self::gpx_snippet( $post->ID ) ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * The GPX extension that links a waypoint to a post.
	 *
	 * @param int $post_id Waypoint post ID.
	 * @return string
	 */
	public static function gpx_snippet( int $post_id ): string {
		return sprintf(
			"<extensions>\n  <gpxrm:%1\$s xmlns:gpxrm=\"%2\$s\">%3\$d</gpxrm:%1\$s>\n</extensions>",
			self::GPX_ELEMENT,
			self::GPX_NAMESPACE,
			$post_id
		);
	}

	/**
	 * Markup of an icon marker, as the map draws it, for previews in the
	 * admin screens.
	 *
	 * @param string $icon       Dashicon name.
	 * @param string $color      Icon colour.
	 * @param string $background Background colour.
	 * @return string
	 */
	private static function icon_marker_html( string $icon, string $color, string $background ): string {
		return sprintf(
			'<span class="gpxrm-marker-preview" style="color:%1$s;background-color:%2$s"><span class="dashicons dashicons-%3$s" aria-hidden="true"></span></span>',
			esc_attr( $color ),
			esc_attr( $background ),
			esc_attr( $icon )
		);
	}

	/**
	 * Save the waypoint's settings from its edit screen.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		// Only from the box above: quick edit, autosaves and imports leave
		// the settings as they are.
		if ( ! wp_verify_nonce( sanitize_text_field( self::posted( self::NONCE ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = self::sanitize_fields(
			array(
				'description' => self::posted( 'gpxrm_description' ),
				'marker'      => sanitize_key( self::posted( 'gpxrm_marker' ) ),
				'icon'        => sanitize_key( self::posted( 'gpxrm_icon' ) ),
				'color'       => sanitize_text_field( self::posted( 'gpxrm_icon_color' ) ),
				'background'  => sanitize_text_field( self::posted( 'gpxrm_icon_background' ) ),
			)
		);

		$meta = array(
			self::META_DESCRIPTION => $fields['description'],
			self::META_MARKER      => $fields['marker'],
			self::META_ICON        => $fields['icon'],
			self::META_COLOR       => $fields['color'],
			self::META_BACKGROUND  => $fields['background'],
		);

		foreach ( $meta as $key => $value ) {
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				// update_post_meta() unslashes what it is given.
				update_post_meta( $post_id, $key, wp_slash( $value ) );
			}
		}
	}

	/**
	 * A submitted field as a string, unslashed, '' when absent or not text.
	 * Each caller sanitizes it.
	 *
	 * @param string $key Field name.
	 * @return string
	 */
	private static function posted( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- save() checks the nonce; callers sanitize.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Keep a waypoint's settings to values the map can use.
	 *
	 * The icon and colours are kept even while another marker is chosen, so
	 * switching back finds them as they were. A colour left at its default
	 * is kept as none, so the map draws its own (white on the track's
	 * colour), which can follow the site or theme if the track's colour
	 * ever does.
	 *
	 * @param array<string, mixed> $raw Submitted settings.
	 * @return array{description: string, marker: string, icon: string, color: string, background: string}
	 */
	public static function sanitize_fields( array $raw ): array {
		$description = $raw['description'] ?? '';
		$icon        = $raw['icon'] ?? '';
		$marker      = self::sanitize_marker( $raw['marker'] ?? '' );

		return array(
			'description' => is_string( $description ) ? self::sanitize_description( $description ) : '',
			'marker'      => $marker,
			'icon'        => is_string( $icon ) && Dashicons::exists( $icon ) ? $icon : ( 'icon' === $marker ? self::DEFAULT_ICON : '' ),
			'color'       => self::chosen_color( $raw['color'] ?? '', self::DEFAULT_COLOR ),
			'background'  => self::chosen_color( $raw['background'] ?? '', self::DEFAULT_BACKGROUND ),
		);
	}

	/**
	 * A colour the author chose, or '' for one that is not a colour or is
	 * the default.
	 *
	 * @param mixed  $value   Submitted value.
	 * @param string $default_color The default colour.
	 * @return string
	 */
	private static function chosen_color( $value, string $default_color ): string {
		$color = self::hex_color( $value, '' );

		return $default_color === $color ? '' : $color;
	}

	/**
	 * A description as typed: plain text, which the map shows as text, so
	 * nothing that looks like a tag is taken out ("<10%" stays). Only
	 * invalid UTF-8 and control characters go, and line breaks become \n.
	 *
	 * @param string $text Submitted description.
	 * @return string
	 */
	public static function sanitize_description( string $text ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", wp_check_invalid_utf8( $text ) );

		return trim( (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text ) );
	}

	/**
	 * A marker choice the map knows, or '' for the usual marker.
	 *
	 * @param mixed $value Stored or submitted value.
	 * @return string
	 */
	public static function sanitize_marker( $value ): string {
		return is_string( $value ) && in_array( $value, self::MARKERS, true ) ? $value : '';
	}

	/**
	 * A colour as #rrggbb in lower case, with #rgb written out, or the
	 * fallback.
	 *
	 * The test sanitize_hex_color() makes, written out because that function
	 * takes only strings, and returns nothing at all for anything else.
	 *
	 * @param mixed  $value    Stored or submitted value.
	 * @param string $fallback Value for anything that is not such a colour.
	 * @return string
	 */
	public static function hex_color( $value, string $fallback ): string {
		if ( ! is_string( $value ) ) {
			return $fallback;
		}

		$value = strtolower( trim( $value ) );
		if ( 1 === preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $short ) ) {
			$value = '#' . $short[1] . $short[1] . $short[2] . $short[2] . $short[3] . $short[3];
		}

		return 1 === preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : $fallback;
	}

	/**
	 * Text to show as text: tags dropped, entities decoded once, as the
	 * browser does when WordPress prints it.
	 *
	 * @param string $text Text, such as a title.
	 * @return string
	 */
	private static function plain( string $text ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Styles and scripts for the waypoint screens.
	 *
	 * @param string $hook The admin page being loaded.
	 * @return void
	 */
	public function admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( null === $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_register_style( 'gpxrm-waypoints-admin', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Inline only.
		wp_enqueue_style( 'gpxrm-waypoints-admin' );
		wp_add_inline_style( 'gpxrm-waypoints-admin', self::admin_css() );

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_add_inline_script( 'wp-color-picker', self::admin_js() );
		}
	}

	/**
	 * Styles for the settings box and the list's marker column.
	 *
	 * @return string
	 */
	private static function admin_css(): string {
		return <<<'CSS'
.gpxrm-marker-preview{display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;width:26px;height:26px;border:3px solid #1b3a1e;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,.4);vertical-align:middle}
.gpxrm-marker-preview .dashicons{width:16px;height:16px;font-size:16px;line-height:1}
.gpxrm-marker-thumb{display:block;width:32px;height:32px;object-fit:cover;border:2px solid #fff;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.4)}
.gpxrm-marker-choice p{margin:.5em 0}
.gpxrm-marker-choice .description{display:block;margin-left:24px}
.gpxrm-icons{position:relative;display:grid;grid-template-columns:repeat(auto-fill,minmax(40px,1fr));gap:4px;max-height:220px;overflow:auto;padding:6px;border:1px solid #dcdcde;background:#fff}
.gpxrm-icon-choice{position:relative;display:block;cursor:pointer}
.gpxrm-icon-choice[hidden]{display:none}
.gpxrm-icon-face{display:flex;align-items:center;justify-content:center;height:36px;border-radius:2px}
.gpxrm-icon-choice:hover .gpxrm-icon-face{background:#f0f0f1}
.gpxrm-icon-choice input:checked+.gpxrm-icon-face{background:#2271b1;color:#fff}
.gpxrm-icon-choice input:focus-visible+.gpxrm-icon-face{box-shadow:0 0 0 2px #fff,0 0 0 4px #2271b1}
.gpxrm-colors{display:flex;flex-wrap:wrap;gap:12px 24px;margin:1em 0}
.gpxrm-color-field{display:flex;flex-direction:column;align-items:flex-start;gap:4px}
.column-gpxrm_marker{width:72px}
.column-gpxrm_id{width:64px}
CSS;
	}

	/**
	 * The settings box's script: the colour pickers, the icon search, and a
	 * preview and an icon list that follow the choices.
	 *
	 * @return string
	 */
	private static function admin_js(): string {
		return <<<'JS'
( function ( $ ) {
	var box = document.getElementById( 'gpxrm-waypoint-box' );
	if ( ! box ) {
		return;
	}
	var settings = box.querySelector( '.gpxrm-icon-settings' );
	var preview = box.querySelector( '.gpxrm-marker-preview' );
	var glyph = preview && preview.querySelector( '.dashicons' );
	var colors = box.querySelectorAll( '.gpxrm-color' );

	function checked( name ) {
		var input = box.querySelector( 'input[name="' + name + '"]:checked' );
		return input ? input.value : '';
	}

	function sync( changed, value ) {
		var wasHidden = settings.hidden;
		settings.hidden = 'icon' !== checked( 'gpxrm_marker' );
		// The icon is kept while another marker is chosen, so on the way
		// back show it rather than the start of the list.
		if ( wasHidden && ! settings.hidden ) {
			reveal();
		}
		if ( ! preview ) {
			return;
		}
		glyph.className = 'dashicons dashicons-' + ( checked( 'gpxrm_icon' ) || 'location' );
		Array.prototype.forEach.call( colors, function ( input ) {
			var color = input === changed ? value : input.value;
			var property = 'gpxrm_icon_color' === input.name ? 'color' : 'backgroundColor';
			preview.style[ property ] = color || input.getAttribute( 'data-default-color' );
		} );
	}

	box.addEventListener( 'change', function () {
		sync();
	} );

	// Enter would submit the post form, saving whatever is half done.
	box.querySelector( '.gpxrm-icon-search' ).addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
		}
	} );

	box.querySelector( '.gpxrm-icon-search' ).addEventListener( 'input', function () {
		var query = this.value.trim().toLowerCase();
		Array.prototype.forEach.call( box.querySelectorAll( '.gpxrm-icon-choice' ), function ( choice ) {
			choice.hidden = '' !== query && -1 === choice.getAttribute( 'data-icon' ).indexOf( query );
		} );
	} );

	// The picker changes its field only after this fires, so the new
	// colour comes from the event.
	$( colors ).wpColorPicker( {
		change: function ( event, ui ) {
			sync( event.target, ui.color.toString() );
		},
		clear: function ( event ) {
			var input = $( event.target ).closest( '.wp-picker-container' ).find( '.gpxrm-color' )[ 0 ];
			sync( input, '' );
		},
	} );
	Array.prototype.forEach.call( colors, function ( input ) {
		$( input ).closest( '.wp-picker-container' ).find( '.wp-color-result' ).attr( 'aria-describedby', input.getAttribute( 'aria-labelledby' ) );
	} );

	function reveal() {
		var picked = box.querySelector( '.gpxrm-icon-choice input:checked' );
		if ( picked ) {
			box.querySelector( '.gpxrm-icons' ).scrollTop = picked.parentNode.offsetTop - 6;
		}
	}

	if ( ! settings.hidden ) {
		reveal();
	}
	sync();
}( jQuery ) );
JS;
	}

	/**
	 * Add a marker preview and the ID, which a GPX file links with, to the
	 * list of waypoints.
	 *
	 * @param array<string, string> $columns Column labels, by key.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['gpxrm_marker'] = __( 'Marker', 'gpx-route-map' );
				$out['gpxrm_id']     = __( 'ID', 'gpx-route-map' );
			}
		}
		return $out;
	}

	/**
	 * Output a cell of the added columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Waypoint post ID.
	 * @return void
	 */
	public function column( string $column, int $post_id ): void {
		if ( 'gpxrm_id' === $column ) {
			echo (int) $post_id;
			return;
		}

		if ( 'gpxrm_marker' !== $column ) {
			return;
		}

		$marker = self::sanitize_marker( self::meta_text( $post_id, self::META_MARKER ) );
		$thumb  = (int) get_post_thumbnail_id( $post_id );

		if ( 'image' === $marker && $thumb > 0 ) {
			// Named by the text after it, as the photo's own alt describes
			// the photo rather than the marker.
			echo wp_get_attachment_image(
				$thumb,
				'thumbnail',
				false,
				array(
					'class' => 'gpxrm-marker-thumb',
					'alt'   => '',
				)
			);
			echo '<span class="screen-reader-text">' . esc_html__( 'Photo', 'gpx-route-map' ) . '</span>';
			return;
		}

		if ( 'icon' === $marker ) {
			$icon = self::meta_text( $post_id, self::META_ICON );
			$icon = Dashicons::exists( $icon ) ? $icon : self::DEFAULT_ICON;
			$html = self::icon_marker_html(
				$icon,
				self::hex_color( self::meta_text( $post_id, self::META_COLOR ), self::DEFAULT_COLOR ),
				self::hex_color( self::meta_text( $post_id, self::META_BACKGROUND ), self::DEFAULT_BACKGROUND )
			);
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
			echo '<span class="screen-reader-text">' . esc_html(
				sprintf(
					/* translators: %s: a Dashicon's name, such as "location". */
					__( 'Icon: %s', 'gpx-route-map' ),
					$icon
				)
			) . '</span>';
			return;
		}

		echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Usual marker', 'gpx-route-map' ) . '</span>';
	}

	/**
	 * Load the icon font into the block editor's canvas, where the live
	 * preview draws icon markers. The front end loads it with the first map
	 * that needs it (see Renderer::render()).
	 *
	 * @return void
	 */
	public function editor_canvas_assets(): void {
		if ( is_admin() && self::enabled() ) {
			wp_enqueue_style( 'dashicons' );
		}
	}

	/**
	 * A post ID from a stored, submitted or file value: a whole number above
	 * zero, else 0.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function post_id( $value ): int {
		if ( is_int( $value ) ) {
			return max( 0, $value );
		}

		if ( ! is_string( $value ) ) {
			return 0;
		}

		$value = trim( $value );

		return 1 === preg_match( '/^[0-9]{1,15}$/', $value ) ? (int) $value : 0;
	}

	/**
	 * The waypoints a block links to posts: its `waypointPosts` attribute,
	 * the post ID by each waypoint's position among the file's <wpt>
	 * elements, counting from 0.
	 *
	 * @param mixed $raw Raw attribute value.
	 * @return array<int, int> Post IDs by waypoint position, in order.
	 */
	public static function links_from_attribute( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$links = array();
		foreach ( $raw as $index => $post ) {
			// PHP turns a key such as "3" into the number already.
			$id = self::post_id( $post );
			if ( is_int( $index ) && $index >= 0 && $id > 0 ) {
				$links[ $index ] = $id;
			}
		}

		ksort( $links );

		return array_slice( $links, 0, self::MAX_LINKS, true );
	}

	/**
	 * The waypoints a shortcode links to posts: its `waypoints` attribute, as
	 * "number:post ID" pairs separated by commas, numbering the file's
	 * waypoints from 1, e.g. "1:12, 3:40".
	 *
	 * @param string $value Attribute value.
	 * @return array<int, int> Post IDs by waypoint position, counting from 0.
	 */
	public static function links_from_shortcode( string $value ): array {
		$links = array();
		foreach ( explode( ',', $value ) as $pair ) {
			$parts = explode( ':', $pair );
			if ( 2 !== count( $parts ) ) {
				continue;
			}

			$number = self::post_id( $parts[0] );
			$id     = self::post_id( $parts[1] );
			if ( $number > 0 && $id > 0 ) {
				$links[ $number - 1 ] = $id;
			}
		}

		return self::links_from_attribute( $links );
	}

	/**
	 * Whether this site can read the links a GPX file holds, which takes
	 * PHP's XMLReader extension. PHP builds it in by default, but a host can
	 * leave it out. Links made in a block or shortcode work without it.
	 *
	 * @return bool
	 */
	public static function reads_files(): bool {
		return class_exists( '\XMLReader' );
	}

	/**
	 * The posts a GPX file links its waypoints to, by each waypoint's
	 * position among the file's <wpt> elements, as the map counts them.
	 *
	 * Read as a stream, so a long track costs no memory.
	 *
	 * @param string $path GPX file.
	 * @return array<int, int>|null Post IDs by waypoint position, in order,
	 *                              or null when the file could not be read.
	 */
	public static function links_in_gpx( string $path ): ?array {
		return self::reads_files() && is_readable( $path ) ? Gpx_Link_Reader::read( $path ) : null;
	}

	/**
	 * The posts a GPX attachment's file links its waypoints to.
	 *
	 * Read from the file once, then kept with the attachment until the file
	 * changes, so a map does not read its whole track on every page view.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<int, int> Post IDs by waypoint position, in order.
	 */
	public static function file_links( int $attachment_id ): array {
		$path    = get_attached_file( $attachment_id );
		$version = is_string( $path ) ? self::file_version( $path ) : '';
		if ( '' === $version ) {
			return array();
		}

		$stored = get_post_meta( $attachment_id, self::META_FILE_LINKS, true );
		if ( is_array( $stored ) && isset( $stored['file'], $stored['links'] ) && $version === $stored['file'] ) {
			return self::links_from_attribute( $stored['links'] );
		}

		// A read that could not run is not kept, so the file is read again
		// once it can be, such as after the host adds XMLReader. static::,
		// so a test can stand in for such a site.
		$links = static::links_in_gpx( (string) $path );
		if ( null === $links ) {
			return array();
		}

		update_post_meta(
			$attachment_id,
			self::META_FILE_LINKS,
			array(
				'file'  => $version,
				'links' => $links,
			)
		);

		return $links;
	}

	/**
	 * What tells a GPX file from an earlier one at the same path: its size and
	 * when it was last written. '' for a path that is not a readable GPX file.
	 *
	 * @param string $path File path.
	 * @return string
	 */
	private static function file_version( string $path ): string {
		if ( '' === $path || ! Plugin::looks_like_gpx( $path ) ) {
			return '';
		}

		$size     = filesize( $path );
		$modified = filemtime( $path );

		return false === $size || false === $modified ? '' : $size . ':' . $modified;
	}

	/**
	 * What one map shows for its linked waypoints, by their position in its
	 * GPX file. Nothing while the feature is off.
	 *
	 * @param int             $attachment_id The map's GPX attachment, or 0.
	 * @param string          $gpx_url       The map's GPX URL.
	 * @param array<int, int> $chosen        Links the block or shortcode made.
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Marker>
	 */
	public static function markers_for_map( int $attachment_id, string $gpx_url, array $chosen ): array {
		if ( ! self::enabled() ) {
			return array();
		}

		// A pasted address of a file in the Media Library is the same file.
		if ( $attachment_id <= 0 && '' !== $gpx_url ) {
			$attachment_id = attachment_url_to_postid( $gpx_url );
		}

		// The block's own links win over the file's.
		$links = $chosen + ( $attachment_id > 0 ? self::file_links( $attachment_id ) : array() );
		ksort( $links );

		return self::markers( $links );
	}

	/**
	 * What a map shows for waypoints linked to posts, by position: only
	 * for published waypoints without a password.
	 *
	 * @param array<int, int> $links Post IDs by waypoint position.
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Marker>
	 */
	public static function markers( array $links ): array {
		$ids = array_values( array_unique( $links ) );
		if ( array() === $ids ) {
			return array();
		}

		$by_id   = self::markers_by_id( $ids );
		$markers = array();
		foreach ( $links as $index => $id ) {
			if ( isset( $by_id[ $id ] ) && array() !== $by_id[ $id ] ) {
				$markers[ $index ] = $by_id[ $id ];
			}
		}

		return $markers;
	}

	/**
	 * What a map shows for each of these waypoint posts it may show, by post
	 * ID: only published waypoints without a password.
	 *
	 * @param array<int, int> $ids Post IDs.
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Marker>
	 */
	private static function markers_by_id( array $ids ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'post__in'               => $ids,
				'posts_per_page'         => count( $ids ),
				'has_password'           => false,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		update_post_thumbnail_cache( $query );

		$by_id = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$by_id[ $post->ID ] = self::marker_for( $post );
			}
		}

		return $by_id;
	}

	/**
	 * What a map shows for a waypoint post.
	 *
	 * @param \WP_Post $post Published waypoint.
	 * @return array<string, mixed>
	 * @phpstan-return Marker
	 */
	private static function marker_for( \WP_Post $post ): array {
		$photo = null;
		$thumb = '';
		$image = (int) get_post_thumbnail_id( $post );

		if ( $image > 0 ) {
			$medium = wp_get_attachment_image_src( $image, 'medium' );
			if ( is_array( $medium ) ) {
				$srcset = wp_get_attachment_image_srcset( $image, 'medium' );
				$photo  = array(
					'src'    => esc_url_raw( $medium[0] ),
					'width'  => (int) $medium[1],
					'height' => (int) $medium[2],
					'alt'    => self::plain( self::meta_text( $image, '_wp_attachment_image_alt' ) ),
					'srcset' => is_string( $srcset ) ? $srcset : '',
				);
			}

			$small = wp_get_attachment_image_src( $image, 'thumbnail' );
			if ( is_array( $small ) ) {
				$thumb = esc_url_raw( $small[0] );
			}
		}

		return self::entry(
			array(
				'name'       => self::plain( get_the_title( $post ) ),
				'desc'       => trim( self::meta_text( $post->ID, self::META_DESCRIPTION ) ),
				'marker'     => self::meta_text( $post->ID, self::META_MARKER ),
				'icon'       => self::meta_text( $post->ID, self::META_ICON ),
				'color'      => self::meta_text( $post->ID, self::META_COLOR ),
				'background' => self::meta_text( $post->ID, self::META_BACKGROUND ),
				'photo'      => $photo,
				'thumb'      => $thumb,
			)
		);
	}

	/**
	 * What a map shows for a waypoint, from the post's settings: only what
	 * differs from the waypoint in the GPX file.
	 *
	 * A photo marker needs a photo, else the waypoint keeps its usual one. An
	 * icon marker always gets an icon, and its colours when chosen; without
	 * them the map draws its own.
	 *
	 * @param array{name: string, desc: string, marker: mixed, icon: mixed, color: mixed, background: mixed, photo: array{src: string, width: int, height: int, alt: string, srcset: string}|null, thumb: string} $raw Post settings.
	 * @return array<string, mixed>
	 * @phpstan-return Marker
	 */
	public static function entry( array $raw ): array {
		$entry = array();

		if ( '' !== $raw['name'] ) {
			$entry['name'] = $raw['name'];
		}

		if ( '' !== $raw['desc'] ) {
			$entry['desc'] = $raw['desc'];
		}

		if ( null !== $raw['photo'] && '' !== $raw['photo']['src'] ) {
			$entry['photo'] = self::photo_entry( $raw['photo'] );
		}

		return $entry + self::marker_entry( $raw );
	}

	/**
	 * What a map is told about a post's photo.
	 *
	 * @param array{src: string, width: int, height: int, alt: string, srcset: string} $photo The photo.
	 * @return array<string, mixed>
	 * @phpstan-return Photo
	 */
	private static function photo_entry( array $photo ): array {
		$entry = array(
			'src'    => $photo['src'],
			'width'  => max( 0, $photo['width'] ),
			'height' => max( 0, $photo['height'] ),
			'alt'    => $photo['alt'],
		);
		if ( '' !== $photo['srcset'] ) {
			$entry['srcset'] = $photo['srcset'];
		}

		return $entry;
	}

	/**
	 * What a map is told about a post's marker: a photo marker with its
	 * thumbnail, an icon marker with its icon and any colours chosen, or
	 * nothing for the usual marker.
	 *
	 * @param array{marker: mixed, icon: mixed, color: mixed, background: mixed, thumb: string} $raw Post settings.
	 * @return array<string, string>
	 * @phpstan-return array{marker?: string, thumb?: string, icon?: string, color?: string, background?: string}
	 */
	private static function marker_entry( array $raw ): array {
		$marker = self::sanitize_marker( $raw['marker'] );
		if ( 'image' === $marker && '' !== $raw['thumb'] ) {
			return array(
				'marker' => 'image',
				'thumb'  => $raw['thumb'],
			);
		}

		if ( 'icon' !== $marker ) {
			return array();
		}

		$entry = array(
			'marker' => 'icon',
			'icon'   => is_string( $raw['icon'] ) && Dashicons::exists( $raw['icon'] ) ? $raw['icon'] : self::DEFAULT_ICON,
		);

		$color = self::hex_color( $raw['color'], '' );
		if ( '' !== $color ) {
			$entry['color'] = $color;
		}
		$background = self::hex_color( $raw['background'], '' );
		if ( '' !== $background ) {
			$entry['background'] = $background;
		}

		return $entry;
	}

	/**
	 * Whether any of a map's waypoints is drawn as an icon, which needs the
	 * Dashicons font.
	 *
	 * @param array<int, array<string, mixed>> $markers What the map shows, by position.
	 * @return bool
	 */
	public static function uses_icons( array $markers ): bool {
		foreach ( $markers as $marker ) {
			if ( 'icon' === ( $marker['marker'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * JSON of what a map shows for its waypoints, for a data attribute.
	 *
	 * Always an object keyed by position, even when the positions run from 0
	 * without a gap. JSON_HEX_AMP for the reason Renderer::attr_json() gives.
	 *
	 * @param array<int, array<string, mixed>> $markers What the map shows, by position.
	 * @return string
	 */
	public static function json( array $markers ): string {
		return (string) wp_json_encode( (object) $markers, JSON_HEX_AMP );
	}
}
