<?php
/**
 * What a map is told about a waypoint linked to a Waypoint post.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a map is told about a waypoint from its Waypoint post's settings:
 * only what differs from the waypoint in the GPX file.
 *
 * A photo marker needs a photo, else the waypoint keeps its usual one. An
 * icon marker always gets an icon, and its colours when chosen; without them
 * the map draws its own.
 *
 * @phpstan-import-type Photo from Waypoints
 * @phpstan-import-type Marker from Waypoints
 * @phpstan-type Settings array{name: string, desc: string, marker: mixed, icon: mixed, color: mixed, background: mixed, photo: array{src: string, width: int, height: int, alt: string, srcset: string}|null, thumb: string}
 */
final class Waypoint_Marker {

	/**
	 * The post's settings.
	 *
	 * @var array<string, mixed>
	 * @phpstan-var Settings
	 */
	private $settings;

	/**
	 * A waypoint's marker, from its post's settings.
	 *
	 * @param array<string, mixed> $settings Post settings.
	 * @phpstan-param Settings $settings
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * What the map is told.
	 *
	 * @return array<string, mixed>
	 * @phpstan-return Marker
	 */
	public function to_array(): array {
		$entry = array();

		if ( '' !== $this->settings['name'] ) {
			$entry['name'] = $this->settings['name'];
		}

		if ( '' !== $this->settings['desc'] ) {
			$entry['desc'] = $this->settings['desc'];
		}

		$photo = $this->photo();
		if ( null !== $photo ) {
			$entry['photo'] = $photo;
		}

		return $entry + $this->marker();
	}

	/**
	 * What the map is told about the post's photo, or null for none.
	 *
	 * @return array<string, mixed>|null
	 * @phpstan-return Photo|null
	 */
	private function photo(): ?array {
		$photo = $this->settings['photo'];
		if ( null === $photo || '' === $photo['src'] ) {
			return null;
		}

		$entry = array(
			'src'    => $photo['src'],
			'width'  => max( 0, $photo['width'] ),
			'height' => max( 0, $photo['height'] ),
			'alt'    => $photo['alt'],
		);
		if ( '' !== $photo['srcset'] ) {
			$entry['srcset'] = $photo['srcset'];
		}

		return $entry;
	}

	/**
	 * What the map is told about the post's marker: a photo marker with its
	 * thumbnail, an icon marker with its icon and any colours chosen, or
	 * nothing for the usual marker.
	 *
	 * @return array<string, string>
	 * @phpstan-return array{marker?: string, thumb?: string, icon?: string, color?: string, background?: string}
	 */
	private function marker(): array {
		$marker = Waypoint_Values::sanitize_marker( $this->settings['marker'] );
		if ( 'image' === $marker && '' !== $this->settings['thumb'] ) {
			return array(
				'marker' => 'image',
				'thumb'  => $this->settings['thumb'],
			);
		}

		if ( 'icon' !== $marker ) {
			return array();
		}

		$icon  = $this->settings['icon'];
		$entry = array(
			'marker' => 'icon',
			'icon'   => is_string( $icon ) && Dashicons::exists( $icon ) ? $icon : Waypoints::DEFAULT_ICON,
		);

		$color = Waypoint_Values::hex_color( $this->settings['color'], '' );
		if ( '' !== $color ) {
			$entry['color'] = $color;
		}
		$background = Waypoint_Values::hex_color( $this->settings['background'], '' );
		if ( '' !== $background ) {
			$entry['background'] = $background;
		}

		return $entry;
	}
}
