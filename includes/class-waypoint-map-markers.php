<?php
/**
 * What one map is told about its waypoints linked to Waypoint posts.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What one map shows for its waypoints linked to Waypoint posts, by their
 * position in its GPX file, and for the posts added to it at their own
 * coordinates.
 *
 * @phpstan-import-type Marker from Waypoints
 * @phpstan-import-type Added from Waypoints
 */
final class Waypoint_Map_Markers {

	/**
	 * What the map shows, by waypoint position.
	 *
	 * @var array<int, array<string, mixed>>
	 * @phpstan-var array<int, Marker>
	 */
	private $markers;

	/**
	 * What the map shows for the posts added to it, in order.
	 *
	 * @var array<int, array<string, mixed>>
	 * @phpstan-var array<int, Added>
	 */
	private $added;

	/**
	 * What a map shows for its linked waypoints and added posts. Nothing
	 * while the feature is off.
	 *
	 * @param int             $attachment_id The map's GPX attachment, or 0.
	 * @param string          $gpx_url       The map's GPX URL.
	 * @param array<int, int> $chosen        Links the block or shortcode made.
	 * @param array<int, int> $added         Posts it adds at their own coordinates.
	 * @return self
	 */
	public static function for_map( int $attachment_id, string $gpx_url, array $chosen, array $added = array() ): self {
		if ( ! Waypoints::enabled() ) {
			return new self( array() );
		}

		// The block's own links win over the file's.
		$links = $chosen + self::file_links( $attachment_id, $gpx_url );
		ksort( $links );

		$posts = new Waypoint_Posts( $links, $added );

		return new self( $posts->markers(), $posts->added() );
	}

	/**
	 * What a map's GPX file links, as a Media Library file. Nothing for a file
	 * on another site, which is never fetched.
	 *
	 * @param int    $attachment_id The map's GPX attachment, or 0.
	 * @param string $gpx_url       The map's GPX URL.
	 * @return array<int, int> Post IDs by waypoint position.
	 */
	public static function file_links( int $attachment_id, string $gpx_url ): array {
		// A pasted address of a file in the Media Library is the same file.
		if ( $attachment_id <= 0 && '' !== $gpx_url ) {
			$attachment_id = attachment_url_to_postid( $gpx_url );
		}

		return $attachment_id > 0 ? ( new Gpx_File_Links( $attachment_id ) )->links() : array();
	}

	/**
	 * What a map shows for its linked waypoints and added posts.
	 *
	 * @param array<int, array<string, mixed>> $markers What the map shows, by position.
	 * @param array<int, array<string, mixed>> $added   What it shows for added posts, in order.
	 * @phpstan-param array<int, Marker> $markers
	 * @phpstan-param array<int, Added> $added
	 */
	public function __construct( array $markers, array $added = array() ) {
		$this->markers = $markers;
		$this->added   = $added;
	}

	/**
	 * What the map shows, by waypoint position.
	 *
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Marker>
	 */
	public function all(): array {
		return $this->markers;
	}

	/**
	 * What the map shows for the posts added to it, in order.
	 *
	 * @return array<int, array<string, mixed>>
	 * @phpstan-return array<int, Added>
	 */
	public function added(): array {
		return $this->added;
	}

	/**
	 * How many waypoints the added posts bring to the map: those no waypoint
	 * of the file links to, as a post shows once. The map corrects this when
	 * it finds such a waypoint is not one it can draw.
	 *
	 * @return int
	 */
	public function added_count(): int {
		$count = 0;
		foreach ( $this->added as $post ) {
			$count += isset( $post['at'] ) ? 0 : 1;
		}

		return $count;
	}

	/**
	 * Whether any of the map's waypoints is drawn as an icon, which needs the
	 * Dashicons font.
	 *
	 * @return bool
	 */
	public function uses_icons(): bool {
		foreach ( array_merge( $this->markers, $this->added ) as $marker ) {
			if ( 'icon' === ( $marker['marker'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * JSON of what the map shows for its waypoints, for a data attribute.
	 *
	 * Always an object keyed by position, even when the positions run from 0
	 * without a gap. JSON_HEX_AMP for the reason Renderer::attr_json() gives.
	 *
	 * @return string
	 */
	public function json(): string {
		return (string) wp_json_encode( (object) $this->markers, JSON_HEX_AMP );
	}

	/**
	 * JSON of what the map shows for the posts added to it, for a data
	 * attribute: a list, in order.
	 *
	 * @return string
	 */
	public function added_json(): string {
		return (string) wp_json_encode( $this->added, JSON_HEX_AMP );
	}
}
