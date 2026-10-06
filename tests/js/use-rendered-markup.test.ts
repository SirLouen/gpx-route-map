/**
 * Rendering the block on the server for the editor preview.
 *
 * The preview shows what WordPress renders for the block's settings, so each
 * change of settings means a request. Dragging a slider must not send one per
 * step, a render for settings that have since changed must not win, and a
 * failure must be tied to the settings it happened with, so the next change
 * tries again.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createElement, createRoot } from '@wordpress/element';

import { useRenderedMarkup } from '../../src/editor/use-rendered-markup';
import type { RenderedMarkup } from '../../src/editor/use-rendered-markup';
import { act, settle } from './react-act';

interface Call {
	options: {
		path: string;
		data: { attributes: Record< string, unknown > };
		signal: AbortSignal;
	};
	resolve: ( value: { rendered: string } ) => void;
	reject: ( reason: unknown ) => void;
}
const calls: Call[] = [];

vi.mock( '@wordpress/api-fetch', () => ( {
	default: ( options: Call[ 'options' ] ) =>
		new Promise( ( resolve, reject ) => {
			calls.push( { options, resolve, reject } );
			options.signal?.addEventListener( 'abort', () =>
				reject( new DOMException( 'Aborted', 'AbortError' ) )
			);
		} ),
} ) );

let seen: { rendered: RenderedMarkup | null; json: string }[] = [];

/**
 * A component that only reports what the hook returns.
 *
 * @param props            Props.
 * @param props.attributes Block attributes.
 * @param props.enabled    Whether to render.
 * @param props.postId     The post being edited.
 */
function Probe( props: {
	attributes: Record< string, unknown >;
	enabled: boolean;
	postId?: number;
} ) {
	seen.push(
		useRenderedMarkup( props.attributes, props.enabled, props.postId )
	);
	return null;
}

let root: ReturnType< typeof createRoot >;

/**
 * Render the probe with some attributes.
 *
 * @param attributes Block attributes.
 * @param enabled    Whether rendering is enabled.
 * @param postId     The post being edited.
 */
function show(
	attributes: Record< string, unknown >,
	enabled = true,
	postId?: number
) {
	act( () =>
		root.render( createElement( Probe, { attributes, enabled, postId } ) )
	);
}

/** What the hook returned last. */
const last = () => seen[ seen.length - 1 ];

beforeEach( () => {
	vi.useFakeTimers();
	calls.length = 0;
	seen = [];
	root = createRoot( document.createElement( 'div' ) );
} );

afterEach( () => {
	act( () => root.unmount() );
	vi.useRealTimers();
} );

describe( 'useRenderedMarkup', () => {
	it( 'renders at once, then waits for the settings to settle', async () => {
		show( { height: 400 } );
		vi.advanceTimersByTime( 0 );
		expect( calls ).toHaveLength( 1 );
		calls[ 0 ].resolve( { rendered: '<p>a</p>' } );
		await settle();
		expect( last().rendered?.html ).toBe( '<p>a</p>' );

		show( { height: 500 } );
		vi.advanceTimersByTime( 299 );
		expect( calls ).toHaveLength( 1 );
		vi.advanceTimersByTime( 1 );
		expect( calls ).toHaveLength( 2 );
	} );

	it( 'sends one request for a burst of changes', () => {
		show( { height: 400 } );
		vi.advanceTimersByTime( 0 );
		for ( const height of [ 410, 420, 430 ] ) {
			show( { height } );
			vi.advanceTimersByTime( 100 );
		}
		vi.advanceTimersByTime( 300 );

		expect( calls ).toHaveLength( 2 );
		expect( calls[ 1 ].options.data.attributes.height ).toBe( 430 );
	} );

	// Before the first answer arrives, for instance right after the block
	// is inserted on a slow host, a slider drag still waits to settle.
	it( 'waits to settle even before the first render has answered', () => {
		show( { height: 400 } );
		vi.advanceTimersByTime( 0 );
		for ( let height = 401; height <= 420; height++ ) {
			show( { height } );
			vi.advanceTimersByTime( 16 );
		}
		vi.advanceTimersByTime( 300 );

		expect( calls ).toHaveLength( 2 );
	} );

	it( 'cancels a render that the settings have moved past', async () => {
		show( { height: 400 } );
		vi.advanceTimersByTime( 0 );
		show( { height: 500 } );
		vi.advanceTimersByTime( 300 );
		await settle();

		expect( calls[ 0 ].options.signal.aborted ).toBe( true );
		// The cancelled render is not taken for a failure.
		expect( last().rendered ).toBeNull();
	} );

	it( 'reports a failure along with the settings it failed for', async () => {
		show( { height: 400 } );
		vi.advanceTimersByTime( 0 );
		calls[ 0 ].reject( new Error( '500' ) );
		await settle();

		expect( last().rendered ).toEqual( {
			json: last().json,
			html: null,
		} );

		show( { height: 500 } );
		expect( last().rendered?.json ).not.toBe( last().json );
	} );

	// The endpoint rejects any attribute it does not know, as one a plugin
	// may add to every block, and the block wrapper's own attributes do not
	// change the map. (A 1.x tile address is already dropped when the editor
	// parses the post.)
	it( 'sends only the block’s own attributes', () => {
		show( {
			height: 400,
			provider: 'osm',
			tileUrl: 'https://tiles.example.test/{z}/{x}/{y}.png',
			className: 'is-style-x',
			align: 'wide',
			lock: { move: true },
		} );
		vi.advanceTimersByTime( 0 );

		expect( calls[ 0 ].options.data.attributes ).toEqual( {
			height: 400,
			provider: 'osm',
		} );
	} );

	it( 'does not render again for a change that only touches the wrapper', () => {
		show( { height: 400, align: 'wide' } );
		vi.advanceTimersByTime( 0 );
		show( { height: 400, align: 'full' } );
		vi.advanceTimersByTime( 1000 );

		expect( calls ).toHaveLength( 1 );
	} );

	// Without it, someone who may edit this post only through its post
	// type's own capabilities is refused.
	it( 'names the post being edited', () => {
		show( { height: 400 }, true, 42 );
		vi.advanceTimersByTime( 0 );

		expect( calls[ 0 ].options.path ).toContain( 'post_id=42' );
	} );

	it( 'renders nothing while disabled', () => {
		show( { height: 400 }, false );
		vi.advanceTimersByTime( 1000 );

		expect( calls ).toHaveLength( 0 );
	} );
} );
