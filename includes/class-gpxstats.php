<?php
/**
 * Route statistics calculated from a GPX track.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculate distance and elevation statistics from GPX track XML.
 */
class GpxStats {

	/**
	 * Minimum sustained elevation change (metres) before a climb or descent is
	 * counted. Filters out GPS noise so gain/loss totals stay realistic.
	 */
	private const ELEVATION_REVERSAL_THRESHOLD_METERS = 9.05;

	/**
	 * Calculate statistics from GPX XML.
	 * Accepts either a bare `<trk>` fragment or a full GPX document.
	 *
	 * @param string|false|null $gpx_xml GPX or track XML.
	 * @return array{distance: float, gain: float, loss: float, maxElevation: float, points: int}|null
	 */
	public static function from_xml( $gpx_xml ): ?array {
		if ( ! is_string( $gpx_xml ) || '' === trim( $gpx_xml ) ) {
			return null;
		}

		$segments = self::extract_segments( $gpx_xml );

		$count         = 0;
		$distance      = 0.0;
		$elevations    = array();
		$max_elevation = null;

		foreach ( $segments as $segment ) {
			$previous = null;

			foreach ( $segment as $point ) {
				++$count;

				if ( null !== $previous ) {
					$distance += self::haversine( $previous['lat'], $previous['lon'], $point['lat'], $point['lon'] );
				}
				$previous = $point;

				if ( null !== $point['ele'] ) {
					$elevations[]  = $point['ele'];
					$max_elevation = null === $max_elevation ? $point['ele'] : max( $max_elevation, $point['ele'] );
				}
			}
		}

		if ( $count < 2 ) {
			return null;
		}

		$elevation_totals = self::calculate_elevation_totals( $elevations );

		return array(
			'distance'     => $distance,
			'gain'         => $elevation_totals['gain'],
			'loss'         => $elevation_totals['loss'],
			'maxElevation' => null === $max_elevation ? 0.0 : $max_elevation,
			'points'       => $count,
		);
	}

	/**
	 * Extract point segments from GPX XML.
	 *
	 * @param string $gpx_xml GPX or track XML.
	 * @return array<int, array<int, array{lat: float, lon: float, ele: float|null}>>
	 */
	private static function extract_segments( string $gpx_xml ): array {
		$fragment = self::strip_prolog( $gpx_xml );

		$document = new \DOMDocument();
		libxml_use_internal_errors( true );

		$loaded = $document->loadXML( '<gpxrmroot>' . $fragment . '</gpxrmroot>' );

		if ( ! $loaded ) {
			libxml_clear_errors();
			return array();
		}

		$xpath = new \DOMXPath( $document );
		$nodes = $xpath->query( '//*[local-name()="trkpt"]' );

		if ( false === $nodes || 0 === $nodes->length ) {
			$nodes = $xpath->query( '//*[local-name()="rtept"]' );
		}

		if ( false === $nodes ) {
			libxml_clear_errors();
			return array();
		}

		$segments    = array();
		$current     = array();
		$current_key = null;

		foreach ( $nodes as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}

			$key = self::segment_ancestor( $node );

			if ( $key !== $current_key && array() !== $current ) {
				$segments[] = $current;
				$current    = array();
			}
			$current_key = $key;

			$lat = $node->getAttribute( 'lat' );
			$lon = $node->getAttribute( 'lon' );

			if ( ! is_numeric( $lat ) || ! is_numeric( $lon ) ) {
				continue;
			}

			$elevation_nodes = $xpath->query( './*[local-name()="ele"]', $node );
			$ele             = null;

			if ( false !== $elevation_nodes && $elevation_nodes->length > 0 ) {
				$ele_node = $elevation_nodes->item( 0 );
				$value    = $ele_node instanceof \DOMNode ? $ele_node->textContent : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode API property.

				if ( is_numeric( $value ) ) {
					$ele = (float) $value;
				}
			}

			$current[] = array(
				'lat' => (float) $lat,
				'lon' => (float) $lon,
				'ele' => $ele,
			);
		}

		if ( array() !== $current ) {
			$segments[] = $current;
		}

		libxml_clear_errors();

