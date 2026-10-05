/**
 * The front end's side of the block editor's live preview.
 *
 * The editor runs the front-end view module inside its canvas and asks it for
 * a map by dispatching an event on a `.gpxrm-map` element, with the
 * AbortSignal that will tear that map down. It hears back only when the map
 * could not be built, so it can show its card instead.
 */

import { afterEach, describe, expect, it, vi } from 'vitest';

import { listenForPreviews } from '../../src/view/preview';
import {
	PREVIEW_EVENT,
	PREVIEW_FAILED_EVENT,
	requestPreview,
} from '../../src/view/preview-events';
import { createMap } from '../../src/view/map-core';
import type { MapLibreGl } from '../../src/view/types';

const LIB = { fake: 'maplibre' } as unknown as MapLibreGl;

/**
 * A promise and the functions that settle it.
 */
function deferred< T >() {
	let resolve: ( value: T ) => void = () => {};
	let reject: ( reason: unknown ) => void = () => {};
	const promise = new Promise< T >( ( res, rej ) => {
		resolve = res;
		reject = rej;
	} );
	return { promise, resolve, reject };
}

/** Let pending promise callbacks run. */
const settle = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

/** A map element in its own document, so listeners from other tests never hear it. */
function mapElement() {
	const doc = document.implementation.createHTMLDocument( 'canvas' );
	const mapEl = doc.createElement( 'div' );
	mapEl.className = 'gpxrm-map';
	doc.body.append( mapEl );
	return { doc, mapEl };
}

/**
 * Ask for a preview the way the editor does.
 *
 * @param mapEl  Map element.
 * @param signal Signal that will tear the map down.
 * @return Whether a listener took the request.
 */
function request( mapEl: HTMLElement, signal: AbortSignal ): boolean {
	return requestPreview( mapEl, signal );
}

/**
 * A request event built by hand, for requests the editor never sends.
 *
 * @param target Where to dispatch it.
 * @param detail Its detail.
 * @return Whether a listener took it.
 */
function rawRequest( target: EventTarget, detail: unknown ): boolean {
	const event = new CustomEvent( PREVIEW_EVENT, {
		bubbles: true,
		cancelable: true,
		detail,
	} );
	target.dispatchEvent( event );
	return event.defaultPrevented;
}

afterEach( () => {
	vi.restoreAllMocks();
} );

describe( 'listenForPreviews', () => {
	it( 'builds a map with the editor’s signal once the library loads', async () => {
		const { doc, mapEl } = mapElement();
		const init = vi.fn( () => Promise.resolve() );
		listenForPreviews( doc, () => Promise.resolve( LIB ), init );
		const controller = new AbortController();

		expect( request( mapEl, controller.signal ) ).toBe( true );
		await settle();

		expect( init ).toHaveBeenCalledTimes( 1 );
		expect( init ).toHaveBeenCalledWith( mapEl, LIB, controller.signal );
	} );

	// The canvas can end up with two copies of the module, for instance one
	// printed by WordPress and one loaded by the editor under another URL.
	it( 'answers each request once even when two copies are listening', async () => {
		const { doc, mapEl } = mapElement();
		const first = vi.fn( () => Promise.resolve() );
		const second = vi.fn( () => Promise.resolve() );
		listenForPreviews( doc, () => Promise.resolve( LIB ), first );
		listenForPreviews( doc, () => Promise.resolve( LIB ), second );

		request( mapEl, new AbortController().signal );
		await settle();

		expect( first.mock.calls.length + second.mock.calls.length ).toBe( 1 );
	} );

	it( 'builds nothing when the request is withdrawn before the library loads', async () => {
		const { doc, mapEl } = mapElement();
		const library = deferred< MapLibreGl >();
		const init = vi.fn( () => Promise.resolve() );
		listenForPreviews( doc, () => library.promise, init );
		const controller = new AbortController();

		request( mapEl, controller.signal );
		controller.abort();
		library.resolve( LIB );
		await settle();

		expect( init ).not.toHaveBeenCalled();
	} );

	// A map that cannot be torn down has no place in the editor.
	it( 'ignores a request that comes without a signal', async () => {
		const { doc, mapEl } = mapElement();
		const init = vi.fn( () => Promise.resolve() );
		listenForPreviews( doc, () => Promise.resolve( LIB ), init );

		expect( rawRequest( mapEl, null ) ).toBe( false );
		await settle();

		expect( init ).not.toHaveBeenCalled();
	} );

	it( 'ignores a request that is not aimed at an element', async () => {
		const { doc } = mapElement();
		const init = vi.fn( () => Promise.resolve() );
		listenForPreviews( doc, () => Promise.resolve( LIB ), init );

		const signal = new AbortController().signal;
		expect( rawRequest( doc, { signal } ) ).toBe( false );
		await settle();

		expect( init ).not.toHaveBeenCalled();
	} );

	it.each( [
		[
			'the library cannot load',
			() => Promise.reject( new Error( 'offline' ) ),
			() => Promise.resolve(),
		],
		[
			'the map cannot be built',
			() => Promise.resolve( LIB ),
			() => Promise.reject( new Error( 'no WebGL' ) ),
		],
	] )( 'tells the editor when %s', async ( _label, load, init ) => {
		const { doc, mapEl } = mapElement();
		listenForPreviews( doc, load, init );
		const failed = vi.fn();
		doc.addEventListener( PREVIEW_FAILED_EVENT, failed );

		request( mapEl, new AbortController().signal );
		await settle();

		expect( failed ).toHaveBeenCalledTimes( 1 );
		expect( failed.mock.calls[ 0 ][ 0 ].target ).toBe( mapEl );
	} );

	it( 'stays quiet about a failure once the request was withdrawn', async () => {
		const { doc, mapEl } = mapElement();
		const library = deferred< MapLibreGl >();
		listenForPreviews( doc, () => library.promise, vi.fn() );
		const failed = vi.fn();
		doc.addEventListener( PREVIEW_FAILED_EVENT, failed );
		const controller = new AbortController();

		request( mapEl, controller.signal );
		controller.abort();
		library.reject( new Error( 'offline' ) );
		await settle();

		expect( failed ).not.toHaveBeenCalled();
	} );
} );

describe( 'createMap in the editor preview', () => {
	/**
	 * Build a map with a fake library and report which controls it got.
	 *
	 * @param preview Whether it is an editor preview.
	 */
	function controlsFor( preview: boolean ): string[] {
		const added: string[] = [];
		const control = ( name: string ) =>
			class {
				name = name;
			};
		const maplibregl = {
			Map: class {
				addControl( c: { name: string } ) {
					added.push( c.name );
				}
			},
			NavigationControl: control( 'navigation' ),
			ScaleControl: control( 'scale' ),
			FullscreenControl: control( 'fullscreen' ),
			GeolocateControl: control( 'geolocate' ),
		} as unknown as MapLibreGl;

		createMap( {
			maplibregl,
			container: document.createElement( 'div' ),
			style: { version: 8, sources: {}, layers: [] },
			bounds: [
				[ 0, 0 ],
				[ 1, 1 ],
			],
			preview,
		} );
		return added;
	}

	it( 'leaves out fullscreen and “find my location”', () => {
		expect( controlsFor( true ) ).toEqual( [ 'navigation', 'scale' ] );
	} );

	it( 'keeps every control on the front end', () => {
		expect( controlsFor( false ) ).toEqual( [
			'navigation',
			'scale',
			'fullscreen',
			'geolocate',
		] );
	} );
} );
