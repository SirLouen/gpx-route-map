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
 * position in its GPX file.
 *
 * @phpstan-import-type Marker from Waypoints
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
	 * What a map shows for its linked waypoints. Nothing while the feature
	 * is off.
	 *
	 * @param int             $attachment_id The map's GPX attachment, or 0.
	 * @param string          $gpx_url       The map's GPX URL.
	 * @param array<int, int> $chosen        Links the block or shortcode made.
	 * @return self
	 */
	public static function for_map( int $attachment_id, string $gpx_url, array $chosen ): self {
		if ( ! Waypoints::enabled() ) {
			return new self( array() );
		}

		// A pasted address of a file in the Media Library is the same file.
		if ( $attachment_id <= 0 && '' !== $gpx_url ) {
			$attachment_id = attachment_url_to_postid( $gpx_url );
		}

		// The block's own links win over the file's.
		$links = $chosen + ( $attachment_id > 0 ? ( new Gpx_File_Links( $attachment_id ) )->links() : array() );
		ksort( $links );

		return new self( ( new Waypoint_Posts( $links ) )->markers() );
	}

	/**
	 * What a map shows for its linked waypoints.
	 *
	 * @param array<int, array<string, mixed>> $markers What the map shows, by position.
	 * @phpstan-param array<int, Marker> $markers
	 */
	public function __construct( array $markers ) {
		$this->markers = $markers;
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
	 * Whether any of the map's waypoints is drawn as an icon, which needs the
	 * Dashicons font.
	 *
	 * @return bool
	 */
	public function uses_icons(): bool {
		foreach ( $this->markers as $marker ) {
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
}
