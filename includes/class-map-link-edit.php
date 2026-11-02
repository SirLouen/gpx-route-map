<?php
/**
 * Changing one map's Waypoint links in a post's content.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Changes the links of one map block: bytes in, bytes out.
 *
 * Only that map's delimiter changes, and only the attribute the change is
 * about. Parsing a post and writing it back, or any pattern over delimiters,
 * damages other blocks: {} becomes [], escapes are decoded, closers are lost.
 *
 * A change is refused, never guessed at: when the map is not what the change
 * expects, or anything looks different afterwards.
 *
 * @phpstan-import-type Token from Map_Tokens
 */
final class Map_Link_Edit {

	const LINKS = 'waypointPosts';
	const ADDED = 'addedWaypoints';

	/**
	 * The content with one change made to one map.
	 *
	 * @param string $content  Post content.
	 * @param int    $ordinal  Which of its map blocks, counting from 0.
	 * @param int    $waypoint The Waypoint post.
	 * @param string $op       'remove-position', 'own', 'remove-added' or 'add'.
	 * @param int    $position The waypoint of the file, for the first two.
	 * @return string|null The new content, or null to refuse.
	 */
	public static function apply( string $content, int $ordinal, int $waypoint, string $op, int $position = -1 ): ?string {
		$maps = Map_Tokens::walk( $content )['maps'];
		$map  = $maps[ $ordinal ] ?? null;
		if ( null === $map || null === $map['attrs'] || ! self::parser_agrees( $maps, $content ) ) {
			return null;
		}

		$attributes = self::attributes( substr( $content, $map['start'], $map['length'] ) );
		if ( null === $attributes || ! self::change( $attributes, $waypoint, $op, $position ) ) {
			return null;
		}

		$changed = substr_replace( $content, self::delimiter( $attributes, $map['type'] ), $map['start'], $map['length'] );

		return self::only_this_changed( $maps, $changed, $ordinal, $attributes ) ? $changed : null;
	}

	/**
	 * Whether WordPress's parser finds as many maps as the walk. It does,
	 * unless a site swapped it through block_parser_class, and then neither
	 * count can be taken for the other.
	 *
	 * @param array<int, array<string, mixed>> $maps    The maps the walk found.
	 * @param string                           $content Post content.
	 * @phpstan-param array<int, Token> $maps
	 * @return bool
	 */
	private static function parser_agrees( array $maps, string $content ): bool {
		return count( $maps ) === self::count_maps( parse_blocks( $content ) );
	}

	/**
	 * How many maps some parsed blocks hold, however deep.
	 *
	 * @param array<mixed> $blocks Parsed blocks.
	 * @return int
	 */
	private static function count_maps( array $blocks ): int {
		$count = 0;
		foreach ( $blocks as $block ) {
			if ( is_array( $block ) ) {
				$count += Map_Tokens::BLOCK === ( $block['blockName'] ?? null ) ? 1 : 0;
				$count += self::count_maps( is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array() );
			}
		}

		return $count;
	}

	/**
	 * A delimiter's attributes, as an object, so {} stays {} when written
	 * back. PHP reads some keys into an array but not into an object.
	 *
	 * @param string $delimiter The delimiter.
	 * @return \stdClass|null
	 */
	private static function attributes( string $delimiter ): ?\stdClass {
		$open = strpos( $delimiter, '{' );
		if ( false === $open ) {
			return new \stdClass();
		}

		$decoded = json_decode( substr( $delimiter, $open, (int) strrpos( $delimiter, '}' ) - $open + 1 ) );

		return $decoded instanceof \stdClass ? $decoded : null;
	}

	/**
	 * Make a change, if the map holds what the change expects.
	 *
	 * @param \stdClass $attributes The map's attributes.
	 * @param int       $waypoint   The Waypoint post.
	 * @param string    $op         The change.
	 * @param int       $position   The waypoint of the file.
	 * @return bool Whether it could be made. If not, the attributes are not
	 *              fit to use.
	 */
	private static function change( \stdClass $attributes, int $waypoint, string $op, int $position ): bool {
		$stored = $attributes->{self::LINKS} ?? null;
		$links  = Waypoint_Links::from_attribute( is_scalar( $stored ) || null === $stored ? null : (array) $stored );
		$added  = Waypoint_Links::added_from_attribute( $attributes->{self::ADDED} ?? null );
		$linked = ( $links[ $position ] ?? 0 ) === $waypoint;
		$listed = in_array( $waypoint, $added, true );

		switch ( $op ) {
			case 'remove-position':
				unset( $links[ $position ] );
				self::set_links( $attributes, $links );
				return $linked;

			case 'own':
				unset( $links[ $position ] );
				self::set_links( $attributes, $links );
				self::set_added( $attributes, array_values( array_unique( array_merge( $added, array( $waypoint ) ) ) ) );
				return $linked;

			case 'remove-added':
				self::set_added( $attributes, array_values( array_diff( $added, array( $waypoint ) ) ) );
				return $listed;

			case 'add':
				self::set_added( $attributes, array_merge( $added, array( $waypoint ) ) );
				return ! $listed && ! in_array( $waypoint, $links, true );
		}

		return false;
	}

	/**
	 * Store a map's links, by position, as an object whatever they were, or
	 * nothing when none are left.
	 *
	 * @param \stdClass       $attributes The map's attributes.
	 * @param array<int, int> $links      Post IDs by waypoint position.
	 * @return void
	 */
	private static function set_links( \stdClass $attributes, array $links ): void {
		if ( array() === $links ) {
			unset( $attributes->{self::LINKS} );
			return;
		}

		$attributes->{self::LINKS} = (object) $links;
	}

	/**
	 * Store a map's added posts, or nothing when none are left.
	 *
	 * @param \stdClass       $attributes The map's attributes.
	 * @param array<int, int> $added      Post IDs.
	 * @return void
	 */
	private static function set_added( \stdClass $attributes, array $added ): void {
		if ( array() === $added ) {
			unset( $attributes->{self::ADDED} );
			return;
		}

		$attributes->{self::ADDED} = $added;
	}

	/**
	 * A delimiter for a map, in the form it was stored in.
	 *
	 * @param \stdClass $attributes The map's attributes.
	 * @param string    $type       'void-block' or 'block-opener'.
	 * @return string
	 */
	private static function delimiter( \stdClass $attributes, string $type ): string {
		$json = array() === get_object_vars( $attributes ) ? '' : serialize_block_attributes( (array) $attributes ) . ' ';

		return '<!-- wp:' . Map_Tokens::BLOCK . ' ' . $json . ( 'void-block' === $type ? '/-->' : '-->' );
	}

	/**
	 * Whether the new content reads as it should: every other map as before,
	 * and this one as its new attributes. This catches the attribute encoder
	 * of WordPress 6.8, which breaks the JSON of a value ending in a backslash.
	 *
	 * @param array<int, array<string, mixed>> $before     The maps before.
	 * @param string                           $changed    The new content.
	 * @param int                              $ordinal    The map changed.
	 * @param \stdClass                        $attributes Its new attributes.
	 * @phpstan-param array<int, Token> $before
	 * @return bool
	 */
	private static function only_this_changed( array $before, string $changed, int $ordinal, \stdClass $attributes ): bool {
		$after = Map_Tokens::walk( $changed )['maps'];
		if ( count( $after ) !== count( $before ) ) {
			return false;
		}

		$wanted = array_column( $before, 'attrs' );

		$wanted[ $ordinal ] = json_decode( (string) wp_json_encode( $attributes ), true );

		return array_column( $after, 'attrs' ) === $wanted;
	}
}
