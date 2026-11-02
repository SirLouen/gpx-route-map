<?php
/**
 * A Waypoint post's Maps box.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The box that says where a Waypoint post shows: a map to choose its place on,
 * with its coordinates, the maps it can be added to, and those already showing
 * it, from a block, a shortcode or the GPX file, or at its own coordinates. It
 * changes those in blocks: Waypoint_Maps_Save writes what its form asks for.
 * All of it is there from the start, so that one Update can set a waypoint's
 * place and add it to a map, on a new waypoint too.
 *
 * A field holds which map it is about, by post and place among the post's map
 * blocks, with a hash of the post's content: a map added above it would
 * otherwise send the change to the wrong one. The GPX file is only a label,
 * as one file can be on a map by ID and on another by address.
 *
 * @phpstan-import-type Row from Waypoint_Map_Rows
 * @phpstan-import-type Addable from Waypoint_Map_Rows
 */
final class Waypoint_Maps_Box {

	/**
	 * The address argument that names a page of the box.
	 */
	const PAGE_ARG = 'gpxrm_maps_page';

	/**
	 * Add the box to the edit screen, first, as where a waypoint goes comes
	 * before what it says, and the icon list is a long way to scroll.
	 *
	 * @return void
	 */
	public function add(): void {
		add_meta_box(
			'gpxrm-waypoint-maps',
			__( 'Maps', 'gpx-route-map' ),
			array( $this, 'render' ),
			Waypoints::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Output the box. Rendering it is what looks through the site's maps, so
	 * nothing else on the screen pays for that.
	 *
	 * @param \WP_Post $post The waypoint.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		$this->output( Waypoint_Map_Rows::for_waypoint( $post->ID ), $post );
	}

	/**
	 * Output what was found. A link to a post maps do not show yet is still a
	 * link, and shows once the post is published, so its rows stay. No row
	 * does not mean no map when some posts could not be looked at.
	 *
	 * @param array<string, mixed> $found The rows, and what they leave out.
	 * @param \WP_Post             $post  The waypoint.
	 * @phpstan-param array{rows: array<int, Row>, hidden: int, unreadable: int, addable: array<int, Addable>} $found
	 * @return void
	 */
	public function output( array $found, \WP_Post $post ): void {
		wp_nonce_field( Waypoints::MAPS_NONCE_ACTION, Waypoints::MAPS_NONCE );

		$this->place_fields( $post->ID );
		$this->add_field( $found['addable'] );

		$part            = Waypoint_Map_Rows::page( $found['rows'], self::asked_page() );
		$has_coordinates = null !== Waypoint_Coordinates::of( $post->ID );

		if ( array() !== $found['rows'] ) {
			$this->table( $part['rows'], $has_coordinates );
			$this->pagination( $part['page'], $part['pages'] );
		} elseif ( 0 === $found['hidden'] + $found['unreadable'] ) {
			echo '<p>' . esc_html__( 'No map shows this waypoint yet.', 'gpx-route-map' ) . '</p>';
		}

		if ( self::maps_cannot_show( $post ) ) {
			echo '<p class="description">' . esc_html__( 'Maps show this waypoint only once it is published without a password.', 'gpx-route-map' ) . '</p>';
		}

		$this->notes( $found['hidden'], $found['unreadable'] );
	}

	/**
	 * Whether maps leave a waypoint out as it stands. One being written has
	 * not reached that yet: publishing it is the next thing it will do.
	 *
	 * @param \WP_Post $post The waypoint.
	 * @return bool
	 */
	private static function maps_cannot_show( \WP_Post $post ): bool {
		if ( 'auto-draft' === $post->post_status ) {
			return false;
		}

		return 'publish' !== $post->post_status || '' !== $post->post_password;
	}

	/**
	 * Output the map to choose the waypoint's place on, with the site's own
	 * tiles, and the coordinates the form sends. The fields are text, as a
	 * number field in a browser turns a typed 50,75 into 5075 and calls it
	 * valid, and they work without the map.
	 *
	 * @param int $post_id The waypoint.
	 * @return void
	 */
	private function place_fields( int $post_id ): void {
		$stored   = Waypoint_Coordinates::stored( $post_id );
		$tile_url = Renderer::tile_url_for( Renderer::site_provider_in_use() );
		?>
		<fieldset class="gpxrm-picker" data-gpxrm-tile-url="<?php echo esc_attr( $tile_url ); ?>" data-gpxrm-attribution="<?php echo esc_attr( Renderer::attribution_for( $tile_url ) ); ?>" data-gpxrm-tile-max-zoom="<?php echo esc_attr( (string) Renderer::tile_max_zoom_for( $tile_url ) ); ?>" data-gpxrm-map-ui="<?php echo esc_attr( Renderer::map_ui_json() ); ?>" data-gpxrm-pin-label="<?php esc_attr_e( 'This waypoint\'s place', 'gpx-route-map' ); ?>">
			<legend class="screen-reader-text"><?php esc_html_e( 'This waypoint\'s place', 'gpx-route-map' ); ?></legend>
			<div class="gpxrm-picker-map" role="application" aria-label="<?php esc_attr_e( 'Map to choose this waypoint\'s place on', 'gpx-route-map' ); ?>"></div>
			<p class="gpxrm-picker-failed description" hidden><?php esc_html_e( 'The map could not load. You can still type the coordinates below.', 'gpx-route-map' ); ?></p>
			<p class="description"><?php esc_html_e( 'Click the map to place this waypoint, and drag the pin to move it. Or type its coordinates, in decimal degrees, such as 50.75 and 5.93.', 'gpx-route-map' ); ?></p>
			<p class="gpxrm-picker-fields">
				<label for="gpxrm_lat"><?php esc_html_e( 'Latitude', 'gpx-route-map' ); ?></label>
				<input type="text" id="gpxrm_lat" name="gpxrm_lat" value="<?php echo esc_attr( $stored['lat'] ); ?>" dir="ltr" autocomplete="off">
				<label for="gpxrm_lon"><?php esc_html_e( 'Longitude', 'gpx-route-map' ); ?></label>
				<input type="text" id="gpxrm_lon" name="gpxrm_lon" value="<?php echo esc_attr( $stored['lon'] ); ?>" dir="ltr" autocomplete="off">
				<button type="button" class="button-link gpxrm-picker-clear" hidden><?php esc_html_e( 'Clear', 'gpx-route-map' ); ?></button>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * The page of the box asked for.
	 *
	 * @return int
	 */
	private static function asked_page(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only chooses which rows to show, and read as a number.
		$page = isset( $_GET[ self::PAGE_ARG ] ) ? wp_unslash( $_GET[ self::PAGE_ARG ] ) : '';

		return is_string( $page ) ? absint( $page ) : 1;
	}

	/**
	 * Output the table of maps.
	 *
	 * @param array<int, array<string, mixed>> $rows            The rows of this page.
	 * @param bool                             $has_coordinates Whether the waypoint has coordinates.
	 * @phpstan-param array<int, Row> $rows
	 * @return void
	 */
	private function table( array $rows, bool $has_coordinates ): void {
		?>
		<table class="widefat striped gpxrm-maps">
			<caption class="screen-reader-text"><?php esc_html_e( 'Maps that show this waypoint', 'gpx-route-map' ); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Map', 'gpx-route-map' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Where it shows', 'gpx-route-map' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Change', 'gpx-route-map' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) {
					$this->row( $row, $has_coordinates );
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Output one row.
	 *
	 * @param array<string, mixed> $row             The row.
	 * @param bool                 $has_coordinates Whether the waypoint has coordinates.
	 * @phpstan-param Row $row
	 * @return void
	 */
	public function row( array $row, bool $has_coordinates ): void {
		$host = $row['host'];
		?>
		<tr>
			<th scope="row">
				<?php echo self::title( $host, $row['can_edit'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
				<span class="gpxrm-map-details"><?php echo esc_html( self::details( $row ) ); ?></span>
			</th>
			<td>
				<?php echo esc_html( self::where( $row, $has_coordinates ) ); ?>
				<?php if ( 'wp_block' === get_post_type( $host ) ) : ?>
					<span class="gpxrm-map-details"><?php esc_html_e( 'In a synced pattern, which shows wherever the pattern is used.', 'gpx-route-map' ); ?></span>
				<?php endif; ?>
			</td>
			<td><?php $this->control( $row, $has_coordinates ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Output what can be done about a row: why nothing, or what.
	 *
	 * @param array<string, mixed> $row             The row.
	 * @param bool                 $has_coordinates Whether the waypoint has coordinates.
	 * @phpstan-param Row $row
	 * @return void
	 */
	private function control( array $row, bool $has_coordinates ): void {
		$reason = self::read_only_reason( $row );
		if ( '' !== $reason ) {
			echo esc_html( $reason );
			return;
		}

		$title = wp_strip_all_tags( get_the_title( $row['host'] ) );
		?>
		<select name="<?php echo esc_attr( 'gpxrm_maps[' . self::field( $row ) . ']' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: a post's title. */ __( 'Change for the map in %s', 'gpx-route-map' ), $title ) ); ?>">
			<option value="keep"><?php esc_html_e( 'Keep', 'gpx-route-map' ); ?></option>
			<?php if ( 'own' === $row['where'] ) : ?>
				<option value="own"<?php disabled( ! $has_coordinates ); ?>><?php esc_html_e( 'At its own coordinates', 'gpx-route-map' ); ?></option>
			<?php endif; ?>
			<option value="remove"><?php esc_html_e( 'Remove from this map', 'gpx-route-map' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Why a row cannot be changed from here, if it cannot.
	 *
	 * @param array<string, mixed> $row The row.
	 * @phpstan-param Row $row
	 * @return string
	 */
	private static function read_only_reason( array $row ): string {
		if ( ! $row['can_edit'] ) {
			return __( 'You can\'t edit this post.', 'gpx-route-map' );
		}

		if ( 'block' !== $row['kind'] ) {
			return __( 'In a shortcode. Edit it in the post.', 'gpx-route-map' );
		}

		if ( 'wp_block' === get_post_type( $row['host'] ) ) {
			return __( 'In a synced pattern. Edit it in the pattern.', 'gpx-route-map' );
		}

		return 'file' === $row['where']
			? __( 'Set in the GPX file. Override it in the map\'s block, or edit the file.', 'gpx-route-map' )
			: '';
	}

	/**
	 * The name of a row's field, without the box's own.
	 *
	 * @param array<string, mixed> $row The row.
	 * @phpstan-param Row $row
	 * @return string
	 */
	private static function field( array $row ): string {
		$from = 'added' === $row['where'] ? 'a' : 'p' . $row['position'];

		return sprintf( '%d:b:%d:%s:%s', $row['host'], $row['ordinal'], $row['hash'], $from );
	}

	/**
	 * Output the choice of a map to add the waypoint to. Whether it has a
	 * place yet makes no difference here: choosing a map shows its route on
	 * the map above, the easiest way to choose one, and both save together.
	 *
	 * @param array<int, array<string, mixed>> $maps The maps it could go on.
	 * @phpstan-param array<int, Addable> $maps
	 * @return void
	 */
	private function add_field( array $maps ): void {
		$offered = Waypoint_Map_Rows::addable( $maps );
		if ( array() === $offered ) {
			return;
		}
		?>
		<p class="gpxrm-maps-add">
			<label for="gpxrm_maps_add"><?php esc_html_e( 'Add to a map', 'gpx-route-map' ); ?></label>
			<select id="gpxrm_maps_add" name="gpxrm_maps_add">
				<option value=""><?php esc_html_e( 'Choose a map', 'gpx-route-map' ); ?></option>
				<?php foreach ( $offered as $map ) : ?>
					<option value="<?php echo esc_attr( sprintf( '%d:b:%d:%s', $map['host'], $map['ordinal'], $map['hash'] ) ); ?>" data-gpxrm-gpx="<?php echo esc_url( $map['file'] ); ?>"><?php echo esc_html( self::add_label( $map ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="gpxrm-maps-add-place description" hidden><?php esc_html_e( 'Now click the map above to choose this waypoint\'s place.', 'gpx-route-map' ); ?></p>
		<p class="description"><?php esc_html_e( 'Choosing a map shows its route above. Click Update to add this waypoint to it, at its own coordinates. To place it on one of a file\'s waypoints, use the Waypoints panel of the map\'s block.', 'gpx-route-map' ); ?></p>
		<?php
	}

	/**
	 * How the choice of a map names it.
	 *
	 * @param array<string, mixed> $map The map.
	 * @phpstan-param Addable $map
	 * @return string
	 */
	private static function add_label( array $map ): string {
		$parts = array(
			wp_strip_all_tags( get_the_title( $map['host'] ) ),
			wp_basename( $map['file'] ),
		);

		if ( $map['of'] > 1 ) {
			$parts[] = self::map_number( $map['number'], $map['of'] );
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Which of a post's maps one is.
	 *
	 * @param int $number Which it is, from 1.
	 * @param int $of     How many the post has.
	 * @return string
	 */
	private static function map_number( int $number, int $of ): string {
		return sprintf(
			/* translators: 1: which of a post's maps this is, 2: how many maps the post has. */
			__( 'Map %1$d of %2$d', 'gpx-route-map' ),
			$number,
			$of
		);
	}

	/**
	 * A post's title, escaped: as a link to edit it, or to read it if it can
	 * be read by anyone, otherwise as text.
	 *
	 * @param int  $host     The post.
	 * @param bool $can_edit Whether the user can edit it.
	 * @return string
	 */
	private static function title( int $host, bool $can_edit ): string {
		// Escaped already.
		$title = _draft_or_post_title( $host );
		$link  = $can_edit ? get_edit_post_link( $host ) : '';
		if ( ! $can_edit && is_post_publicly_viewable( $host ) ) {
			$link = get_permalink( $host );
		}

		return is_string( $link ) && '' !== $link
			? sprintf( '<a href="%s">%s</a>', esc_url( $link ), $title )
			: $title;
	}

	/**
	 * What tells one row's map from another's: its post's type and status, its
	 * file, and which of the post's maps it is, when it has several.
	 *
	 * @param array<string, mixed> $row The row.
	 * @phpstan-param Row $row
	 * @return string
	 */
	private static function details( array $row ): string {
		$type   = get_post_type_object( (string) get_post_type( $row['host'] ) );
		$status = get_post_status_object( (string) get_post_status( $row['host'] ) );
		$parts  = array(
			null === $type ? null : $type->labels->singular_name,
			null === $status ? null : $status->label,
			wp_basename( $row['file'] ),
		);

		if ( $row['of'] > 1 ) {
			$parts[] = self::map_number( $row['number'], $row['of'] );
		}

		return implode( ' · ', array_filter( $parts, 'is_string' ) );
	}

	/**
	 * Where a map shows the waypoint.
	 *
	 * @param array{where: string, position: int} $row             The row.
	 * @param bool                                $has_coordinates Whether the waypoint has coordinates.
	 * @return string
	 */
	public static function where( array $row, bool $has_coordinates ): string {
		if ( 'added' === $row['where'] ) {
			return $has_coordinates
				? __( 'At its own coordinates', 'gpx-route-map' )
				: __( 'At its own coordinates, once this waypoint has them', 'gpx-route-map' );
		}

		return sprintf(
			'file' === $row['where']
				/* translators: %d: a waypoint's number in the GPX file. */
				? __( 'Waypoint %d of the GPX file, linked in the file', 'gpx-route-map' )
				/* translators: %d: a waypoint's number in the GPX file. */
				: __( 'Waypoint %d of the GPX file', 'gpx-route-map' ),
			$row['position'] + 1
		);
	}

	/**
	 * Output the links to the other pages of the box, if there are any.
	 *
	 * @param int $page  This page.
	 * @param int $pages How many there are.
	 * @return void
	 */
	private function pagination( int $page, int $pages ): void {
		if ( $pages < 2 ) {
			return;
		}

		$links = paginate_links(
			array(
				'base'         => add_query_arg( self::PAGE_ARG, '%#%', remove_query_arg( wp_removable_query_args() ) ),
				'format'       => '',
				'current'      => $page,
				'total'        => $pages,
				'add_fragment' => '#gpxrm-waypoint-maps',
			)
		);

		echo '<p class="gpxrm-maps-pages">' . wp_kses_post( $links ) . '</p>';
	}

	/**
	 * Output what the list leaves out.
	 *
	 * @param int $hidden     Maps in posts the user cannot read.
	 * @param int $unreadable Posts that could not be read.
	 * @return void
	 */
	private function notes( int $hidden, int $unreadable ): void {
		$notes = array();
		if ( $hidden > 0 ) {
			$notes[] = sprintf(
				/* translators: %d: how many maps. */
				_n( '%d more map is in a post you can\'t view.', '%d more maps are in posts you can\'t view.', $hidden, 'gpx-route-map' ),
				$hidden
			);
		}

		if ( $unreadable > 0 ) {
			$notes[] = sprintf(
				/* translators: %d: how many posts. */
				_n( '%d post could not be read.', '%d posts could not be read.', $unreadable, 'gpx-route-map' ),
				$unreadable
			);
		}

		$notes[] = __( 'Maps added by a widget, a theme file or another plugin are not listed here.', 'gpx-route-map' );

		foreach ( $notes as $note ) {
			echo '<p class="description">' . esc_html( $note ) . '</p>';
		}
	}
}
