<?php
/**
 * A Waypoint post's saved settings, as its screens show them.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads a Waypoint post's saved settings for its settings box and the list of
 * waypoints, kept to values those can show: an icon of the font, colours as
 * #rrggbb, with the map's own where none is saved.
 */
final class Waypoint_Settings {

	/**
	 * The post's ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * A Waypoint post's settings.
	 *
	 * @param int $post_id The post's ID.
	 */
	public function __construct( int $post_id ) {
		$this->post_id = $post_id;
	}

	/**
	 * The description, as saved.
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->text( Waypoints::META_DESCRIPTION );
	}

	/**
	 * The chosen marker, or '' for the usual one.
	 *
	 * @return string
	 */
	public function marker(): string {
		return Waypoint_Values::sanitize_marker( $this->text( Waypoints::META_MARKER ) );
	}

	/**
	 * The icon of an icon marker.
	 *
	 * @return string
	 */
	public function icon(): string {
		$icon = $this->text( Waypoints::META_ICON );
		return Dashicons::exists( $icon ) ? $icon : Waypoints::DEFAULT_ICON;
	}

	/**
	 * The icon's colour.
	 *
	 * @return string
	 */
	public function color(): string {
		return Waypoint_Values::hex_color( $this->text( Waypoints::META_COLOR ), Waypoints::DEFAULT_COLOR );
	}

	/**
	 * The colour behind the icon.
	 *
	 * @return string
	 */
	public function background(): string {
		return Waypoint_Values::hex_color( $this->text( Waypoints::META_BACKGROUND ), Waypoints::DEFAULT_BACKGROUND );
	}

	/**
	 * Markup of the icon marker, as the map draws it.
	 *
	 * @return string
	 */
	public function icon_html(): string {
		return sprintf(
			'<span class="gpxrm-marker-preview" style="color:%1$s;background-color:%2$s"><span class="dashicons dashicons-%3$s" aria-hidden="true"></span></span>',
			esc_attr( $this->color() ),
			esc_attr( $this->background() ),
			esc_attr( $this->icon() )
		);
	}

	/**
	 * A stored text field of the post.
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	private function text( string $key ): string {
		$value = get_post_meta( $this->post_id, $key, true );
		return is_string( $value ) ? $value : '';
	}
}
