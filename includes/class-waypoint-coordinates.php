<?php
/**
 * A Waypoint post's coordinates.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads coordinates as an author types them, and as a post stores them: text
 * in decimal degrees with a dot, both or neither, so that a post has the same
 * coordinates whatever the server's locale.
 *
 * Never a cast on what was typed: (float) '50,75' is 50, about 83 km off,
 * with no error.
 */
final class Waypoint_Coordinates {

	const MAX_LAT = 90;
	const MAX_LON = 180;

	/**
	 * Coordinates as typed, ready to store.
	 *
	 * @param string $lat Typed latitude.
	 * @param string $lon Typed longitude.
	 * @return array{lat: string, lon: string}|null The text to store, '' for
	 *         both when both fields are empty, or null for anything that is
	 *         not a latitude and a longitude.
	 */
	public static function parse( string $lat, string $lon ): ?array {
		$lat = self::tidy( $lat );
		$lon = self::tidy( $lon );
		if ( '' === $lat && '' === $lon ) {
			return array(
				'lat' => '',
				'lon' => '',
			);
		}

		$lat = self::number( $lat, self::MAX_LAT );
		$lon = self::number( $lon, self::MAX_LON );
		if ( null === $lat || null === $lon ) {
			return null;
		}

		return array(
			'lat' => $lat,
			'lon' => $lon,
		);
	}

	/**
	 * A post's coordinates as stored, for its box.
	 *
	 * @param int $post_id Post ID.
	 * @return array{lat: string, lon: string} '' for both without any.
	 */
	public static function stored( int $post_id ): array {
		$lat = get_post_meta( $post_id, Waypoints::META_LAT, true );
		$lon = get_post_meta( $post_id, Waypoints::META_LON, true );

		return array(
			'lat' => is_string( $lat ) ? $lat : '',
			'lon' => is_string( $lon ) ? $lon : '',
		);
	}

	/**
	 * A post's coordinates, as its meta can be written by other means than
	 * its box: both must be good, else it has none.
	 *
	 * @param int $post_id Post ID.
	 * @return array{lat: float, lon: float}|null
	 */
	public static function of( int $post_id ): ?array {
		$stored = self::stored( $post_id );
		$lat    = self::number( $stored['lat'], self::MAX_LAT );
		$lon    = self::number( $stored['lon'], self::MAX_LON );
		if ( null === $lat || null === $lon ) {
			return null;
		}

		return array(
			'lat' => (float) $lat,
			'lon' => (float) $lon,
		);
	}

	/**
	 * Typed text without what a paste can bring: a space that cannot end a
	 * line, or a minus sign in place of a hyphen.
	 *
	 * @param string $text Typed text.
	 * @return string
	 */
	private static function tidy( string $text ): string {
		return trim( str_replace( array( "\xC2\xA0", "\xE2\x88\x92" ), array( ' ', '-' ), $text ) );
	}

	/**
	 * A number in decimal degrees, with a dot or a comma, as text to store:
	 * with a dot, at most 7 decimals, about a centimetre, and no sign on
	 * zero. Only the digits 0 to 9, as the pattern has no /u.
	 *
	 * @param string $text  Text.
	 * @param int    $limit The largest number it may be, either way.
	 * @return string|null Null for anything else.
	 */
	private static function number( string $text, int $limit ): ?string {
		if ( 1 !== preg_match( '/^([+-]?)([0-9]{1,3})(?:[.,]([0-9]+))?$/D', $text, $parts ) ) {
			return null;
		}

		$whole    = ltrim( $parts[2], '0' );
		$whole    = '' === $whole ? '0' : $whole;
		$decimals = rtrim( substr( $parts[3] ?? '', 0, 7 ), '0' );
		$number   = '' === $decimals ? $whole : $whole . '.' . $decimals;

		if ( (float) $number > $limit ) {
			return null;
		}

		return '-' === $parts[1] && '0' !== $number ? '-' . $number : $number;
	}
}
