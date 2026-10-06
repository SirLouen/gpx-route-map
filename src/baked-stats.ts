/**
 * The stats the editor stores on a block, so the server can print the stats
 * bar before the map has loaded.
 */

import { routeStats } from './view/stats';
import type { ParsedGpx } from './view/types';

/** Summary stats computed in the browser and stored on the block. */
export type GpxStats = {
	distance: number;
	gain: number;
	loss: number;
	max: number;
	/** Missing from stats stored before 2.0.0. */
	min?: number;
	waypoints: number;
};

/**
 * The stats to store for a parsed GPX file.
 *
 * @param parsed The parsed file.
 */
export function bakeStats( parsed: ParsedGpx ): GpxStats {
	const s = routeStats(
		parsed.coords,
		new Set( parsed.segmentStarts ),
		parsed.hasEle
	);
	return {
		distance: s.distance,
		gain: s.gain,
		loss: s.loss,
		max: s.maxEle,
		min: s.minEle,
		waypoints: parsed.waypoints.length,
	};
}

/**
 * Whether the stored stats already match fresh ones, so the attribute is only
 * written when the numbers actually change: reopening a saved block must not
 * mark the post as changed.
 *
 * Stats stored before 2.0.0 have no min. That alone is no change, so they
 * gain one only when something else makes the editor store them again.
 *
 * @param a Stored stats.
 * @param b Freshly computed stats.
 */
export function sameStats( a: GpxStats | undefined, b: GpxStats ): boolean {
	return (
		!! a &&
		a.distance === b.distance &&
		a.gain === b.gain &&
		a.loss === b.loss &&
		a.max === b.max &&
		( undefined === a.min || a.min === b.min ) &&
		a.waypoints === b.waypoints
	);
}

/** Stats worked out from a file, with the address they were read from. */
export type FreshStats = {
	url: string;
	stats: GpxStats;
};

/**
 * Whether stats just worked out from the file should be stored.
 *
 * Only when they changed: opening a post must not mark it as changed. Stats
 * stored before 2.0.0 have no min, which alone is no change - unless the
 * author chose to show the min before the file had been read.
 *
 * @param stored  Stored stats.
 * @param next    Stats just worked out from the file.
 * @param wantMin Whether the author has just chosen to show the min.
 */
export function shouldStore(
	stored: GpxStats | undefined,
	next: GpxStats,
	wantMin: boolean
): boolean {
	return (
		! sameStats( stored, next ) || ( wantMin && undefined === stored?.min )
	);
}

/**
 * The stats to store along with a new choice of figures, if any.
 *
 * Opening a post never stores its stats again, so stats stored before 2.0.0
 * keep lacking a min. Choosing the min is the author's own edit, though: the
 * stats just worked out from the file then go with it, min included - but
 * only from the file the block shows now, not one it showed before.
 *
 * @param stored Stored stats.
 * @param fresh  Stats last worked out from a file, if any.
 * @param url    The address of the file the block shows now.
 * @param fields The figures now shown.
 */
export function statsToStore(
	stored: GpxStats | undefined,
	fresh: FreshStats | undefined,
	url: string,
	fields: string[]
): GpxStats | undefined {
	return fresh &&
		fresh.url === url &&
		fields.includes( 'min' ) &&
		undefined === stored?.min
		? fresh.stats
		: undefined;
}
