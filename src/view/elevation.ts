/**
 * Hand-rolled canvas elevation profile with drag/hover/touch scrubbing.
 */

import { haversine } from './map-core';
import type { Coord } from './types';

const THRESHOLD_M = 9.05;

const THEME = {
	line: '#4caf50',
	bg: '#1b3a1e',
	text: '#f5e6d3',
	grid: 'rgba(120, 120, 120, 0.25)',
	gridLabel: 'rgba(120, 120, 120, 0.75)',
	areaTop: 'rgba(76, 175, 80, 0.35)',
	areaBottom: 'rgba(76, 175, 80, 0.05)',
};

export interface ElevationTotals {
	gain: number;
	loss: number;
}

/**
 * Filtered elevation gain/loss using a reversal threshold: an up/down wiggle
 * smaller than THRESHOLD_M metres is ignored, so GPS noise doesn't inflate the
 * totals.
 *
 * @param elevations Elevation values.
 */
export function computeElevationTotals(
	elevations: number[]
): ElevationTotals {
	if ( elevations.length < 2 ) {
		return { gain: 0, loss: 0 };
	}

	let gain = 0;
	let loss = 0;
	let base = elevations[ 0 ];
	let high = elevations[ 0 ];
	let low = elevations[ 0 ];
	let trend = 0;

	for ( let i = 1; i < elevations.length; i++ ) {
		const e = elevations[ i ];

		if ( trend === 0 ) {
			high = Math.max( high, e );
			low = Math.min( low, e );
			if ( e - low >= THRESHOLD_M ) {
				trend = 1;
				base = low;
				high = e;
			} else if ( high - e >= THRESHOLD_M ) {
				trend = -1;
				base = high;
				low = e;
			}
			continue;
		}

		if ( trend === 1 ) {
			if ( e > high ) {
				high = e;
			}
			if ( high - e >= THRESHOLD_M ) {
				gain += high - base;
				trend = -1;
				base = high;
				low = e;
			}
			continue;
		}

		if ( e < low ) {
			low = e;
		}
		if ( e - low >= THRESHOLD_M ) {
			loss += base - low;
			trend = 1;
			base = low;
			high = e;
		}
	}

	if ( trend === 1 ) {
		gain += high - base;
	}
	if ( trend === -1 ) {
		loss += base - low;
	}

	return { gain, loss };
}

interface ProfileState {
	elevations: number[];
	dists: number[];
	totalDist: number;
	minEle: number;
	range: number;
	padL: number;
	padR: number;
	padT: number;
	padB: number;
	plotW: number;
	plotH: number;
	W: number;
	H: number;
}

export interface ProfileCallbacks {
	onScrub?: ( idx: number, dragging: boolean ) => void;
	onLeave?: () => void;
}

/**
 * Interactive elevation profile bound to a single <canvas>.
 */
export class ElevationProfile {
	canvas: HTMLCanvasElement;
	coords: Coord[];
	onScrub: ( idx: number, dragging: boolean ) => void;
	onLeave: () => void;
	segmentStarts: Set< number >;
	dragging: boolean;
	state: ProfileState | null;
	listenersBound: boolean;

	/**
	 * @param canvas        Target canvas.
	 * @param coords        [lon, lat, ele] triples.
	 * @param callbacks     Scrub hooks.
	 * @param segmentStarts Indices where a new segment begins.
	 */
	constructor(
		canvas: HTMLCanvasElement,
		coords: Coord[],
		callbacks: ProfileCallbacks = {},
		segmentStarts: Set< number > | null = null
	) {
		this.canvas = canvas;
		this.coords = coords;
		this.onScrub = callbacks.onScrub || ( () => {} );
		this.onLeave = callbacks.onLeave || ( () => {} );
		this.segmentStarts = segmentStarts || new Set();
		this.dragging = false;
		this.state = null;
		this.listenersBound = false;
	}

	/**
	 * Build geometry, size the canvas and attach interaction listeners.
	 */
	build(): void {
		const { canvas, coords } = this;
		const rect = canvas.getBoundingClientRect();
		if ( rect.width === 0 ) {
			return;
		}
		const dpr = window.devicePixelRatio || 1;
		canvas.width = rect.width * dpr;
		canvas.height = rect.height * dpr;

		const W = rect.width;
		const H = rect.height;
		const elevations = coords.map( ( c ) => c[ 2 ] );
		const minEle = Math.min( ...elevations ) - 20;
		const maxEle = Math.max( ...elevations ) + 20;
		const range = maxEle - minEle || 1;

		// Segment gaps contribute zero distance so the x-axis matches the
		// stats bar.
		const dists = [ 0 ];
		for ( let i = 1; i < coords.length; i++ ) {
			dists.push(
				dists[ i - 1 ] +
					( this.segmentStarts.has( i )
						? 0
						: haversine(
								coords[ i - 1 ][ 1 ],
								coords[ i - 1 ][ 0 ],
								coords[ i ][ 1 ],
								coords[ i ][ 0 ]
						  ) )
			);
		}
		const totalDist = dists[ dists.length - 1 ] || 1;

		const padL = 42;
		const padR = 12;
		const padT = 8;
		const padB = 22;

		this.state = {
			elevations,
			dists,
			totalDist,
			minEle,
			range,
			padL,
			padR,
			padT,
			padB,
			plotW: W - padL - padR,
			plotH: H - padT - padB,
			W,
			H,
		};

		this.redraw();
		this.attachListeners();
	}

