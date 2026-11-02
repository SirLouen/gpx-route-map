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
 * The box that lists the maps showing a Waypoint post, from a block, a
 * shortcode or the GPX file, or at its own coordinates. It only reads.
 *
 * @phpstan-import-type Row from Waypoint_Map_Rows
 */
final class Waypoint_Maps_Box {

	/**
	 * The address argument that names a page of the box.
	 */
	const PAGE_ARG = 'gpxrm_maps_page';

	/**
	 * Add the box to the edit screen, after the Waypoint box.
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
			'default'
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
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save the waypoint first.', 'gpx-route-map' ) . '</p>';
			return;
		}

		$found = Waypoint_Map_Rows::for_waypoint( $post->ID );
		$part  = Waypoint_Map_Rows::page( $found['rows'], self::asked_page() );

		$has_coordinates = null !== Waypoint_Coordinates::of( $post->ID );

		if ( array() !== $found['rows'] ) {
			$this->table( $part['rows'], $has_coordinates );
			$this->pagination( $part['page'], $part['pages'] );
		} elseif ( 0 === $found['hidden'] ) {
			echo '<p>' . esc_html__( 'No map shows this waypoint yet.', 'gpx-route-map' ) . '</p>';
		}

		$this->notes( $found['hidden'], $found['unreadable'] );
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
		</tr>
		<?php
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
			$parts[] = sprintf(
				/* translators: 1: which of a post's maps this is, 2: how many maps the post has. */
				__( 'Map %1$d of %2$d', 'gpx-route-map' ),
				$row['number'],
				$row['of']
			);
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
