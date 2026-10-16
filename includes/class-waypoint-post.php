<?php
/**
 * A published Waypoint post, as a map shows it.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads what a map shows for a published Waypoint post: its title, its
 * description, its photo and its marker settings.
 *
 * @phpstan-import-type Marker from Waypoints
 */
final class Waypoint_Post {

	/**
	 * The post.
	 *
	 * @var \WP_Post
	 */
	private $post;

	/**
	 * Its photo, or 0 for none.
	 *
	 * @var int
	 */
	private $image;

	/**
	 * A published Waypoint post.
	 *
	 * @param \WP_Post $post The post.
	 */
	public function __construct( \WP_Post $post ) {
		$this->post  = $post;
		$this->image = (int) get_post_thumbnail_id( $post );
	}

	/**
	 * What a map shows for the post.
	 *
	 * @return array<string, mixed>
	 * @phpstan-return Marker
	 */
	public function marker(): array {
		$marker = new Waypoint_Marker(
			array(
				'name'       => self::plain( get_the_title( $this->post ) ),
				'desc'       => Waypoint_Description::html( $this->text( Waypoints::META_DESCRIPTION ) ),
				'marker'     => $this->text( Waypoints::META_MARKER ),
				'icon'       => $this->text( Waypoints::META_ICON ),
				'color'      => $this->text( Waypoints::META_COLOR ),
				'background' => $this->text( Waypoints::META_BACKGROUND ),
				'photo'      => $this->photo(),
				'thumb'      => $this->thumb(),
			)
		);

		return $marker->to_array();
	}

	/**
	 * A stored text field of the post, or of another post such as its photo.
	 *
	 * @param string $key Meta key.
	 * @param int    $id  The other post's ID, or 0 for this one.
	 * @return string
	 */
	private function text( string $key, int $id = 0 ): string {
		$value = get_post_meta( $id > 0 ? $id : $this->post->ID, $key, true );
		return is_string( $value ) ? $value : '';
	}

	/**
	 * The post's photo, at the size a popup shows it.
	 *
	 * @return array{src: string, width: int, height: int, alt: string, srcset: string}|null
	 */
	private function photo(): ?array {
		$medium = $this->image > 0 ? wp_get_attachment_image_src( $this->image, 'medium' ) : false;
		if ( ! is_array( $medium ) ) {
			return null;
		}

		$srcset = wp_get_attachment_image_srcset( $this->image, 'medium' );

		return array(
			'src'    => esc_url_raw( $medium[0] ),
			'width'  => (int) $medium[1],
			'height' => (int) $medium[2],
			'alt'    => self::plain( $this->text( '_wp_attachment_image_alt', $this->image ) ),
			'srcset' => is_string( $srcset ) ? $srcset : '',
		);
	}

	/**
	 * The post's photo as a marker's thumbnail, or '' for none.
	 *
	 * @return string
	 */
	private function thumb(): string {
		$small = $this->image > 0 ? wp_get_attachment_image_src( $this->image, 'thumbnail' ) : false;
		return is_array( $small ) ? esc_url_raw( $small[0] ) : '';
	}

	/**
	 * Text to show as text: tags dropped, entities decoded once, as the
	 * browser does when WordPress prints it.
	 *
	 * @param string $text Text, such as a title.
	 * @return string
	 */
	private static function plain( string $text ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}
}
