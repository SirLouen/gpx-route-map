<?php
/**
 * The maps a post's content holds.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the maps of a post, in blocks and in shortcodes, with the file and
 * the links each one shows. The renderer's own code says which file a map
 * shows, so this can never read one differently from the map itself.
 *
 * Keeps what it read from each GPX file, as many maps can show the same one,
 * so one reader serves a whole pass over a site's posts.
 *
 * It finds shortcodes by their tag only, which is enough for a list: one
 * inside [caption], or in a wrapper that prints its content as text, counts
 * though it does not show.
 *
 * @phpstan-type Map array{kind: string, ordinal: int, start: int, file_id: int, file_url: string, own: array<int, int>, links: array<int, int>, added: array<int, int>}
 */
class Post_Maps {

	const SHORTCODE = 'gpx_route_map';

	/**
	 * What each file links, by its ID and address.
	 *
	 * @var array<string, array<int, int>>
	 */
	private $file_links = array();

	/**
	 * The maps of a post, in the order of its content.
	 *
	 * `ordinal` counts within its kind, including a map with no file or with
	 * attributes that are not JSON, which have no file and link nothing.
	 *
	 * @param string $content Post content.
	 * @return array<int, array<string, mixed>>|null Null when the content
	 *         cannot be read, which is not the same as holding no maps.
	 * @phpstan-return array<int, Map>|null
	 */
	public function read( string $content ): ?array {
		$walk       = Map_Tokens::walk( $content );
		$shortcodes = $this->shortcodes( self::blanked( $content, $walk['spans'] ) );
		if ( null === $shortcodes ) {
			return null;
		}

		$maps = $shortcodes;
		foreach ( $walk['maps'] as $ordinal => $token ) {
			$maps[] = $this->map( 'block', $ordinal, $token['start'], $token['attrs'] ?? array() );
		}
		usort(
			$maps,
			static function ( array $one, array $other ): int {
				return $one['start'] <=> $other['start'];
			}
		);

		return $maps;
	}

	/**
	 * The content with its block delimiters blanked, at the same length, so a
	 * shortcode written in a block's attributes is not taken for one.
	 *
	 * @param string                            $content Post content.
	 * @param array<int, array{0: int, 1: int}> $spans   Delimiters, as start and length.
	 * @return string
	 */
	private static function blanked( string $content, array $spans ): string {
		$blanked = '';
		$from    = 0;
		foreach ( $spans as list( $start, $length ) ) {
			$blanked .= substr( $content, $from, $start - $from ) . str_repeat( ' ', $length );
			$from     = $start + $length;
		}

		return $blanked . substr( $content, $from );
	}

	/**
	 * The shortcode maps of a post.
	 *
	 * @param string $text Post content, with its block delimiters blanked.
	 * @return array<int, array<string, mixed>>|null Null when it cannot be read.
	 * @phpstan-return array<int, Map>|null
	 */
	private function shortcodes( string $text ): ?array {
		if ( false === strpos( $text, '[' . self::SHORTCODE ) ) {
			return array();
		}

		$matches = $this->shortcode_matches( $text );
		if ( null === $matches ) {
			return null;
		}

		$maps = array();
		foreach ( $matches as $match ) {
			// [[gpx_route_map]] prints the shortcode as text.
			if ( '[' !== $match[1][0] || ']' !== $match[6][0] ) {
				$maps[] = $this->map( 'shortcode', count( $maps ), $match[0][1], self::shortcode_attributes( $match[3][0] ) );
			}
		}

		return $maps;
	}

	/**
	 * Every place the text holds the shortcode, with the groups of WordPress's
	 * own pattern, each with its offset.
	 *
	 * @param string $text Text.
	 * @return array<int, array<array{0: string, 1: int}>>|null Null when the
	 *         text is too much for the pattern.
	 */
	protected function shortcode_matches( string $text ): ?array {
		$found = preg_match_all( '/' . get_shortcode_regex( array( self::SHORTCODE ) ) . '/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

		return false === $found ? null : $matches;
	}

	/**
	 * A shortcode's attributes, as a block would hold them.
	 *
	 * @param string $text The attributes as written.
	 * @return array<mixed>
	 */
	private static function shortcode_attributes( string $text ): array {
		// As the shortcode reads them, so a site's filter on them counts here.
		$atts  = shortcode_atts( Plugin::shortcode_defaults(), shortcode_parse_atts( $text ), self::SHORTCODE );
		$links = is_string( $atts['waypoints'] ) ? $atts['waypoints'] : '';

		return Plugin::shortcode_file( $atts ) + array(
			'waypointPosts'  => Waypoint_Links::from_shortcode( $links ),
			'addedWaypoints' => Waypoint_Links::added_from_shortcode( $links ),
		);
	}

	/**
	 * One map, from attributes as a block holds them.
	 *
	 * @param string       $kind    'block' or 'shortcode'.
	 * @param int          $ordinal Its place among maps of its kind.
	 * @param int          $start   Where it starts, in bytes.
	 * @param array<mixed> $attrs   Its attributes.
	 * @return array<string, mixed>
	 * @phpstan-return Map
	 */
	private function map( string $kind, int $ordinal, int $start, array $attrs ): array {
		$file = Renderer::file_of( $attrs );
		$own  = Waypoint_Links::from_attribute( $attrs['waypointPosts'] ?? null );

		// The map's own links win over the file's, as on the map.
		$links = $own + $this->links_in( $file['id'], $file['url'] );
		ksort( $links );

		return array(
			'kind'     => $kind,
			'ordinal'  => $ordinal,
			'start'    => $start,
			'file_id'  => $file['id'],
			'file_url' => $file['url'],
			'own'      => $own,
			'links'    => $links,
			'added'    => Waypoint_Links::added_from_attribute( $attrs['addedWaypoints'] ?? null ),
		);
	}

	/**
	 * What a file links, read once whatever number of maps show it. This also
	 * reads files no map has rendered yet, and keeps what it read with the
	 * attachment, as showing the map would, which is what makes the next pass
	 * quick. Never a file on another site: the address is text a Contributor
	 * can set, and an admin's screen must not ask for what a lower role chose.
	 *
	 * @param int    $attachment_id The file's attachment, or 0.
	 * @param string $url           Its address.
	 * @return array<int, int> Post IDs by waypoint position.
	 */
	private function links_in( int $attachment_id, string $url ): array {
		if ( '' === $url ) {
			return array();
		}

		$key = $attachment_id . ':' . $url;
		if ( ! isset( $this->file_links[ $key ] ) ) {
			$this->file_links[ $key ] = Waypoint_Map_Markers::file_links( $attachment_id, $url );
		}

		return $this->file_links[ $key ];
	}
}
