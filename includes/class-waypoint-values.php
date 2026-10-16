<?php
/**
 * Checking the settings of a Waypoint post.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps a Waypoint post's settings to values the map can use: its marker,
 * icon and colours, and its description through Waypoint_Description.
 */
final class Waypoint_Values {

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
		$color       = self::hex_color( $raw['color'] ?? '', '' );
		$background  = self::hex_color( $raw['background'] ?? '', '' );

		return array(
			'description' => is_string( $description ) ? Waypoint_Description::sanitize( $description ) : '',
			'marker'      => $marker,
			'icon'        => is_string( $icon ) && Dashicons::exists( $icon ) ? $icon : ( 'icon' === $marker ? Waypoints::DEFAULT_ICON : '' ),
			'color'       => Waypoints::DEFAULT_COLOR === $color ? '' : $color,
			'background'  => Waypoints::DEFAULT_BACKGROUND === $background ? '' : $background,
		);
	}

	/**
	 * A marker choice the map knows, or '' for the usual marker.
	 *
	 * @param mixed $value Stored or submitted value.
	 * @return string
	 */
	public static function sanitize_marker( $value ): string {
		return is_string( $value ) && in_array( $value, Waypoints::MARKERS, true ) ? $value : '';
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
}
