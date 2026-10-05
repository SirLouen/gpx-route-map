/**
 * Keeping the block editor out of the live map's way.
 *
 * Two things in the editor would otherwise hijack a selected preview. The
 * block itself is draggable, so pressing on the map, its stats or its
 * elevation profile and moving would start dragging the whole block. And the
 * map's links (the tile credit, a download from another site) would navigate
 * the editor canvas itself away.
 */

import { afterEach, describe, expect, it, vi } from 'vitest';

import { guardPreview } from '../../src/editor/preview-guards';

/**
 * A block wrapper holding a preview, in a document with a window of its own,
 * as the editor canvas iframe has.
 */
function canvas() {
	const frame = document.createElement( 'iframe' );
	document.body.append( frame );
	const doc = frame.contentDocument as Document;
	const block = doc.createElement( 'div' );
	block.draggable = true;
	const host = doc.createElement( 'div' );
	const profile = doc.createElement( 'canvas' );
	host.append( profile );
	block.append( host );
	doc.body.append( block );
	return { doc, block, host, profile };
}

/**
 * Press on an element and start a native drag, as a browser does when the
 * pointer moves with the button held. The drag is fired at the draggable
 * block, not at what was pressed.
 *
 * @param pressed The element pressed.
 * @param block   The draggable block wrapper.
 * @return Whether the drag was cancelled, and whether the block's own drag
 *         handler saw it.
 */
function pressAndDrag( pressed: Element, block: Element ) {
	const blockHandler = vi.fn();
	block.addEventListener( 'dragstart', blockHandler );
	pressed.dispatchEvent( new Event( 'pointerdown', { bubbles: true } ) );
	const drag = new Event( 'dragstart', { bubbles: true, cancelable: true } );
	block.dispatchEvent( drag );
	block.removeEventListener( 'dragstart', blockHandler );
	return {
		cancelled: drag.defaultPrevented,
		handled: blockHandler.mock.calls.length > 0,
	};
}

/**
 * A link inside the preview.
 *
 * @param host       The preview host.
 * @param href       Link target.
 * @param attributes Other attributes.
 */
function link(
	host: HTMLElement,
	href: string,
	attributes: Record< string, string > = {}
): HTMLAnchorElement {
	const a = host.ownerDocument.createElement( 'a' );
	a.href = href;
	Object.entries( attributes ).forEach( ( [ k, v ] ) =>
		a.setAttribute( k, v )
	);
	host.append( a );
	return a;
}

/**
 * Click a link and report whether the click was cancelled.
 *
 * @param a The link.
 */
function click( a: HTMLAnchorElement ): boolean {
	const event = new MouseEvent( 'click', {
		bubbles: true,
		cancelable: true,
	} );
	a.dispatchEvent( event );
	return event.defaultPrevented;
}

afterEach( () => {
	vi.restoreAllMocks();
	document.body.replaceChildren();
} );

describe( 'guardPreview: dragging', () => {
	// Cancelling the browser's drag is not enough: WordPress's own handler on
	// the block would still start its drag, which then never ends.
	it.each( [ [ 'the map' ], [ 'the elevation profile' ] ] )(
		'keeps a drag that starts on %s from moving the block',
		( where ) => {
			const { block, host, profile } = canvas();
			guardPreview( host );

			const pressed = 'the map' === where ? host : profile;
			expect( pressAndDrag( pressed, block ) ).toEqual( {
				cancelled: true,
				handled: false,
			} );
		}
	);

	it( 'leaves a drag that starts outside the preview alone', () => {
		const { doc, block, host } = canvas();
		guardPreview( host );

		expect( pressAndDrag( doc.body, block ) ).toEqual( {
			cancelled: false,
			handled: true,
		} );
	} );

	it( 'forgets an earlier press inside once the pointer goes down elsewhere', () => {
		const { doc, block, host } = canvas();
		guardPreview( host );

		host.dispatchEvent( new Event( 'pointerdown', { bubbles: true } ) );
		expect( pressAndDrag( doc.body, block ).handled ).toBe( true );
	} );

	it( 'stops guarding once removed', () => {
		const { block, host } = canvas();
		const remove = guardPreview( host );
		// Pressed inside first, so a guard left behind would still act.
		host.dispatchEvent( new Event( 'pointerdown', { bubbles: true } ) );

		remove();

		expect( pressAndDrag( host, block ) ).toEqual( {
			cancelled: false,
			handled: true,
		} );
	} );
} );

describe( 'guardPreview: links', () => {
	it( 'opens the tile credit in a new tab instead of navigating the canvas', () => {
		const { host, doc } = canvas();
		const open = vi.fn();
		vi.spyOn( doc.defaultView as Window, 'open' ).mockImplementation(
			open
		);
		guardPreview( host );

		const credit = link( host, 'https://www.openstreetmap.org/copyright' );

		expect( click( credit ) ).toBe( true );
		expect( open ).toHaveBeenCalledWith(
			'https://www.openstreetmap.org/copyright',
			'_blank',
			'noopener,noreferrer'
		);
	} );

	it.each( [
		[
			'a link that already opens a new tab',
			{ target: '_blank' },
			'https://example.org/',
		],
		[ 'a download from this site', { download: 'route.gpx' }, '' ],
	] )( 'leaves %s alone', ( _label, attributes, href ) => {
		const { host, doc } = canvas();
		const open = vi.fn();
		vi.spyOn( doc.defaultView as Window, 'open' ).mockImplementation(
			open
		);
		guardPreview( host );

		const own = href || new URL( '/route.gpx', doc.baseURI ).href;
		expect( click( link( host, own, attributes ) ) ).toBe( false );
		expect( open ).not.toHaveBeenCalled();
	} );

	// The browser ignores `download` across origins and navigates instead.
	it( 'opens a download from another site in a new tab', () => {
		const { host, doc } = canvas();
		const open = vi.fn();
		vi.spyOn( doc.defaultView as Window, 'open' ).mockImplementation(
			open
		);
		guardPreview( host );

		const other = link( host, 'https://elsewhere.example/route.gpx', {
			download: 'route.gpx',
		} );

		expect( click( other ) ).toBe( true );
		expect( open ).toHaveBeenCalledTimes( 1 );
	} );
} );