	/**
	 * Nearest sample index to a distance along the track.
	 *
	 * @param targetDist Distance in km.
	 */
	indexAtDistance( targetDist: number ): number {
		if ( ! this.state ) {
			return 0;
		}
		const { dists } = this.state;
		let idx = 0;
		for ( let i = 1; i < dists.length; i++ ) {
			if (
				Math.abs( dists[ i ] - targetDist ) <
				Math.abs( dists[ idx ] - targetDist )
			) {
				idx = i;
			}
		}
		return idx;
	}

	/**
	 * Map a client X position to a sample index, or null if outside the plot.
	 *
	 * @param clientX Pointer clientX.
	 */
	indexAtClientX( clientX: number ): number | null {
		if ( ! this.state ) {
			return null;
		}
		const { padL, padR, plotW, totalDist, W } = this.state;
		const r = this.canvas.getBoundingClientRect();
		const mx = clientX - r.left;
		if ( mx < padL || mx > W - padR ) {
			return null;
		}
		return this.indexAtDistance( ( ( mx - padL ) / plotW ) * totalDist );
	}

	/**
	 * Attach mouse and touch scrubbing listeners. build() calls this on every
	 * rebuild (window resize), so it must only ever attach one set.
	 */
	attachListeners(): void {
		if ( this.listenersBound ) {
			return;
		}
		this.listenersBound = true;

		const canvas = this.canvas;

		canvas.addEventListener( 'mousedown', ( e ) => {
			this.dragging = true;
			canvas.style.cursor = 'grabbing';
			const idx = this.indexAtClientX( e.clientX );
			if ( idx !== null ) {
				this.highlight( idx );
				this.onScrub( idx, false );
			}
		} );

		canvas.addEventListener( 'mousemove', ( e ) => {
			const idx = this.indexAtClientX( e.clientX );
			if ( idx === null ) {
				this.clear();
				return;
			}
			this.highlight( idx );
			this.onScrub( idx, this.dragging );
		} );

		window.addEventListener( 'mouseup', () => {
			this.dragging = false;
			canvas.style.cursor = '';
		} );

		canvas.addEventListener( 'mouseleave', () => {
			this.dragging = false;
			canvas.style.cursor = '';
			this.clear();
		} );

		const touchIndex = ( touch: Touch ): number | null =>
			this.indexAtClientX( touch.clientX );

		canvas.addEventListener(
			'touchstart',
			( e ) => {
				e.preventDefault();
				this.dragging = true;
				const idx = touchIndex( e.touches[ 0 ] );
				if ( idx !== null ) {
					this.highlight( idx );
					this.onScrub( idx, false );
				}
			},
			{ passive: false }
		);

		canvas.addEventListener(
			'touchmove',
			( e ) => {
				e.preventDefault();
				const idx = touchIndex( e.touches[ 0 ] );
				if ( idx === null ) {
					return;
				}
				this.highlight( idx );
				this.onScrub( idx, this.dragging );
			},
			{ passive: false }
		);

		canvas.addEventListener( 'touchend', () => {
			this.dragging = false;
			this.clear();
		} );
	}