		return $segments;
	}

	/**
	 * Nearest ancestor that delimits a segment, or null when there is none.
	 *
	 * @param \DOMElement $node A trkpt/rtept element.
	 * @return \DOMNode|null
	 */
	private static function segment_ancestor( \DOMElement $node ): ?\DOMNode {
		$parent = $node->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode API property.

		while ( $parent instanceof \DOMElement ) {
			$name = $parent->localName; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode API property.

			if ( 'trkseg' === $name || 'trk' === $name || 'rte' === $name ) {
				return $parent;
			}

			$parent = $parent->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode API property.
		}

		return null;
	}

	/**
	 * Remove a UTF-8 BOM, XML prolog and DOCTYPE so the input can be wrapped
	 * in a root element.
	 *
	 * @param string $xml Raw XML.
	 * @return string
	 */
	private static function strip_prolog( string $xml ): string {
		$xml = trim( $xml );
		$xml = preg_replace( '/^\xEF\xBB\xBF/', '', $xml );
		$xml = preg_replace( '/^<\?xml[^>]*\?>\s*/i', '', (string) $xml );
		$xml = preg_replace( '/^<!DOCTYPE[^>]*>\s*/i', '', (string) $xml );

		return (string) $xml;
	}

	/**
	 * Calculate filtered ascent/descent totals using a reversal threshold.
	 *
	 * @param float[] $elevations Elevation values in metres.
	 * @return array{gain: float, loss: float}
	 */
	private static function calculate_elevation_totals( array $elevations ): array {
		if ( count( $elevations ) < 2 ) {
			return array(
				'gain' => 0.0,
				'loss' => 0.0,
			);
		}

		$gain           = 0.0;
		$loss           = 0.0;
		$base_elevation = $elevations[0];
		$high_elevation = $elevations[0];
		$low_elevation  = $elevations[0];
		$trend          = 0;

		$count = count( $elevations );
		for ( $index = 1; $index < $count; $index++ ) {
			$elevation = $elevations[ $index ];

			if ( 0 === $trend ) {
				$high_elevation = max( $high_elevation, $elevation );
				$low_elevation  = min( $low_elevation, $elevation );

				if ( $elevation - $low_elevation >= self::ELEVATION_REVERSAL_THRESHOLD_METERS ) {
					$trend          = 1;
					$base_elevation = $low_elevation;
					$high_elevation = $elevation;
				} elseif ( $high_elevation - $elevation >= self::ELEVATION_REVERSAL_THRESHOLD_METERS ) {
					$trend          = -1;
					$base_elevation = $high_elevation;
					$low_elevation  = $elevation;
				}

				continue;
			}

			if ( 1 === $trend ) {
				if ( $elevation > $high_elevation ) {
					$high_elevation = $elevation;
				}

				if ( $high_elevation - $elevation >= self::ELEVATION_REVERSAL_THRESHOLD_METERS ) {
					$gain          += $high_elevation - $base_elevation;
					$trend          = -1;
					$base_elevation = $high_elevation;
					$low_elevation  = $elevation;
				}

				continue;
			}

			if ( $elevation < $low_elevation ) {
				$low_elevation = $elevation;
			}

			if ( $elevation - $low_elevation >= self::ELEVATION_REVERSAL_THRESHOLD_METERS ) {
				$loss          += $base_elevation - $low_elevation;
				$trend          = 1;
				$base_elevation = $low_elevation;
				$high_elevation = $elevation;
			}
		}

		if ( 1 === $trend ) {
			$gain += $high_elevation - $base_elevation;
		}

		if ( -1 === $trend ) {
			$loss += $base_elevation - $low_elevation;
		}

		return array(
			'gain' => $gain,
			'loss' => $loss,
		);
	}

	/**
	 * Great-circle distance between two points, in kilometres.
	 *
	 * @param float $lat1 Latitude of point 1.
	 * @param float $lon1 Longitude of point 1.
	 * @param float $lat2 Latitude of point 2.
	 * @param float $lon2 Longitude of point 2.
	 * @return float
	 */
	private static function haversine( float $lat1, float $lon1, float $lat2, float $lon2 ): float {
		$earth_radius = 6371;
		$delta_lat    = deg2rad( $lat2 - $lat1 );
		$delta_lon    = deg2rad( $lon2 - $lon1 );
		$angle        = sin( $delta_lat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $delta_lon / 2 ) ** 2;

		return $earth_radius * 2 * atan2( sqrt( $angle ), sqrt( 1 - $angle ) );
	}
}
