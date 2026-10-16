<?php
/**
 * The links a GPX file in the Media Library holds to Waypoint posts.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The posts a GPX attachment's file links its waypoints to.
 *
 * Read from the file once, then kept with the attachment until the file
 * changes, so a map does not read its whole track on every page view.
 */
class Gpx_File_Links {

	/**
	 * The attachment.
	 *
	 * @var int
	 */
	private $attachment_id;

	/**
	 * Its file, or '' for none.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * The links of a GPX attachment's file.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function __construct( int $attachment_id ) {
		$path = get_attached_file( $attachment_id );

		$this->attachment_id = $attachment_id;
		$this->path          = is_string( $path ) ? $path : '';
	}

	/**
	 * The posts the file links its waypoints to.
	 *
	 * @return array<int, int> Post IDs by waypoint position, in order.
	 */
	public function links(): array {
		$version = $this->version();
		if ( '' === $version ) {
			return array();
		}

		$kept = $this->kept( $version );
		if ( null !== $kept ) {
			return $kept;
		}

		// A read that could not run is not kept, so the file is read again
		// once it can be, such as after the host adds XMLReader.
		$links = $this->read();
		if ( null === $links ) {
			return array();
		}

		update_post_meta(
			$this->attachment_id,
			Waypoints::META_FILE_LINKS,
			array(
				'file'  => $version,
				'links' => $links,
			)
		);

		return $links;
	}

	/**
	 * The links kept from this version of the file.
	 *
	 * @param string $version The file's version (see version()).
	 * @return array<int, int>|null Post IDs by waypoint position, in order,
	 *                              or null when none are kept for it.
	 */
	private function kept( string $version ): ?array {
		$stored = get_post_meta( $this->attachment_id, Waypoints::META_FILE_LINKS, true );
		if ( ! is_array( $stored ) || ! isset( $stored['file'], $stored['links'] ) ) {
			return null;
		}

		return $version === $stored['file'] ? Waypoint_Links::from_attribute( $stored['links'] ) : null;
	}

	/**
	 * What tells the file from an earlier one at the same path: its size and
	 * when it was last written. '' for a path that is not a readable GPX file.
	 *
	 * @return string
	 */
	private function version(): string {
		if ( '' === $this->path || ! Plugin::looks_like_gpx( $this->path ) ) {
			return '';
		}

		$size     = filesize( $this->path );
		$modified = filemtime( $this->path );

		return false === $size || false === $modified ? '' : $size . ':' . $modified;
	}

	/**
	 * Read the links in the file. Protected, so a test can stand in for a
	 * site that cannot read it.
	 *
	 * @return array<int, int>|null Post IDs by waypoint position, in order,
	 *                              or null when the file could not be read.
	 */
	protected function read(): ?array {
		return Gpx_Link_Reader::read( $this->path );
	}
}
