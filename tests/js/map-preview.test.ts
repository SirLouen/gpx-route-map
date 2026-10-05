/**
 * The editor preview component.
 *
 * Rendered for real, into an iframe like the editor canvas, with only the
 * server render, the module loader and the editor stores stood in for. A
 * request for a live map is what the component hands the view module; these
 * tests count them instead of building maps.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createElement, createRoot, flushSync } from '@wordpress/element';

import type { RenderedMarkup } from '../../src/editor/use-rendered-markup';

let previewMode = false;
let postId: number | undefined;
/** The server's answer for each block's settings, by their JSON. */
let answers: Map< string, string | null >;
/** Each block's last answered render, kept as the real hook keeps it. */
const lastRendered = new Map< unknown, RenderedMarkup >();
const renderedFor: { postId?: number }[] = [];

vi.mock( '@wordpress/data', () => ( {
	useSelect: ( select: ( pick: ( store: string ) => unknown ) => unknown ) =>
		select( ( store ) =>
			'core/editor' === store
				? { getCurrentPostId: () => postId }
				: { getSettings: () => ( { isPreviewMode: previewMode } ) }
		),
} ) );

vi.mock( '../../src/editor/use-rendered-markup', () => ( {
	useRenderedMarkup: (
		attributes: Record< string, unknown >,
		enabled: boolean,
		id?: number
	): { rendered: RenderedMarkup | null; json: string } => {
		const json = JSON.stringify( attributes );
		if ( enabled ) {
			renderedFor.push( { postId: id } );
			if ( answers.has( json ) ) {
				lastRendered.set( attributes.key, {
					json,
					html: answers.get( json ) ?? null,
				} );
			}
		}
		// Until the settings' own render is answered, the last one stays.
		return { rendered: lastRendered.get( attributes.key ) ?? null, json };
	},
} ) );

/** Whether loading the view module fails. */
let loadFails = false;

vi.mock( '../../src/editor/load-view', () => ( {
	loadViewModule: () =>
		loadFails
			? Promise.reject( new Error( 'offline' ) )
			: Promise.resolve(),
} ) );

import { MapPreview } from '../../src/editor/map-preview';
import {
	PREVIEW_EVENT,
	PREVIEW_FAILED_EVENT,
} from '../../src/view/preview-events';

/**
 * Block markup as WordPress renders it.
 *
 * @param tiles  Tile URL.
 * @param height Map height in pixels.
 */
const markup = ( tiles = 'a', height = 480 ) =>
	`<div class="wp-block-gpx-route-map-map"><div class="gpxrm"><div class="gpxrm-map" style="height:${ height }px" data-gpxrm-gpx="x.gpx" data-gpxrm-tile-url="${ tiles }"><canvas></canvas></div></div></div>`;

const NOTICE =
	'<div class="gpxrm-notice">GPX Route Map: no GPX file selected.</div>';

let frame: HTMLIFrameElement;
let doc: Document;
let root: ReturnType< typeof createRoot >;
let requests: { target: HTMLElement; signal: AbortSignal }[];

/**
 * Show one preview per entry, each with its own settings.
 *
 * @param blocks Settings per block, keyed so React keeps each block's
 *               component across renders.
 */
function show( blocks: Array< { key: number; attributes?: object } > ) {
	flushSync( () =>
		root.render(
			createElement(
				'div',
				null,
				blocks.map( ( { key, attributes = { key } } ) =>
					createElement(
						'div',
						{ key, 'data-block': key },
						createElement( MapPreview, {
							attributes: attributes as Record< string, unknown >,
							isSelected: false,
							fallback: createElement( 'span', {
								className: 'card',
							} ),
						} )
					)
				)
			)
		)
	);
}

/**
 * Blocks 0..n-1 with default settings.
 * @param {...any} keys
 */
const blocks = ( ...keys: number[] ) => keys.map( ( key ) => ( { key } ) );

/** Let the module load resolve and React apply what follows. */
const settle = async () => {
	for ( let i = 0; i < 3; i++ ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}
	flushSync( () => {} );
};

const cards = () => doc.querySelectorAll( '.card' ).length;
const live = () => requests.filter( ( r ) => ! r.signal.aborted );

/**
 * Answer every block's render with the same markup.
 *
 * @param html Markup, or null for a failed render.
 * @param keys Block keys to answer for.
 */
function answerAll( html: string | null, keys: number[] ) {
	keys.forEach( ( key ) => answers.set( JSON.stringify( { key } ), html ) );
}

