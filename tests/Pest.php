<?php
/**
 * Pest configuration, and the helpers more than one test file uses.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Waypoints;

/**
 * Write a GPX file to a temp file and return its path.
 *
 * @param string $waypoints The file's <wpt> elements, and anything else.
 * @return string
 */
function gpxrm_waypoint_gpx( string $waypoints ): string {
	$path = (string) tempnam( sys_get_temp_dir(), 'gpxrm' );
	file_put_contents(
		$path,
		'<?xml version="1.0" encoding="UTF-8"?>' . "\n"
		. '<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1" xmlns:gpxrm="' . Waypoints::GPX_NAMESPACE . '">'
		. $waypoints
		. '<trk><trkseg><trkpt lat="43.0" lon="-6.0"/><trkpt lat="43.1" lon="-6.0"/></trkseg></trk>'
		. '</gpx>'
	);

	return $path;
}

/**
 * A map the box could offer.
 *
 * @param int $host The post.
 * @return array<string, mixed>
 */
function gpxrm_addable_map( int $host ): array {
	return array(
		'host'    => $host,
		'ordinal' => 0,
		'hash'    => str_repeat( 'd', 40 ),
		'number'  => 1,
		'of'      => 1,
		'file'    => 'https://example.test/uploads/route.gpx',
	);
}
