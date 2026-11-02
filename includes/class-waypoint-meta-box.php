<?php
/**
 * A Waypoint post's settings box.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The box a Waypoint post's own settings are edited in, on its classic edit
 * screen, and how they save: its coordinates, its description, its marker, and
 * the icon and colours of an icon marker. It ends with the post's ID and the
 * GPX extension that links a file's waypoint to it.
 */
final class Waypoint_Meta_Box {

	/**
	 * The address argument that says coordinates could not be saved.
	 */
	const NOTICE_ARG = 'gpxrm_coords';

	/**
	 * Add the box to the edit screen.
	 *
	 * @return void
	 */
	public function add(): void {
		add_meta_box(
			'gpxrm-waypoint',
			__( 'Waypoint', 'gpx-route-map' ),
			array( $this, 'render' ),
			Waypoints::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Output the box.
	 *
	 * @param \WP_Post $post The waypoint.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		$settings = new Waypoint_Settings( $post->ID );

		wp_nonce_field( Waypoints::NONCE_ACTION, Waypoints::NONCE );
		echo '<div id="gpxrm-waypoint-box">';
		$this->coordinate_fields( $post->ID );
		$this->description_field( $settings );
		$this->marker_field( $settings );
		$this->icon_fields( $settings );
		$this->link_help( $post->ID );
		echo '</div>';
	}

	/**
	 * Output the coordinates. Text fields, as a number field in the browser
	 * turns a typed 50,75 into 5075 and calls it valid. With a link to see
	 * what was saved on a map, the only check on this screen of a latitude
	 * and longitude swapped.
	 *
	 * @param int $post_id The post's ID.
	 * @return void
	 */
	private function coordinate_fields( int $post_id ): void {
		$stored = Waypoint_Coordinates::stored( $post_id );
		$saved  = Waypoint_Coordinates::of( $post_id );
		?>
		<fieldset class="gpxrm-coordinates">
			<legend><strong><?php esc_html_e( 'Coordinates', 'gpx-route-map' ); ?></strong></legend>
			<p>
				<label for="gpxrm_lat"><?php esc_html_e( 'Latitude', 'gpx-route-map' ); ?></label>
				<input type="text" id="gpxrm_lat" name="gpxrm_lat" value="<?php echo esc_attr( $stored['lat'] ); ?>" dir="ltr" autocomplete="off">
				<label for="gpxrm_lon"><?php esc_html_e( 'Longitude', 'gpx-route-map' ); ?></label>
				<input type="text" id="gpxrm_lon" name="gpxrm_lon" value="<?php echo esc_attr( $stored['lon'] ); ?>" dir="ltr" autocomplete="off">
			</p>
			<p class="description"><?php esc_html_e( 'To show this waypoint at its own place on a map. In decimal degrees, such as 50.75 and 5.93.', 'gpx-route-map' ); ?></p>
			<?php if ( null !== $saved ) : ?>
				<p><a href="<?php echo esc_url( self::map_link( $stored['lat'], $stored['lon'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'See these coordinates on OpenStreetMap', 'gpx-route-map' ); ?></a></p>
			<?php endif; ?>
		</fieldset>
		<?php
	}

	/**
	 * The address of a place on OpenStreetMap, with a marker on it.
	 *
	 * @param string $lat Latitude.
	 * @param string $lon Longitude.
	 * @return string
	 */
	private static function map_link( string $lat, string $lon ): string {
		return sprintf( 'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=15/%1$s/%2$s', $lat, $lon );
	}

	/**
	 * Output the description's editor, with only the buttons for what a
	 * description keeps (Waypoint_Description::tags()).
	 *
	 * @param Waypoint_Settings $settings The post's settings.
	 * @return void
	 */
	private function description_field( Waypoint_Settings $settings ): void {
		?>
		<div class="gpxrm-description">
			<p><label for="gpxrm_description"><strong><?php esc_html_e( 'Description', 'gpx-route-map' ); ?></strong></label></p>
			<?php
			wp_editor(
				$settings->description(),
				'gpxrm_description',
				array(
					'textarea_name' => 'gpxrm_description',
					'textarea_rows' => 6,
					'media_buttons' => false,
					'teeny'         => true,
					'tinymce'       => array( 'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo' ),
					'quicktags'     => array( 'buttons' => 'strong,em,link,ul,ol,li,close' ),
				)
			);
			?>
			<p class="description"><?php esc_html_e( 'What visitors read when they open this waypoint on a map, in place of the description in the GPX file. Links, bold and italic text, and lists are kept.', 'gpx-route-map' ); ?></p>
		</div>
		<?php
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
	 * Output the choice of marker.
	 *
	 * @param Waypoint_Settings $settings The post's settings.
	 * @return void
	 */
	private function marker_field( Waypoint_Settings $settings ): void {
		$marker = $settings->marker();
		?>
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
		<?php
	}

	/**
	 * Output an icon marker's settings, shown while it is the chosen marker:
	 * the icon, searchable, its colours, and a preview, which follows them as
	 * they change.
	 *
	 * @param Waypoint_Settings $settings The post's settings.
	 * @return void
	 */
	private function icon_fields( Waypoint_Settings $settings ): void {
		$icon = $settings->icon();
		// The picker hides its field until opened, so each name sits outside
		// it, and its button points to that name.
		$color_fields = array(
			'gpxrm_icon_color'      => array( __( 'Icon color', 'gpx-route-map' ), $settings->color(), Waypoints::DEFAULT_COLOR ),
			'gpxrm_icon_background' => array( __( 'Background color', 'gpx-route-map' ), $settings->background(), Waypoints::DEFAULT_BACKGROUND ),
		);
		?>
		<div class="gpxrm-icon-settings"<?php echo 'icon' === $settings->marker() ? '' : ' hidden'; ?>>
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
				<?php foreach ( $color_fields as $name => $field ) : ?>
					<div class="gpxrm-color-field">
						<span class="gpxrm-color-label" id="<?php echo esc_attr( $name . '_label' ); ?>"><?php echo esc_html( $field[0] ); ?></span>
						<input type="text" class="gpxrm-color" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $field[1] ); ?>" data-default-color="<?php echo esc_attr( $field[2] ); ?>" aria-labelledby="<?php echo esc_attr( $name . '_label' ); ?>">
					</div>
				<?php endforeach; ?>
			</div>
			<p>
				<?php esc_html_e( 'Preview:', 'gpx-route-map' ); ?>
				<?php echo $settings->icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Output the post's ID and the GPX extension that links a file's
	 * waypoint to it.
	 *
	 * @param int $post_id The post's ID.
	 * @return void
	 */
	private function link_help( int $post_id ): void {
		?>
		<hr>
		<p>
			<?php
			printf(
				/* translators: %d: the waypoint's post ID. */
				esc_html__( 'Waypoint ID: %d', 'gpx-route-map' ),
				(int) $post_id
			);
			?>
		</p>
		<p class="description">
			<?php esc_html_e( 'A map shows this waypoint when its block links one of the GPX file\'s waypoints to it. Or a GPX file in the Media Library can link to it itself: put this at the end of the waypoint\'s <wpt> element, or only its middle line inside the waypoint\'s <extensions> if it has one.', 'gpx-route-map' ); ?>
		</p>
		<textarea class="large-text code" rows="3" readonly aria-label="<?php esc_attr_e( 'GPX extension that links a waypoint to this one', 'gpx-route-map' ); ?>"><?php echo esc_textarea( self::gpx_snippet( $post_id ) ); ?></textarea>
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
	 * Save the settings from the box. Only from the box: quick edit,
	 * autosaves and imports leave them as they are.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( ! $this->may_save( $post_id ) ) {
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

		$this->save_coordinates( $post_id );
	}

	/**
	 * Save the coordinates: stored when good, cleared when both fields are
	 * empty, and when they cannot be read, kept as they were, for a notice
	 * to say so rather than lose them. A form without the fields, rendered
	 * before they existed, clears nothing.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_coordinates( int $post_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- may_save() checks the nonce.
		if ( ! isset( $_POST['gpxrm_lat'] ) && ! isset( $_POST['gpxrm_lon'] ) ) {
			return;
		}

		$coordinates = Waypoint_Coordinates::parse( $this->posted( 'gpxrm_lat' ), $this->posted( 'gpxrm_lon' ) );
		if ( null === $coordinates ) {
			add_filter( 'redirect_post_location', array( $this, 'flag_invalid_coordinates' ) );
			return;
		}

		if ( '' === $coordinates['lat'] ) {
			delete_post_meta( $post_id, Waypoints::META_LAT );
			delete_post_meta( $post_id, Waypoints::META_LON );
			return;
		}

		update_post_meta( $post_id, Waypoints::META_LAT, $coordinates['lat'] );
		update_post_meta( $post_id, Waypoints::META_LON, $coordinates['lon'] );
	}

	/**
	 * Say in the address WordPress returns to that the coordinates were not
	 * saved.
	 *
	 * @param string $location The address.
	 * @return string
	 */
	public function flag_invalid_coordinates( string $location ): string {
		return add_query_arg( self::NOTICE_ARG, 'invalid', $location );
	}

	/**
	 * Take that from the address once it has been read.
	 *
	 * @param array<int, string> $args Arguments WordPress removes.
	 * @return array<int, string>
	 */
	public function removable_args( array $args ): array {
		$args[] = self::NOTICE_ARG;
		return $args;
	}

	/**
	 * Say that the coordinates were not saved, beside WordPress's own notice
	 * that the post was.
	 *
	 * @return void
	 */
	public function coordinates_notice(): void {
		$screen = get_current_screen();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only chooses to show a fixed message.
		if ( null === $screen || Waypoints::POST_TYPE !== $screen->post_type || ! isset( $_GET[ self::NOTICE_ARG ] ) ) {
			return;
		}

		wp_admin_notice(
			__( 'The coordinates were not saved. Enter a latitude from -90 to 90 and a longitude from -180 to 180 in decimal degrees, such as 50.75 and 5.93. This waypoint keeps its earlier coordinates.', 'gpx-route-map' ),
			array( 'type' => 'warning' )
		);
	}

	/**
	 * Whether this request saves the box: it was sent from it, for this post,
	 * it is not an autosave or a revision, and the user can edit the post. A
	 * plugin that saves another waypoint during the request must not give it
	 * this form's fields.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function may_save( int $post_id ): bool {
		if ( ! wp_verify_nonce( sanitize_text_field( $this->posted( Waypoints::NONCE ) ), Waypoints::NONCE_ACTION ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( (int) $this->posted( 'post_ID' ) !== $post_id ) {
			return false;
		}

		return ! wp_is_post_revision( $post_id ) && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * A submitted field as a string, unslashed, '' when absent or not text.
	 * Each caller sanitizes it.
	 *
	 * @param string $key Field name.
	 * @return string
	 */
	private function posted( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- may_save() checks the nonce; callers sanitize.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		return is_string( $value ) ? $value : '';
	}
}
