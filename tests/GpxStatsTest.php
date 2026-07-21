<?php
/**
 * Unit tests for Gpxrm\GpxStats.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\GpxStats;

/**
 * Full GPX document with a known ascent/descent profile used as the test
 * fixture; the expected totals in the tests below are its reference values.
 */
function gpxrm_sample_gpx(): string {
	return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gpx xmlns="http://www.topografix.com/GPX/1/1"><trk><trkseg>
<trkpt lat="43.362611" lon="-5.843862"><ele>229.535</ele></trkpt>
<trkpt lat="43.362884" lon="-5.844012"><ele>227.786</ele></trkpt>
<trkpt lat="43.36567" lon="-5.867412"><ele>218.98</ele></trkpt>
<trkpt lat="43.365633" lon="-5.869158"><ele>212.782</ele></trkpt>
<trkpt lat="43.365632" lon="-5.869159"><ele>221</ele></trkpt>
<trkpt lat="43.372532" lon="-5.897344"><ele>256.25</ele></trkpt>
<trkpt lat="43.396753" lon="-5.980079"><ele>81</ele></trkpt>
<trkpt lat="43.390568" lon="-5.945241"><ele>219.25</ele></trkpt>
<trkpt lat="43.390643" lon="-5.947991"><ele>249.75</ele></trkpt>
<trkpt lat="43.396873" lon="-6.068203"><ele>66.41</ele></trkpt>
</trkseg></trk></gpx>
XML;
}

test(
	'calculates filtered elevation totals from a full gpx document',
	function () {
		$stats = GpxStats::from_xml( gpxrm_sample_gpx() );

		expect( $stats )->toBeArray();
		expect( (int) round( $stats['gain'] ) )->toBe( 212 );
		expect( (int) round( $stats['loss'] ) )->toBe( 375 );
		expect( (int) round( $stats['maxElevation'] ) )->toBe( 256 );
		expect( $stats['points'] )->toBe( 10 );
		expect( $stats['distance'] )->toBeGreaterThan( 0 );
	}
);

test(
	'accepts a bare trk fragment',
	function () {
		$fragment = '<trk><trkseg>'
		. '<trkpt lat="43.0" lon="-6.0"><ele>100</ele></trkpt>'
		. '<trkpt lat="43.1" lon="-6.1"><ele>130</ele></trkpt>'
		. '</trkseg></trk>';

		$stats = GpxStats::from_xml( $fragment );

		expect( $stats )->toBeArray();
		expect( $stats['points'] )->toBe( 2 );
		expect( $stats['maxElevation'] )->toBe( 130.0 );
	}
);

test(
	'counts trackpoints without elevation for distance but not elevation stats',
	function () {
		$fragment = '<trk><trkseg>'
		. '<trkpt lat="43.0" lon="-6.0"><ele>100</ele></trkpt>'
		. '<trkpt lat="43.001" lon="-6.0"></trkpt>'
		. '<trkpt lat="43.001" lon="-6.01"><ele>140</ele></trkpt>'
		. '</trkseg></trk>';

		$stats = GpxStats::from_xml( $fragment );

		expect( $stats['points'] )->toBe( 3 );
		expect( $stats['maxElevation'] )->toBe( 140.0 );
		// Route is L-shaped (~0.11 km + ~0.81 km). Skipping the middle
		// point would shortcut the corner to ~0.82 km.
		expect( $stats['distance'] )->toBeGreaterThan( 0.9 );
	}
);

test(
	'computes distance for a track with no elevation data at all',
	function () {
		$fragment = '<trk><trkseg>'
		. '<trkpt lat="43.0" lon="-6.0"></trkpt>'
		. '<trkpt lat="43.001" lon="-6.0"></trkpt>'
		. '</trkseg></trk>';

		$stats = GpxStats::from_xml( $fragment );

		expect( $stats )->toBeArray();
		expect( $stats['distance'] )->toBeGreaterThan( 0.1 );
		expect( $stats['gain'] )->toBe( 0.0 );
		expect( $stats['loss'] )->toBe( 0.0 );
		expect( $stats['maxElevation'] )->toBe( 0.0 );
		expect( $stats['points'] )->toBe( 2 );
	}
);

test(
	'does not count the gap between track segments as distance',
	function () {
		$split = '<trk><trkseg>'
		. '<trkpt lat="43.0" lon="-6.0"><ele>100</ele></trkpt>'
		. '<trkpt lat="43.001" lon="-6.0"><ele>101</ele></trkpt>'
		. '</trkseg><trkseg>'
		. '<trkpt lat="44.0" lon="-7.0"><ele>102</ele></trkpt>'
		. '<trkpt lat="44.001" lon="-7.0"><ele>103</ele></trkpt>'
		. '</trkseg></trk>';

		$joined = str_replace( '</trkseg><trkseg>', '', $split );

		// ~0.11 km per segment. The segment gap alone is ~130 km.
		expect( GpxStats::from_xml( $split )['distance'] )->toBeLessThan( 1.0 );
		expect( GpxStats::from_xml( $joined )['distance'] )->toBeGreaterThan( 100.0 );
	}
);

test(
	'falls back to route points when the file has no track',
	function () {
		$route = '<gpx xmlns="http://www.topografix.com/GPX/1/1"><rte>'
		. '<rtept lat="43.0" lon="-6.0"><ele>100</ele></rtept>'
		. '<rtept lat="43.001" lon="-6.0"><ele>130</ele></rtept>'
		. '</rte></gpx>';

		$stats = GpxStats::from_xml( $route );

		expect( $stats )->toBeArray();
		expect( $stats['points'] )->toBe( 2 );
		expect( $stats['maxElevation'] )->toBe( 130.0 );
		expect( $stats['distance'] )->toBeGreaterThan( 0.1 );
	}
);

test(
	'parses a document with a DOCTYPE declaration',
	function () {
		$gpx = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
		. '<!DOCTYPE gpx SYSTEM "http://www.topografix.com/GPX/1/1/gpx.xsd">' . "\n"
		. '<gpx xmlns="http://www.topografix.com/GPX/1/1"><trk><trkseg>'
		. '<trkpt lat="43.0" lon="-6.0"><ele>100</ele></trkpt>'
		. '<trkpt lat="43.001" lon="-6.0"><ele>130</ele></trkpt>'
		. '</trkseg></trk></gpx>';

		expect( GpxStats::from_xml( $gpx ) )->toBeArray();
	}
);

test(
	'returns null for unusable input',
	function ( $input ) {
		expect( GpxStats::from_xml( $input ) )->toBeNull();
	}
)->with(
	array(
		'empty string'       => '',
		'whitespace only'    => "   \n\t",
		'null'               => null,
		'false'              => false,
		'no track points'    => '<gpx></gpx>',
		'single track point' => '<trk><trkseg><trkpt lat="43.0" lon="-6.0"><ele>100</ele></trkpt></trkseg></trk>',
		'malformed xml'      => '<trk><trkseg><trkpt lat=',
	)
);
