/**
 * parseGPX: track/route extraction, segment boundaries, error flagging.
 */

import { describe, expect, it } from 'vitest';

import { parseGPX } from '../../src/view/map-core';

const PROLOG = '<?xml version="1.0" encoding="UTF-8"?>\n';
const NS = 'xmlns="http://www.topografix.com/GPX/1/1"';

const SPLIT_TRACK =
	PROLOG +
	`<gpx ${ NS }><trk><trkseg>` +
	'<trkpt lat="43.0" lon="-6.0"><ele>100</ele></trkpt>' +
	'<trkpt lat="43.001" lon="-6.0"><ele>101</ele></trkpt>' +
	'</trkseg><trkseg>' +
	'<trkpt lat="44.0" lon="-7.0"><ele>102</ele></trkpt>' +
	'<trkpt lat="44.001" lon="-7.0"><ele>103</ele></trkpt>' +
	'</trkseg></trk></gpx>';

describe( 'parseGPX', () => {
	it( 'parses coordinates as [lon, lat, ele] triples', () => {
		const { coords, invalid } = parseGPX( SPLIT_TRACK );
		expect( invalid ).toBe( false );
		expect( coords ).toHaveLength( 4 );
		expect( coords[ 0 ] ).toEqual( [ -6.0, 43.0, 100 ] );
	} );

	it( 'records a segment start per trkseg', () => {
		expect( parseGPX( SPLIT_TRACK ).segmentStarts ).toEqual( [ 0, 2 ] );
	} );

	it( 'treats a track without trkseg wrappers as one segment', () => {
		const { coords, segmentStarts } = parseGPX(
			'<trk>' +
				'<trkpt lat="43.0" lon="-6.0"><ele>1</ele></trkpt>' +
				'<trkpt lat="43.001" lon="-6.0"><ele>2</ele></trkpt>' +
				'</trk>'
		);
		expect( coords ).toHaveLength( 2 );
		expect( segmentStarts ).toEqual( [ 0 ] );
	} );

	it( 'falls back to rtept for GPX route files, one segment per rte', () => {
		const { coords, segmentStarts } = parseGPX(
			PROLOG +
				`<gpx ${ NS }><rte>` +
				'<rtept lat="43.0" lon="-6.0"><ele>100</ele></rtept>' +
				'<rtept lat="43.001" lon="-6.0"><ele>101</ele></rtept>' +
				'</rte><rte>' +
				'<rtept lat="44.0" lon="-7.0"><ele>102</ele></rtept>' +
				'<rtept lat="44.001" lon="-7.0"><ele>103</ele></rtept>' +
				'</rte></gpx>'
		);
		expect( coords ).toHaveLength( 4 );
		expect( segmentStarts ).toEqual( [ 0, 2 ] );
	} );

	it( 'flags malformed XML as invalid instead of "no points"', () => {
		const result = parseGPX( '<trk><trkseg><trkpt lat=' );
		expect( result.invalid ).toBe( true );
		expect( result.coords ).toEqual( [] );
	} );

	it( 'skips points with non-numeric coordinates', () => {
		const { coords } = parseGPX(
			'<trk><trkseg>' +
				'<trkpt lat="oops" lon="-6.0"><ele>1</ele></trkpt>' +
				'<trkpt lat="43.0" lon="-6.0"><ele>2</ele></trkpt>' +
				'</trkseg></trk>'
		);
		expect( coords ).toHaveLength( 1 );
	} );

	// MapLibre throws on a latitude past a pole and cannot fit the map to an
	// infinite coordinate, so one such point would stop the whole map.
	it.each( [
		[ 'a latitude past a pole', '139.69', '35.68' ],
		[ 'an infinite latitude', 'Infinity', '-6.0' ],
		[ 'an infinite longitude', '43.0', '-Infinity' ],
		[ 'an absurd longitude', '43.0', '1e300' ],
	] )( 'skips points and waypoints with %s', ( _label, lat, lon ) => {
		const { coords, waypoints } = parseGPX(
			'<gpx>' +
				`<wpt lat="${ lat }" lon="${ lon }"><name>Bad</name></wpt>` +
				'<wpt lat="43.3" lon="-5.8"><name>Good</name></wpt>' +
				'<trk><trkseg>' +
				`<trkpt lat="${ lat }" lon="${ lon }"><ele>1</ele></trkpt>` +
				'<trkpt lat="43.0" lon="-6.0"><ele>2</ele></trkpt>' +
				'</trkseg></trk></gpx>'
		);
		expect( coords ).toEqual( [ [ -6.0, 43.0, 2 ] ] );
		expect( waypoints.map( ( w ) => w.name ) ).toEqual( [ 'Good' ] );
	} );

	// Some tools write them for a track crossing the antimeridian.
	it( 'keeps longitudes past ±180', () => {
		const { coords } = parseGPX(
			'<trk><trkseg>' +
				'<trkpt lat="-17" lon="179.9"></trkpt>' +
				'<trkpt lat="-17" lon="181.2"></trkpt>' +
				'<trkpt lat="-17" lon="-359.5"></trkpt>' +
				'</trkseg></trk>'
		);
		expect( coords ).toHaveLength( 3 );
	} );

	it( 'keeps the poles themselves', () => {
		const { coords } = parseGPX(
			'<trk><trkseg>' +
				'<trkpt lat="90" lon="0"></trkpt>' +
				'<trkpt lat="-90" lon="0"></trkpt>' +
				'</trkseg></trk>'
		);
		expect( coords ).toHaveLength( 2 );
	} );

	it( 'defaults missing elevation to 0', () => {
		const { coords } = parseGPX(
			'<trk><trkseg>' +
				'<trkpt lat="43.0" lon="-6.0"></trkpt>' +
				'</trkseg></trk>'
		);
		expect( coords[ 0 ][ 2 ] ).toBe( 0 );
	} );

	it( 'extracts waypoints with name, desc, type and link', () => {
		const { waypoints } = parseGPX(
			PROLOG +
				`<gpx ${ NS }>` +
				'<wpt lat="43.3" lon="-5.8"><name>Fountain</name>' +
				'<desc>Cold water</desc><type>fountain</type>' +
				'<link href="https://example.com/x"/></wpt>' +
				'<trk><trkseg>' +
				'<trkpt lat="43.0" lon="-6.0"><ele>1</ele></trkpt>' +
				'</trkseg></trk></gpx>'
		);
		expect( waypoints ).toEqual( [
			{
				lon: -5.8,
				lat: 43.3,
				name: 'Fountain',
				desc: 'Cold water',
				type: 'fountain',
				link: 'https://example.com/x',
			},
		] );
	} );

	// The name becomes the marker's accessible name, so whitespace alone must
	// not count as one: it would announce as nothing at all.
	it.each( [
		[ 'missing', '', 'Waypoint' ],
		[ 'whitespace only', '<name>  \n\t </name>', 'Waypoint' ],
		[
			'padded, as pretty-printed GPX has it',
			'<name>\n  Fountain\n</name>',
			'Fountain',
		],
	] )( 'names a waypoint whose name is %s', ( _label, nameXml, want ) => {
		const { waypoints } = parseGPX(
			PROLOG +
				`<gpx ${ NS }>` +
				`<wpt lat="43.3" lon="-5.8">${ nameXml }</wpt>` +
				'<trk><trkseg>' +
				'<trkpt lat="43.0" lon="-6.0"><ele>1</ele></trkpt>' +
				'</trkseg></trk></gpx>'
		);
		expect( waypoints[ 0 ].name ).toBe( want );
	} );
} );
