/**
 * Keyboard and screen-reader access for map markers that open a popup.
 *
 * Free of MapLibre imports so it can be unit tested directly; it only needs
 * the slice of the Marker and Popup APIs described below.
 */

/** The part of a MapLibre Marker this needs. */
export interface MarkerLike {
	getElement: () => HTMLElement;
	togglePopup: () => unknown;
}

/** The part of a MapLibre Popup this needs. */
export interface PopupLike {
	on: ( type: 'open' | 'close', listener: () => void ) => unknown;
	isOpen: () => boolean;
	remove: () => unknown;
	getElement: () => HTMLElement | undefined;
}

/**
 * Make a marker's popup usable from the keyboard.
 *
 * MapLibre 6 makes a marker with a popup focusable but leaves it unusable
 * from the keyboard:
 *
 * - Enter and Space do nothing. setPopup() adds a keypress toggle, but
 *   addTo() starts with remove(), which takes that listener off again, so
 *   with the usual setPopup().addTo() order it never exists.
 * - Space scrolls the page down a screen instead.
 * - Escape does nothing.
 * - Closing from inside the popup removes the focused close button with it,
 *   dropping focus to <body> and sending the user back to the top of the page.
 * - Only its default pin gets a role, and that pin's name is an English
 *   "Map marker"; a custom marker element is focusable but anonymous.
 *
 * @param marker The marker that owns the popup.
 * @param popup  Its popup.
 * @param label  Accessible name: what the popup is about.
 */
export function keyboardMarker(
	marker: MarkerLike,
	popup: PopupLike,
	label: string
): void {
	const el = marker.getElement();

	el.setAttribute( 'role', 'button' );
	el.setAttribute( 'aria-label', label );
	el.setAttribute( 'aria-expanded', 'false' );

	// Whether the popup is being closed from the keyboard, which decides
	// whether the marker shows a focus ring when focus comes back to it.
	let closingByKeyboard = false;

	el.addEventListener( 'keydown', ( e ) => {
		if ( 'Escape' === e.key ) {
			if ( popup.isOpen() ) {
				e.preventDefault();
				e.stopPropagation();
				closingByKeyboard = true;
				popup.remove();
			}
			return;
		}

		if ( 'Enter' !== e.key && ' ' !== e.key ) {
			return;
		}

		// Cancelled so Space does not scroll the page. Handled on keydown, as
		// a native button would, rather than on keypress.
		e.preventDefault();
		if ( ! e.repeat ) {
			marker.togglePopup();
		}
	} );

	popup.on( 'open', () => {
		el.setAttribute( 'aria-expanded', 'true' );
		closingByKeyboard = false;

		// A fresh container on every open, so the listener goes with it.
		popup.getElement()?.addEventListener( 'keydown', ( e ) => {
			if ( 'Escape' === e.key ) {
				e.preventDefault();
				e.stopPropagation();
				closingByKeyboard = true;
				popup.remove();
				return;
			}

			if ( 'Enter' !== e.key && ' ' !== e.key ) {
				return;
			}

			// Opening moved focus into the popup, so a held Enter or Space keeps
			// repeating in here. On the close button a repeat clicks it and
			// shuts the popup it just opened; on a link, Space scrolls the page.
			if ( e.repeat ) {
				e.preventDefault();
			} else if (
				e.target instanceof Element &&
				e.target.closest( '.maplibregl-popup-close-button' )
			) {
				closingByKeyboard = true;
			}
		} );
	} );

	popup.on( 'close', () => {
		el.setAttribute( 'aria-expanded', 'false' );

		// Only reclaim focus that was lost with the popup. If the user moved it
		// somewhere on purpose - clicked the map, tabbed away - leave it there.
		// The marker's own document, which is not the global one when the map
		// sits in an iframe.
		const doc = el.ownerDocument;
		const active = doc.activeElement;
		if ( active && active !== doc.body ) {
			return;
		}

		// Focus comes back however the popup was closed: assistive technology
		// activating the close button arrives as a trusted pointerdown in
		// Chromium, so skipping pointer closes would strand exactly the users
		// this is for. Only the ring depends on it. Chromium and Firefox honour
		// focusVisible; elsewhere it is ignored and the ring may also show
		// after a mouse close, which is harmless.
		el.focus( {
			preventScroll: true,
			focusVisible: closingByKeyboard,
		} as FocusOptions );
	} );
}
