/**
 * Which added waypoints the map opens on. A latitude and longitude swapped
 * still pass as coordinates, and with no map picker nothing else catches them:
 * opening on one would shrink a 4 km track to a dot.
 */

import { describe, expect, it } from 'vitest';

import {
	kmFromBox,
	MIN_REACH_KM,
	openingBounds,
	reachKm,
	unwrapLon,
} from '../../src/view/map-core';
import type { AddedWaypoint, Bounds, Coord } from '../../src/view/types';

/** A track of about 3.6 km. */
const TRACK: Coord[] = [
	[ 5.93, 50.75, 0 ],
	[ 5.95, 50.72, 0 ],
];
const TRACK_BOX: Bounds = [
	[ 5.93, 50.72 ],
	[ 5.95, 50.75 ],
];

/**
 * An added waypoint at a place.
 *
 * @param lat Latitude.
 * @param lon Longitude.
 */
const at = ( lat: number, lon: number ): AddedWaypoint => ( {
	lat,
	lon,
	at: [],
	name: 'Summit',
} );

describe( 'reachKm', () => {
	it( 'lets a short route reach as far as the least distance', () => {
		expect( reachKm( TRACK_BOX ) ).toBe( MIN_REACH_KM );
		expect( MIN_REACH_KM ).toBe( 20 );
	} );

	it( 'lets a long route reach as far as it is across', () => {
		const box: Bounds = [
			[ 5, 50 ],
			[ 6, 52 ],
		];
		expect( reachKm( box ) ).toBeCloseTo( 233.1, 0 );
	} );
} );

describe( 'kmFromBox', () => {
	it( 'is nothing inside the box', () => {
		expect( kmFromBox( TRACK_BOX, 50.73, 5.94 ) ).toBe( 0 );
	} );

	it( 'measures to the nearest edge or corner', () => {
		// A degree of latitude is about 111 km.
		expect( kmFromBox( TRACK_BOX, 51.75, 5.94 ) ).toBeCloseTo( 111.2, 0 );
		expect( kmFromBox( TRACK_BOX, 51.75, 6.95 ) ).toBeCloseTo( 131.2, 0 );
	} );

	it( 'takes the short way across the antimeridian', () => {
		const box: Bounds = [
			[ 179.8, -17 ],
			[ 180.15, -16.9 ],
		];
		expect( kmFromBox( box, -17, -179.7 ) ).toBeCloseTo( 16, 0 );
	} );
} );

describe( 'unwrapLon', () => {
	it( 'leaves a longitude on the same side of the world as it is', () => {
		expect( unwrapLon( TRACK_BOX, 7 ) ).toBe( 7 );
	} );

	it( 'brings one from across the antimeridian next to the box', () => {
		const box: Bounds = [
			[ 179.8, -17 ],
			[ 180.15, -16.9 ],
		];
		expect( unwrapLon( box, -179.7 ) ).toBeCloseTo( 180.3, 6 );
	} );
} );

describe( 'openingBounds', () => {
	it( 'is the track alone without added waypoints', () => {
		expect( openingBounds( TRACK, [] ) ).toEqual( TRACK_BOX );
	} );

	it( 'takes in an added waypoint near the route', () => {
		expect( openingBounds( TRACK, [ at( 50.8, 5.9 ) ] ) ).toEqual( [
			[ 5.9, 50.72 ],
			[ 5.95, 50.8 ],
		] );
	} );

	it( 'leaves out one far from the route, which is still drawn', () => {
		expect( openingBounds( TRACK, [ at( 52, 5.94 ) ] ) ).toEqual(
			TRACK_BOX
		);
	} );

	it( 'leaves out a latitude and longitude swapped', () => {
		expect( openingBounds( TRACK, [ at( 5.93, 50.75 ) ] ) ).toEqual(
			TRACK_BOX
		);
	} );

	it( 'works from a track of a single point', () => {
		expect(
			openingBounds( [ [ 5.93, 50.75, 0 ] ], [ at( 50.8, 5.93 ) ] )
		).toEqual( [
			[ 5.93, 50.75 ],
			[ 5.93, 50.8 ],
		] );
	} );

	it( 'stays near a track that crosses the antimeridian', () => {
		const bounds = openingBounds(
			[
				[ 179.8, -17, 0 ],
				[ 180.15, -16.9, 0 ],
			],
			[ at( -17, -179.7 ) ]
		);
		expect( bounds[ 0 ] ).toEqual( [ 179.8, -17 ] );
		expect( bounds[ 1 ][ 0 ] ).toBeCloseTo( 180.3, 6 );
	} );
} );
