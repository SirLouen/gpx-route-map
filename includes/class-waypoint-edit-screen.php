<?php
/**
 * The screens a Waypoint post is edited and listed on.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A Waypoint post's settings box, how it saves, the box's styles and script,
 * and the marker and ID columns of the list of waypoints. Also loads the icon
 * font into the block editor's canvas, where the live preview draws icon
 * markers.
 */
final class Waypoint_Edit_Screen {

	/**
	 * Register the hooks of the screens Waypoint posts are edited and listed
	 * on.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'use_block_editor_for_post_type', array( $this, 'use_block_editor' ), 10, 2 );
		add_action( 'add_meta_boxes_' . Waypoints::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . Waypoints::POST_TYPE, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_filter( 'manage_' . Waypoints::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Waypoints::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'enqueue_block_assets', array( $this, 'editor_canvas_assets' ) );
	}

	/**
	 * Edit waypoints on the classic screen.
	 *
	 * @param mixed $use_block_editor Whether the block editor would be used.
	 * @param mixed $post_type        Post type.
	 * @return bool
	 */
	public function use_block_editor( $use_block_editor, $post_type ): bool {
		return Waypoints::POST_TYPE === $post_type ? false : (bool) $use_block_editor;
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
			Waypoints::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * The markers a post can choose, with their labels and explanations.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private function marker_choices(): array {
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
	private function meta_text( int $post_id, string $key ): string {
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
		$description = $this->meta_text( $post->ID, Waypoints::META_DESCRIPTION );
		$marker      = Waypoint_Values::sanitize_marker( $this->meta_text( $post->ID, Waypoints::META_MARKER ) );
		$icon        = $this->meta_text( $post->ID, Waypoints::META_ICON );
		$icon        = Dashicons::exists( $icon ) ? $icon : Waypoints::DEFAULT_ICON;
		$color       = Waypoint_Values::hex_color( $this->meta_text( $post->ID, Waypoints::META_COLOR ), Waypoints::DEFAULT_COLOR );
		$background  = Waypoint_Values::hex_color( $this->meta_text( $post->ID, Waypoints::META_BACKGROUND ), Waypoints::DEFAULT_BACKGROUND );

		wp_nonce_field( Waypoints::NONCE_ACTION, Waypoints::NONCE );
		?>
		<div id="gpxrm-waypoint-box">
			<div class="gpxrm-description">
				<p><label for="gpxrm_description"><strong><?php esc_html_e( 'Description', 'gpx-route-map' ); ?></strong></label></p>
				<?php
				wp_editor(
					$description,
					'gpxrm_description',
					array(
						'textarea_name' => 'gpxrm_description',
						'textarea_rows' => 6,
						'media_buttons' => false,
						'teeny'         => true,
						// Only what a description keeps (Waypoint_Description::tags()).
						'tinymce'       => array( 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo' ),
						'quicktags'     => array( 'buttons' => 'strong,em,link,ul,ol,li,close' ),
					)
				);
				?>
				<p class="description"><?php esc_html_e( 'What visitors read when they open this waypoint on a map, in place of the description in the GPX file. Links, bold and italic text, and lists are kept.', 'gpx-route-map' ); ?></p>
			</div>

			<fieldset class="gpxrm-marker-choice">
				<legend><strong><?php esc_html_e( 'Marker', 'gpx-route-map' ); ?></strong></legend>
				<?php foreach ( $this->marker_choices() as $value => $choice ) : ?>
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
						'gpxrm_icon_color'      => array( __( 'Icon color', 'gpx-route-map' ), $color, Waypoints::DEFAULT_COLOR ),
						'gpxrm_icon_background' => array( __( 'Background color', 'gpx-route-map' ), $background, Waypoints::DEFAULT_BACKGROUND ),
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
					echo $this->icon_marker_html( $icon, $color, $background ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
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
			Waypoints::GPX_ELEMENT,
			Waypoints::GPX_NAMESPACE,
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
	private function icon_marker_html( string $icon, string $color, string $background ): string {
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
		if ( ! wp_verify_nonce( sanitize_text_field( $this->posted( Waypoints::NONCE ) ), Waypoints::NONCE_ACTION ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = Waypoint_Values::sanitize_fields(
			array(
				'description' => $this->posted( 'gpxrm_description' ),
				'marker'      => sanitize_key( $this->posted( 'gpxrm_marker' ) ),
				'icon'        => sanitize_key( $this->posted( 'gpxrm_icon' ) ),
				'color'       => sanitize_text_field( $this->posted( 'gpxrm_icon_color' ) ),
				'background'  => sanitize_text_field( $this->posted( 'gpxrm_icon_background' ) ),
			)
		);

		$meta = array(
			Waypoints::META_DESCRIPTION => $fields['description'],
			Waypoints::META_MARKER      => $fields['marker'],
			Waypoints::META_ICON        => $fields['icon'],
			Waypoints::META_COLOR       => $fields['color'],
			Waypoints::META_BACKGROUND  => $fields['background'],
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
	private function posted( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- save() checks the nonce; callers sanitize.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Styles and scripts for the waypoint screens.
	 *
	 * @param string $hook The admin page being loaded.
	 * @return void
	 */
	public function admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( null === $screen || Waypoints::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_register_style( 'gpxrm-waypoints-admin', false, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Inline only.
		wp_enqueue_style( 'gpxrm-waypoints-admin' );
		wp_add_inline_style( 'gpxrm-waypoints-admin', $this->admin_css() );

		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_add_inline_script( 'wp-color-picker', $this->admin_js() );
		}
	}

	/**
	 * Styles for the settings box and the list's marker column.
	 *
	 * @return string
	 */
	private function admin_css(): string {
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
	private function admin_js(): string {
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

		$marker = Waypoint_Values::sanitize_marker( $this->meta_text( $post_id, Waypoints::META_MARKER ) );
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
			$icon = $this->meta_text( $post_id, Waypoints::META_ICON );
			$icon = Dashicons::exists( $icon ) ? $icon : Waypoints::DEFAULT_ICON;
			$html = $this->icon_marker_html(
				$icon,
				Waypoint_Values::hex_color( $this->meta_text( $post_id, Waypoints::META_COLOR ), Waypoints::DEFAULT_COLOR ),
				Waypoint_Values::hex_color( $this->meta_text( $post_id, Waypoints::META_BACKGROUND ), Waypoints::DEFAULT_BACKGROUND )
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
		if ( is_admin() && Waypoints::enabled() ) {
			wp_enqueue_style( 'dashicons' );
		}
	}
}
