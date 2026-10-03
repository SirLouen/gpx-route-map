/**
 * Keyboard access for markers that open a popup.
 *
 * The fakes mirror MapLibre 6.7, checked against its source:
 * - no keyboard toggle on the marker: setPopup() adds a keypress listener,
 *   but addTo() starts with remove(), which takes it off again;
 * - addTo() builds a fresh popup container, focuses its first link or button
 *   (the content comes before the close button) and then fires "open";
 * - remove() deletes the container and only then fires "close", by which
 *   point focus that was inside it has fallen to <body>.
 */

import { afterEach, describe, expect, it, vi } from 'vitest';

import { keyboardMarker } from '../../src/view/marker-keyboard';

/**
 * A marker and its popup, wired together.
 *
 * @param withLink Whether the popup content holds a link, as a waypoint with a
 *                 GPX <link> does; MapLibre then focuses it, not the button.
 */
function setup( withLink = false ) {
	const el = document.createElement( 'div' );
	el.tabIndex = 0;
	document.body.appendChild( el );

	let container: HTMLElement | undefined;
	const listeners: Record< 'open' | 'close', Array< () => void > > = {
		open: [],
		close: [],
	};

	const popup = {
		on( type: 'open' | 'close', fn: () => void ) {
			listeners[ type ].push( fn );
			return popup;
		},
		isOpen: () => !! container,
		getElement: () => container,
		open() {
			container = document.createElement( 'div' );
			const content = document.createElement( 'div' );
			const text = document.createElement( 'p' );
			content.appendChild( text );
			if ( withLink ) {
				const link = document.createElement( 'a' );
				link.href = 'https://example.com/';
				content.appendChild( link );
			}
			const close = document.createElement( 'button' );
			close.className = 'maplibregl-popup-close-button';
			container.append( content, close );
			document.body.appendChild( container );
			container
				.querySelector< HTMLElement >( 'a[href], button' )
				?.focus();
			listeners.open.forEach( ( fn ) => fn() );
		},
		remove() {
			if ( ! container ) {
				return;
			}
			container.remove();
			container = undefined;
			listeners.close.forEach( ( fn ) => fn() );
		},
	};

	const marker = {
		getElement: () => el,
		togglePopup: vi.fn( () =>
			popup.isOpen() ? popup.remove() : popup.open()
		),
	};

	keyboardMarker( marker, popup, 'Trailhead Refuge' );

	return { el, popup, marker };
}

/**
 * Dispatch a cancelable key event and report whether it was cancelled.
 *
 * @param target Element to dispatch on.
 * @param type   Event type.
 * @param name   KeyboardEvent.key.
 * @param extra  Other event fields.
 */
function key(
	target: Element,
	type: 'keydown' | 'keypress',
	name: string,
	extra: KeyboardEventInit = {}
): boolean {
	const code = ' ' === name ? 'Space' : name;
	const event = new KeyboardEvent( type, {
		key: name,
		code,
		bubbles: true,
		cancelable: true,
		...extra,
	} );
	target.dispatchEvent( event );
	return event.defaultPrevented;
}

/**
 * What has focus in the document that holds `node`.
 *
 * @param node Any node in that document.
 */
function focused( node: Node ): Element {
	return node.ownerDocument?.activeElement as Element;
}

afterEach( () => {
	document.body.replaceChildren();
} );

