/**
 * Route statistics helpers, exported separately so they are unit-testable.
 */

import { haversine } from './map-core';
import type { Coord } from './types';
import { computeElevationTotals } from './elevation';

export interface RouteStats {
	distance: number;
	gain: number;
	loss: number;
	maxEle: number;
}

/**
 * Aggregate route statistics from coordinates.
 *
 * @param coords    Coordinates.
 * @param segStarts Indices where a new segment begins.
 */
export function routeStats(
	coords: Coord[],
	segStarts: Set< number >
): RouteStats {
	const totals = computeElevationTotals( coords.map( ( c ) => c[ 2 ] ) );
	let distance = 0;
	let maxEle = coords[ 0 ]?.[ 2 ] ?? 0;
	for ( let i = 1; i < coords.length; i++ ) {
		if ( ! segStarts.has( i ) ) {
			distance += haversine(
				coords[ i - 1 ][ 1 ],
				coords[ i - 1 ][ 0 ],
				coords[ i ][ 1 ],
				coords[ i ][ 0 ]
			);
		}
		if ( coords[ i ][ 2 ] > maxEle ) {
			maxEle = coords[ i ][ 2 ];
		}
	}
	return { distance, gain: totals.gain, loss: totals.loss, maxEle };
}

/**
 * Index of the coordinate nearest a lng/lat (planar approximation).
 *
 * @param coords Coordinates.
 * @param lng    Longitude.
 * @param lat    Latitude.
 */
export function nearestIndex(
	coords: Coord[],
	lng: number,
	lat: number
): number {
	let minD = Infinity;
	let idx = 0;
	for ( let i = 0; i < coords.length; i++ ) {
		const dx = coords[ i ][ 0 ] - lng;
		const dy = coords[ i ][ 1 ] - lat;
		const d = dx * dx + dy * dy;
		if ( d < minD ) {
			minD = d;
			idx = i;
		}
	}
	return idx;
}
