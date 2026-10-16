<?php
/**
 * A Waypoint post's description: the markup it keeps, and how maps show it.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A description is written in an editor that adds links, bold and italic text
 * and lists. It is stored in WordPress's usual paragraph format and kept to
 * that markup when saved and again when shown.
 */
final class Waypoint_Description {

	/**
	 * A description as its editor writes it, in WordPress's usual paragraph
	 * format: links, bold and italic text and lists are kept, any other
	 * markup goes, as do invalid UTF-8 and control characters, and line
	 * breaks become \n. The visual editor writes a "<" typed as text as an
	 * entity, so it stays.
	 *
	 * @param string $text Submitted description.
	 * @return string
	 */
	public static function sanitize( string $text ): string {
		$text = str_replace( array( "\r\n", "\r" ), "\n", wp_check_invalid_utf8( $text ) );
		$text = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );

		return trim( wp_kses( $text, self::tags() ) );
	}

	/**
	 * The markup a description keeps: what its editor's buttons write.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function tags(): array {
		$link = array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		);

		return array(
			'a'      => $link,
			'b'      => array(),
			'br'     => array(),
			'em'     => array(),
			'i'      => array(),
			'li'     => array(),
			'ol'     => array(),
			'p'      => array(),
			'strong' => array(),
			'ul'     => array(),
		);
	}

	/**
	 * A description as the map shows it: its markup kept to what a
	 * description may hold, as its meta can be written by other means than
	 * its box, and its paragraphs and line breaks as HTML.
	 *
	 * @param string $text Stored description.
	 * @return string
	 */
	public static function html( string $text ): string {
		return trim( wpautop( wp_kses( $text, self::tags() ) ) );
	}
}
