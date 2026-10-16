<?php
/**
 * Reading the links a GPX file holds to Waypoint posts.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One pass over a GPX file, as a stream, for the Waypoint posts its
 * waypoints name inside their <extensions>, by each waypoint's position among
 * the file's <wpt> elements, as the map counts them.
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XMLReader's own properties.
 */
final class Gpx_Link_Reader {

	/**
	 * The file, opened.
	 *
	 * @var \XMLReader
	 */
	private $reader;

	/**
	 * The post IDs found so far, by waypoint position.
	 *
	 * @var array<int, int>
	 */
	private $links = array();

	/**
	 * The open waypoint's position, counting from 0.
	 *
	 * @var int
	 */
	private $index = -1;

	/**
	 * The open waypoint's depth, or -1 between waypoints.
	 *
	 * @var int
	 */
	private $depth = -1;

	/**
	 * Whether this site can read the links a GPX file holds, which takes
	 * PHP's XMLReader extension. PHP builds it in by default, but a host can
	 * leave it out. Links made in a block or shortcode work without it.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return class_exists( '\XMLReader' );
	}

	/**
	 * The links in a GPX file, read up to its end or its first error.
	 * External entities and network access stay off, and libxml reports
	 * nothing.
	 *
	 * @param string $path GPX file.
	 * @return array<int, int>|null Post IDs by waypoint position, in order,
	 *                              or null when the file could not be read.
	 */
	public static function read( string $path ): ?array {
		if ( ! self::available() || ! is_readable( $path ) ) {
			return null;
		}

		$internal = libxml_use_internal_errors( true );
		$reader   = new \XMLReader();

		try {
			return $reader->open( $path, null, LIBXML_NONET ) ? ( new self( $reader ) )->links() : null;
		} finally {
			$reader->close();
			libxml_clear_errors();
			libxml_use_internal_errors( $internal );
		}
	}

	/**
	 * Start a pass over an opened file.
	 *
	 * @param \XMLReader $reader The file, opened.
	 */
	private function __construct( \XMLReader $reader ) {
		$this->reader = $reader;
	}

	/**
	 * Read the file and give the links found.
	 *
	 * @return array<int, int> Post IDs by waypoint position, in order.
	 */
	private function links(): array {
		while ( $this->reader->read() ) {
			if ( \XMLReader::ELEMENT === $this->reader->nodeType ) {
				$this->element();
			}
		}

		return array_slice( $this->links, 0, Waypoints::MAX_LINKS, true );
	}

	/**
	 * Take in the element the reader is on: a waypoint opens, and inside one,
	 * its first link element counts.
	 *
	 * @return void
	 */
	private function element(): void {
		if ( $this->reader->depth <= $this->depth ) {
			$this->depth = -1;
		}

		if ( 'wpt' === $this->reader->localName ) {
			++$this->index;
			$this->depth = $this->reader->depth;
		} elseif ( $this->depth >= 0 && ! isset( $this->links[ $this->index ] ) ) {
			$this->link();
		}
	}

	/**
	 * Keep the post ID a link element holds, when the reader is on one.
	 *
	 * @return void
	 */
	private function link(): void {
		if ( Waypoints::GPX_ELEMENT !== $this->reader->localName || Waypoints::GPX_NAMESPACE !== $this->reader->namespaceURI ) {
			return;
		}

		$id = Waypoint_Links::post_id( $this->reader->readString() );
		if ( $id > 0 ) {
			$this->links[ $this->index ] = $id;
		}
	}
}