beforeEach( () => {
	( window as unknown as { gpxrmDefaults: object } ).gpxrmDefaults = {
		viewModule: 'https://example.test/view.js',
	};
	previewMode = false;
	postId = undefined;
	loadFails = false;
	answers = new Map();
	lastRendered.clear();
	renderedFor.length = 0;
	frame = document.createElement( 'iframe' );
	document.body.append( frame );
	doc = frame.contentDocument as Document;
	requests = [];
	doc.addEventListener( PREVIEW_EVENT, ( event ) => {
		requests.push( {
			target: event.target as HTMLElement,
			signal: ( event as CustomEvent ).detail.signal,
		} );
	} );
	const container = doc.createElement( 'div' );
	doc.body.append( container );
	root = createRoot( container );
} );

afterEach( () => {
	flushSync( () => root.unmount() );
	frame.remove();
} );

const TEN = [ 0, 1, 2, 3, 4, 5, 6, 7, 8, 9 ];

describe( 'MapPreview', () => {
	it( 'keeps at most 8 live maps per canvas and shows the card for the rest', async () => {
		answerAll( markup(), TEN );
		show( blocks( ...TEN ) );
		await settle();

		expect( live() ).toHaveLength( 8 );
		expect( cards() ).toBe( 2 );
		// Made in the canvas's own window, where the view module runs.
		expect(
			live()[ 0 ].signal instanceof
				( frame.contentWindow as unknown as typeof window ).AbortSignal
		).toBe( true );
		// Marked so the front end's own boot leaves it alone.
		expect( live()[ 0 ].target.dataset.gpxrmBooted ).toBe( '1' );
		expect( live()[ 0 ].target.dataset.gpxrmPreview ).toBe( '' );
	} );

	it( 'gives a freed slot to a block that was waiting for one', async () => {
		const nine = [ 0, 1, 2, 3, 4, 5, 6, 7, 8 ];
		answerAll( markup(), nine );
		show( blocks( ...nine ) );
		await settle();
		expect( live() ).toHaveLength( 8 );
		expect( cards() ).toBe( 1 );

		show( blocks( 1, 2, 3, 4, 5, 6, 7, 8 ) );
		await settle();

		expect( live() ).toHaveLength( 8 );
		expect( cards() ).toBe( 0 );
	} );

	// The block finds no slot and only then starts waiting; one freed in
	// between must still reach it.
	it( 'gives a block a slot freed just as it found none', async () => {
		const eight = [ 1, 2, 3, 4, 5, 6, 7, 8 ];
		answerAll( markup(), eight );
		// Block 0 has no render yet; the others take every slot.
		show( blocks( 0, ...eight ) );
		await settle();
		expect( live() ).toHaveLength( 8 );

		// In one go block 0's map arrives, finding no slot, and block 8
		// turns into a notice, freeing one.
		answerAll( markup(), [ 0 ] );
		const gone = { key: 8, gone: true };
		answers.set( JSON.stringify( gone ), NOTICE );
		show( [
			...blocks( 0, 1, 2, 3, 4, 5, 6, 7 ),
			{ key: 8, attributes: gone },
		] );
		await settle();

		expect( doc.querySelector( '.gpxrm-notice' ) ).not.toBeNull();
		expect( cards() ).toBe( 0 );
		expect( live() ).toHaveLength( 8 );
	} );

	// A notice needs no slot, so waiting for one does not hide it.
	it( 'shows a notice at once while waiting for a slot', async () => {
		const nine = [ 0, 1, 2, 3, 4, 5, 6, 7, 8 ];
		answerAll( markup(), nine );
		show( blocks( ...nine ) );
		await settle();
		expect( cards() ).toBe( 1 );

		const gone = { key: 8, gone: true };
		answers.set( JSON.stringify( gone ), NOTICE );
		show( [
			...blocks( 0, 1, 2, 3, 4, 5, 6, 7 ),
			{ key: 8, attributes: gone },
		] );
		await settle();
		expect( doc.querySelector( '.gpxrm-notice' ) ).not.toBeNull();
		expect( cards() ).toBe( 0 );
		expect( live() ).toHaveLength( 8 );

		// A map again: still no slot for it, so the card, until one is freed.
		const back = { key: 8, back: true };
		answers.set( JSON.stringify( back ), markup() );
		show( [
			...blocks( 0, 1, 2, 3, 4, 5, 6, 7 ),
			{ key: 8, attributes: back },
		] );
		await settle();
		expect( doc.querySelector( '.gpxrm-notice' ) ).toBeNull();
		expect( cards() ).toBe( 1 );
		expect( live() ).toHaveLength( 8 );

		// A failed render, then new settings not rendered yet: no notice to
		// show, so still the card rather than an empty preview.
		const failing = { key: 8, failing: true };
		answers.set( JSON.stringify( failing ), null );
		show( [
			...blocks( 0, 1, 2, 3, 4, 5, 6, 7 ),
			{ key: 8, attributes: failing },
		] );
		await settle();
		expect( cards() ).toBe( 1 );
		show( [
			...blocks( 0, 1, 2, 3, 4, 5, 6, 7 ),
			{ key: 8, attributes: { key: 8, pending: true } },
		] );
		await settle();
		expect( cards() ).toBe( 1 );

		show( [
			...blocks( 1, 2, 3, 4, 5, 6, 7 ),
			{ key: 8, attributes: back },
		] );
		await settle();
		expect( cards() ).toBe( 0 );
		expect( live() ).toHaveLength( 8 );
	} );

	it( 'gives the slot back when a block goes', async () => {
		answerAll( markup(), TEN );
		show( blocks( 0, 1, 2, 3, 4, 5, 6, 7 ) );
		await settle();
		expect( live() ).toHaveLength( 8 );

		show( blocks( 1, 2, 3, 4, 5, 6, 7 ) );
		await settle();
		expect( live() ).toHaveLength( 7 );

		show( blocks( 1, 2, 3, 4, 5, 6, 7, 8 ) );
		await settle();
		expect( live() ).toHaveLength( 8 );
		expect( cards() ).toBe( 0 );
	} );

	it( 'falls back to the card, freeing the slot, when the map fails or loses its context', async () => {
		answerAll( markup(), TEN );
		show( blocks( 0, 1, 2, 3, 4, 5, 6, 7 ) );
		await settle();

		live()[ 0 ].target.dispatchEvent(
			new CustomEvent( PREVIEW_FAILED_EVENT, { bubbles: true } )
		);
		await settle();
		expect( cards() ).toBe( 1 );
		expect( live() ).toHaveLength( 7 );

		live()[ 0 ]
			.target.querySelector( 'canvas' )
			?.dispatchEvent( new Event( 'webglcontextlost' ) );
		await settle();
		expect( cards() ).toBe( 2 );
		expect( live() ).toHaveLength( 6 );

		// The failed ones stay cards; the others take the freed slots.
		show( blocks( ...TEN ) );
		await settle();
		expect( live() ).toHaveLength( 8 );
		expect( cards() ).toBe( 2 );
	} );

	// What fails here is the browser - WebGL, the scripts - not the
	// settings: a file the map cannot draw shows its own error in the map.
	it( 'keeps the card once the map failed, whatever the settings', async () => {
		answerAll( markup(), [ 0 ] );
		show( blocks( 0 ) );
		await settle();
		live()[ 0 ].target.dispatchEvent(
			new CustomEvent( PREVIEW_FAILED_EVENT, { bubbles: true } )
		);
		await settle();
		expect( cards() ).toBe( 1 );

		const other = { key: 0, t: 'b' };
		answers.set( JSON.stringify( other ), markup( 'b' ) );
		show( [ { key: 0, attributes: other } ] );
		await settle();
		expect( cards() ).toBe( 1 );
		expect( requests ).toHaveLength( 1 );
	} );

	it( 'falls back to the card when the view module cannot load', async () => {
		loadFails = true;
		answerAll( markup(), [ 0 ] );
		show( blocks( 0 ) );
		await settle();
		expect( cards() ).toBe( 1 );
		expect( requests ).toHaveLength( 0 );
	} );

	it( 'patches a new height and rebuilds for new tiles, keeping its slot', async () => {
		const nine = [ 0, 1, 2, 3, 4, 5, 6, 7, 8 ];
		answerAll( markup(), nine );
		show( blocks( ...nine ) );
		await settle();
		const first = live()[ 0 ].target;

		const taller = nine.map( ( key ) => ( {
			key,
			attributes: { key, h: 300 },
		} ) );
		taller.forEach( ( { attributes } ) =>
			answers.set( JSON.stringify( attributes ), markup( 'a', 300 ) )
		);
		show( taller );
		await settle();
		// Same map, new height: no new request.
		expect( requests ).toHaveLength( 8 );
		expect( first.isConnected ).toBe( true );
		expect( first.getAttribute( 'style' ) ).toBe( 'height:300px' );

		const retiled = nine.map( ( key ) => ( {
			key,
			attributes: { key, t: 'b' },
		} ) );
		retiled.forEach( ( { attributes } ) =>
			answers.set( JSON.stringify( attributes ), markup( 'b', 300 ) )
		);
		show( retiled );
		await settle();
		// A new map each, in the slots they already had.
		expect( requests ).toHaveLength( 16 );
		expect( live() ).toHaveLength( 8 );
		expect( cards() ).toBe( 1 );
	} );

	it( 'shows the card in block previews', async () => {
		previewMode = true;
		answerAll( markup(), [ 0, 1 ] );
		show( blocks( 0, 1 ) );
		await settle();

		expect( requests ).toHaveLength( 0 );
		expect( cards() ).toBe( 2 );
	} );

	// What the front end shows too, for instance for a file that is gone.
	it( 'shows WordPress’s notice when there is no map to show', async () => {
		answerAll( NOTICE, [ 0 ] );
		show( blocks( 0 ) );
		await settle();

		expect( requests ).toHaveLength( 0 );
		expect( cards() ).toBe( 0 );
		expect( doc.querySelector( '.gpxrm-notice' )?.textContent ).toBe(
			'GPX Route Map: no GPX file selected.'
		);
	} );

	it( 'replaces a live map with the notice, freeing its slot', async () => {
		const eight = [ 0, 1, 2, 3, 4, 5, 6, 7 ];
		answerAll( markup(), [ ...eight, 8 ] );
		show( blocks( ...eight ) );
		await settle();
		expect( live() ).toHaveLength( 8 );

		answers.set( JSON.stringify( { key: 0, gone: true } ), NOTICE );
		const withNotice = [
			{ key: 0, attributes: { key: 0, gone: true } },
			...blocks( 1, 2, 3, 4, 5, 6, 7 ),
		];
		show( withNotice );
		await settle();
		expect( doc.querySelector( '.gpxrm-notice' ) ).not.toBeNull();
		expect( live() ).toHaveLength( 7 );

		// The ninth block gets the slot the notice gave up.
		show( [ ...withNotice, ...blocks( 8 ) ] );
		await settle();
		expect( live() ).toHaveLength( 8 );
		expect( cards() ).toBe( 0 );
	} );

	it( 'keeps the slot it had when it rebuilds', async () => {
		const four = [ 0, 1, 2, 3 ];
		answerAll( markup(), [ ...four, 4, 5, 6, 7 ] );
		show( blocks( ...four ) );
		await settle();

		const retiled = four.map( ( key ) => ( {
			key,
			attributes: { key, t: 'b' },
		} ) );
		retiled.forEach( ( { attributes } ) =>
			answers.set( JSON.stringify( attributes ), markup( 'b' ) )
		);
		show( retiled );
		await settle();
		expect( live() ).toHaveLength( 4 );

		// Four rebuilds took no extra slots, so four more maps still fit.
		show( [ ...retiled, ...blocks( 4, 5, 6, 7 ) ] );
		await settle();
		expect( live() ).toHaveLength( 8 );
		expect( cards() ).toBe( 0 );
	} );

	it( 'shows the card when rendering fails, and tries again once the settings change', async () => {
		answerAll( null, [ 0 ] );
		show( blocks( 0 ) );
		await settle();
		expect( cards() ).toBe( 1 );
		expect( requests ).toHaveLength( 0 );

		// New settings, not answered yet: the old failure no longer counts.
		const fixed = { key: 0, fixed: true };
		show( [ { key: 0, attributes: fixed } ] );
		await settle();
		expect( cards() ).toBe( 0 );

		answers.set( JSON.stringify( fixed ), markup() );
		show( [ { key: 0, attributes: fixed } ] );
		await settle();
		expect( cards() ).toBe( 0 );
		expect( live() ).toHaveLength( 1 );
	} );

	it( 'renders for the post being edited', async () => {
		postId = 42;
		answerAll( markup(), [ 0 ] );
		show( blocks( 0 ) );
		await settle();

		expect( renderedFor.at( -1 )?.postId ).toBe( 42 );
	} );

	// The guards themselves are tested on their own; this checks they are on.
	it( 'keeps a drag on the map from moving the block', async () => {
		answerAll( markup(), [ 0 ] );
		show( blocks( 0 ) );
		await settle();
		const mapEl = live()[ 0 ].target;

		mapEl.dispatchEvent( new Event( 'pointerdown', { bubbles: true } ) );
		const drag = new Event( 'dragstart', {
			bubbles: true,
			cancelable: true,
		} );
		mapEl.dispatchEvent( drag );

		expect( drag.defaultPrevented ).toBe( true );
	} );
} );