	/**
	 * Redraw the base profile (grid, area, line, axis labels).
	 */
	redraw(): void {
		if ( ! this.state ) {
			return;
		}
		const ctx = this.canvas.getContext( '2d' );
		if ( ! ctx ) {
			return;
		}
		const { coords } = this;
		const {
			elevations,
			dists,
			totalDist,
			minEle,
			range,
			padL,
			padR,
			padT,
			plotW,
			plotH,
			W,
			H,
		} = this.state;
		const dpr = window.devicePixelRatio || 1;
		ctx.setTransform( dpr, 0, 0, dpr, 0, 0 );
		ctx.clearRect( 0, 0, W, H );

		const x = ( d: number ): number => padL + ( d / totalDist ) * plotW;
		const y = ( e: number ): number =>
			padT + plotH - ( ( e - minEle ) / range ) * plotH;

		ctx.strokeStyle = THEME.grid;
		ctx.lineWidth = 0.5;
		for ( let i = 0; i <= 5; i++ ) {
			const ev = minEle + ( range * i ) / 5;
			const yy = y( ev );
			ctx.beginPath();
			ctx.moveTo( padL, yy );
			ctx.lineTo( W - padR, yy );
			ctx.stroke();
			ctx.fillStyle = THEME.gridLabel;
			ctx.font = '10px system-ui, sans-serif';
			ctx.textAlign = 'right';
			ctx.fillText( `${ Math.round( ev ) }`, padL - 4, yy + 3 );
		}
		ctx.textAlign = 'center';
		for ( let i = 0; i <= 5; i++ ) {
			const dv = ( totalDist * i ) / 5;
			ctx.fillStyle = THEME.gridLabel;
			ctx.fillText( `${ dv.toFixed( 1 ) }`, x( dv ), H - 4 );
		}

		ctx.beginPath();
		ctx.moveTo( x( dists[ 0 ] ), y( elevations[ 0 ] ) );
		for ( let i = 1; i < coords.length; i++ ) {
			ctx.lineTo( x( dists[ i ] ), y( elevations[ i ] ) );
		}
		ctx.lineTo( x( dists[ dists.length - 1 ] ), padT + plotH );
		ctx.lineTo( x( dists[ 0 ] ), padT + plotH );
		ctx.closePath();
		const grad = ctx.createLinearGradient( 0, padT, 0, padT + plotH );
		grad.addColorStop( 0, THEME.areaTop );
		grad.addColorStop( 1, THEME.areaBottom );
		ctx.fillStyle = grad;
		ctx.fill();

		ctx.beginPath();
		ctx.moveTo( x( dists[ 0 ] ), y( elevations[ 0 ] ) );
		for ( let i = 1; i < coords.length; i++ ) {
			ctx.lineTo( x( dists[ i ] ), y( elevations[ i ] ) );
		}
		ctx.strokeStyle = THEME.line;
		ctx.lineWidth = 2;
		ctx.stroke();

		ctx.fillStyle = THEME.gridLabel;
		ctx.font = '9px system-ui, sans-serif';
		ctx.textAlign = 'left';
		ctx.fillText( 'm', padL - 4, padT - 1 );
		ctx.textAlign = 'center';
		ctx.fillText( 'km', W / 2, H );
	}

	/**
	 * Draw the crosshair, marker dot and tooltip for a sample index.
	 *
	 * @param idx Sample index.
	 */
	highlight( idx: number ): void {
		if ( ! this.state ) {
			return;
		}
		this.redraw();
		const ctx = this.canvas.getContext( '2d' );
		if ( ! ctx ) {
			return;
		}
		const {
			elevations,
			dists,
			totalDist,
			minEle,
			range,
			padL,
			padR,
			padT,
			plotW,
			plotH,
			W,
		} = this.state;

		const xPos = padL + ( dists[ idx ] / totalDist ) * plotW;
		const yPos =
			padT + plotH - ( ( elevations[ idx ] - minEle ) / range ) * plotH;

		ctx.save();
		ctx.strokeStyle = THEME.text;
		ctx.lineWidth = 1;
		ctx.setLineDash( [ 4, 3 ] );
		ctx.beginPath();
		ctx.moveTo( xPos, padT );
		ctx.lineTo( xPos, padT + plotH );
		ctx.stroke();
		ctx.setLineDash( [] );

		ctx.beginPath();
		ctx.arc( xPos, yPos, 5, 0, Math.PI * 2 );
		ctx.fillStyle = '#ffffff';
		ctx.fill();
		ctx.strokeStyle = THEME.line;
		ctx.lineWidth = 2.5;
		ctx.stroke();

		const label = `${ Math.round( elevations[ idx ] ) } m  ·  ${ dists[
			idx
		].toFixed( 2 ) } km`;
		ctx.font = 'bold 11px system-ui, sans-serif';
		const tw = ctx.measureText( label ).width;
		const tx = Math.min(
			Math.max( xPos - tw / 2 - 6, padL ),
			W - padR - tw - 14
		);
		const ty = yPos - 22 - 12 < padT ? yPos + 16 : yPos - 22;
		ctx.fillStyle = THEME.bg;
		ctx.globalAlpha = 0.85;
		ctx.beginPath();
		ctx.roundRect( tx, ty - 12, tw + 12, 20, 4 );
		ctx.fill();
		ctx.globalAlpha = 1;
		ctx.fillStyle = THEME.text;
		ctx.textAlign = 'left';
		ctx.fillText( label, tx + 6, ty + 2 );
		ctx.restore();
	}

	/**
	 * Clear any highlight and notify the map to hide its position dot.
	 */
	clear(): void {
		this.redraw();
		this.onLeave();
	}

	/**
	 * Sample coordinate for an index.
	 *
	 * @param idx Index.
	 */
	coordAt( idx: number ): Coord {
		return this.coords[ idx ];
	}
}
