<?php
/**
 * The columns the list of Waypoint posts gains.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds to the list of waypoints a preview of each one's marker, and its ID,
 * which a GPX file links with.
 */
final class Waypoint_Columns {

	/**
	 * Add the columns, after the title.
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
		} elseif ( 'gpxrm_marker' === $column ) {
			$this->marker( new Waypoint_Settings( $post_id ), (int) get_post_thumbnail_id( $post_id ) );
		}
	}

	/**
	 * Output a marker cell: the marker as the map draws it, named for screen
	 * readers.
	 *
	 * @param Waypoint_Settings $settings The post's settings.
	 * @param int               $photo    The post's photo, or 0 for none.
	 * @return void
	 */
	private function marker( Waypoint_Settings $settings, int $photo ): void {
		$marker = $settings->marker();

		if ( 'image' === $marker && $photo > 0 ) {
			// Named by the text after it, as the photo's own alt describes
			// the photo rather than the marker.
			echo wp_get_attachment_image(
				$photo,
				'thumbnail',
				false,
				array(
					'class' => 'gpxrm-marker-thumb',
					'alt'   => '',
				)
			);
			echo '<span class="screen-reader-text">' . esc_html__( 'Photo', 'gpx-route-map' ) . '</span>';
		} elseif ( 'icon' === $marker ) {
			echo $settings->icon_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside.
			echo '<span class="screen-reader-text">' . esc_html(
				sprintf(
					/* translators: %s: a Dashicon's name, such as "location". */
					__( 'Icon: %s', 'gpx-route-map' ),
					$settings->icon()
				)
			) . '</span>';
		} else {
			echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Usual marker', 'gpx-route-map' ) . '</span>';
		}
	}
}
