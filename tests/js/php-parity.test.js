/**
 * Integration Test: the JS distance must equal the PHP GpxStats
 * distance for identical GPX input.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';

import { describe, expect, it } from 'vitest';

import { parseGPX } from '../../src/view/map-core';
import { ElevationProfile } from '../../src/view/elevation';

const hasPhp = ( () => {
	try {
		execFileSync( 'php', [ '-v' ], { stdio: 'ignore' } );
		return true;
	} catch {
		return false;
	}
} )();

const GPXSTATS = path.resolve( __dirname, '../../includes/class-gpxstats.php' );

/**
 * Run the real PHP GpxStats on a GPX string and return its distance.
 *
 * @param {string} xml GPX document.
 * @return {number} Distance in km.
 */
function phpDistance( xml ) {
	const out = execFileSync(
		'php',
		[
			'-r',
			'define("ABSPATH", sys_get_temp_dir()); require $argv[1]; ' +
				'printf("%.12F", \\Gpxrm\\GpxStats::from_xml(stream_get_contents(STDIN))["distance"]);',
			GPXSTATS,
		],
		{ input: xml }
	).toString();
	return parseFloat( out );
}

const ctxStub = new Proxy(
	{},
	{
		get: ( target, prop ) => {
			if ( prop === 'createLinearGradient' ) {
				return () => ( { addColorStop: () => {} } );
			}
			if ( prop === 'measureText' ) {
				return () => ( { width: 50 } );
			}
			return () => {};
		},
		set: () => true,
	}
);
const canvasStub = {
	style: {},
	getBoundingClientRect: () => ( { width: 400, height: 160, left: 0 } ),
	getContext: () => ctxStub,
	addEventListener: () => {},
};

/**
 * Segment-aware JS distance through the real production code path.
 *
 * @param {string} xml GPX document.
 * @return {number} Distance in km.
 */
function jsDistance( xml ) {
	const { coords, segmentStarts } = parseGPX( xml );
	const profile = new ElevationProfile(
		canvasStub,
		coords,
		{},
		new Set( segmentStarts )
	);
	profile.build();
	return profile.state.dists.at( -1 );
}

const PROLOG = '<?xml version="1.0" encoding="UTF-8"?>\n';
const NS = 'xmlns="http://www.topografix.com/GPX/1/1"';

describe.skipIf( ! hasPhp )( 'JS↔PHP distance parity', () => {
	it.each( [
		[
			'split segments',
			PROLOG +
				`<gpx ${ NS }><trk><trkseg>` +
				'<trkpt lat="43.0" lon="-6.0"><ele>100</ele></trkpt>' +
				'<trkpt lat="43.001" lon="-6.0"><ele>101</ele></trkpt>' +
				'</trkseg><trkseg>' +
				'<trkpt lat="44.0" lon="-7.0"><ele>102</ele></trkpt>' +
				'<trkpt lat="44.001" lon="-7.0"><ele>103</ele></trkpt>' +
				'</trkseg></trk></gpx>',
		],
		[
			'single segment',
			PROLOG +
				`<gpx ${ NS }><trk><trkseg>` +
				'<trkpt lat="43.0" lon="-6.0"><ele>100</ele></trkpt>' +
				'<trkpt lat="43.001" lon="-6.0"><ele>101</ele></trkpt>' +
				'<trkpt lat="44.0" lon="-7.0"><ele>102</ele></trkpt>' +
				'<trkpt lat="44.001" lon="-7.0"><ele>103</ele></trkpt>' +
				'</trkseg></trk></gpx>',
		],
		[
			'route file',
			PROLOG +
				`<gpx ${ NS }><rte>` +
				'<rtept lat="43.0" lon="-6.0"><ele>100</ele></rtept>' +
				'<rtept lat="43.001" lon="-6.0"><ele>101</ele></rtept>' +
				'</rte><rte>' +
				'<rtept lat="44.0" lon="-7.0"><ele>102</ele></rtept>' +
				'<rtept lat="44.001" lon="-7.0"><ele>103</ele></rtept>' +
				'</rte></gpx>',
		],
	] )( 'agrees on %s', ( label, xml ) => {
		expect(
			Math.abs( jsDistance( xml ) - phpDistance( xml ) )
		).toBeLessThan( 1e-9 );
	} );
} );