describe( 'keyboardMarker', () => {
	it( 'names the marker as a button with a collapsed popup', () => {
		const { el } = setup();

		expect( el.getAttribute( 'role' ) ).toBe( 'button' );
		expect( el.getAttribute( 'aria-label' ) ).toBe( 'Trailhead Refuge' );
		expect( el.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it.each( [
		[ 'Enter', 'Enter' ],
		[ 'Space', ' ' ],
	] )( 'opens the popup on %s and cancels the key', ( _name, k ) => {
		const { el, marker, popup } = setup();

		// Cancelled is what stops Space from scrolling the page down a screen.
		expect( key( el, 'keydown', k ) ).toBe( true );
		expect( marker.togglePopup ).toHaveBeenCalledTimes( 1 );
		expect( popup.isOpen() ).toBe( true );
		expect( el.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
	} );

	// Opening moves focus into the popup, so the repeats of a held key land
	// there, not on the marker: on the close button a repeat would click it,
	// and on a link Space would scroll the page.
	it.each( [
		[ 'Enter on the close button', 'Enter', false, 'BUTTON' ],
		[ 'Space on the close button', ' ', false, 'BUTTON' ],
		[ 'Space on a waypoint link', ' ', true, 'A' ],
	] )(
		'cancels the repeats of a held %s',
		( _label, k, withLink, landsOn ) => {
			const { el, marker, popup } = setup( withLink );

			key( el, 'keydown', k );
			const target = focused( el );
			expect( target.tagName ).toBe( landsOn );

			expect( key( target, 'keydown', k, { repeat: true } ) ).toBe(
				true
			);
			expect( key( target, 'keydown', k, { repeat: true } ) ).toBe(
				true
			);
			expect( marker.togglePopup ).toHaveBeenCalledTimes( 1 );
			expect( popup.isOpen() ).toBe( true );
		}
	);

	it( 'lets a fresh press inside the popup through', () => {
		const { el } = setup();
		key( el, 'keydown', 'Enter' );

		// Only repeats are cancelled; a deliberate press still reaches the
		// close button as normal.
		expect( key( focused( el ), 'keydown', 'Enter' ) ).toBe( false );
	} );

	it( 'ignores a held key on the marker itself', () => {
		const { el, marker } = setup();

		key( el, 'keydown', 'Enter', { repeat: true } );

		expect( marker.togglePopup ).not.toHaveBeenCalled();
	} );

	it( 'closes on Escape from the marker and keeps it to itself', () => {
		const { el, popup } = setup();
		key( el, 'keydown', 'Enter' );
		el.focus();

		const outer = vi.fn();
		document.addEventListener( 'keydown', outer );
		expect( key( el, 'keydown', 'Escape' ) ).toBe( true );
		document.removeEventListener( 'keydown', outer );

		expect( popup.isOpen() ).toBe( false );
		expect( outer ).not.toHaveBeenCalled();
		expect( focused( el ) ).toBe( el );
		expect( el.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'closes on Escape from inside the popup and returns focus', () => {
		const { el, popup } = setup();
		key( el, 'keydown', 'Enter' );
		const inside = focused( el );

		const outer = vi.fn();
		document.addEventListener( 'keydown', outer );
		expect( key( inside, 'keydown', 'Escape' ) ).toBe( true );
		document.removeEventListener( 'keydown', outer );

		expect( popup.isOpen() ).toBe( false );
		expect( outer ).not.toHaveBeenCalled();
		expect( focused( el ) ).toBe( el );
	} );

	it( 'returns focus when the close button takes it down with it', () => {
		const { el, popup } = setup();
		key( el, 'keydown', 'Enter' );

		// What activating the close button does: remove() while focus sits
		// inside the popup.
		popup.remove();

		expect( focused( el ) ).toBe( el );
	} );

	// Screen readers, voice control and switch devices activate the close
	// button through the accessibility API, which Chromium delivers as a
	// trusted pointerdown. Treating that as "a mouse user, leave focus be"
	// would strand exactly the people this is for.
	it( 'returns focus however the close button was pressed', () => {
		const { el, popup } = setup();
		key( el, 'keydown', 'Enter' );

		focused( el ).dispatchEvent(
			new Event( 'pointerdown', { bubbles: true } )
		);
		popup.remove();

		expect( focused( el ) ).toBe( el );
	} );

	// Focus always comes back; only the ring depends on how the popup closed.
	// jsdom ignores focusVisible, so this checks what was asked of the browser.
	it.each( [
		[ 'Escape inside the popup', 'escape', true ],
		[ 'Enter on the close button', 'enter', true ],
		[ 'a mouse, pen or finger', 'pointer', false ],
	] )(
		'asks for a visible ring only after a keyboard close: %s',
		( _label, how, ring ) => {
			const { el, popup } = setup();
			key( el, 'keydown', 'Enter' );
			const inside = focused( el );
			const spy = vi.spyOn( el, 'focus' );

			if ( 'escape' === how ) {
				key( inside, 'keydown', 'Escape' );
			} else if ( 'enter' === how ) {
				// The keydown reaches the button, whose click closes the popup.
				key( inside, 'keydown', 'Enter' );
				popup.remove();
			} else {
				inside.dispatchEvent(
					new Event( 'pointerdown', { bubbles: true } )
				);
				popup.remove();
			}

			expect( spy ).toHaveBeenCalledWith(
				expect.objectContaining( { focusVisible: ring } )
			);
		}
	);

	it( 'does not carry a keyboard close over to the next open', () => {
		const { el, popup } = setup();
		key( el, 'keydown', 'Enter' );
		key( focused( el ), 'keydown', 'Escape' );

		key( el, 'keydown', 'Enter' );
		const spy = vi.spyOn( el, 'focus' );
		popup.remove();

		expect( spy ).toHaveBeenCalledWith(
			expect.objectContaining( { focusVisible: false } )
		);
	} );

	it( 'leaves focus alone if the user already moved it elsewhere', () => {
		const { el, popup } = setup();
		const elsewhere = document.createElement( 'button' );
		document.body.appendChild( elsewhere );

		key( el, 'keydown', 'Enter' );
		elsewhere.focus();
		popup.remove();

		expect( focused( el ) ).toBe( elsewhere );
	} );

	it( 'lets other keys through untouched', () => {
		const { el, marker } = setup();

		for ( const k of [ 'Tab', 'ArrowLeft', '+', 'a' ] ) {
			expect( key( el, 'keydown', k ), k ).toBe( false );
		}
		// Escape too, when there is nothing to close.
		expect( key( el, 'keydown', 'Escape' ) ).toBe( false );
		expect( marker.togglePopup ).not.toHaveBeenCalled();
	} );

	it( 'opens again after closing, with fresh popup listeners', () => {
		const { el, popup } = setup();

		key( el, 'keydown', 'Enter' );
		popup.remove();
		key( el, 'keydown', 'Enter' );
		key( focused( el ), 'keydown', 'Escape' );

		expect( popup.isOpen() ).toBe( false );
		expect( focused( el ) ).toBe( el );
	} );
} );
