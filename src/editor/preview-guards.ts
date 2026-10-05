/**
 * Keeping the block editor out of the live map's way.
 */

/**
 * Stop the editor hijacking a selected preview.
 *
 * - A drag that starts in the preview pans the map or scrubs the elevation
 *   profile. The block is draggable while selected, so without this the
 *   browser would also start dragging the whole block, and WordPress's own
 *   drag handler would follow it into a drag that never ends.
 * - The map's links - the tile credit, a download from another site - would
 *   navigate the editor canvas itself away; they open in a new tab instead.
 *
 * @param host The element holding the preview.
 * @return Removes the guards.
 */
export function guardPreview( host: HTMLElement ): () => void {
	const doc = host.ownerDocument;
	const win = doc.defaultView;

	let pressedInside = false;
	const press = ( event: Event ) => {
		pressedInside = host.contains( event.target as Node | null );
	};
	const drag = ( event: Event ) => {
		if ( pressedInside ) {
			event.preventDefault();
			event.stopPropagation();
		}
	};

	const follow = ( event: Event ) => {
		const target = event.target as Element | null;
		const link = target?.closest?.( 'a[href]' ) as HTMLAnchorElement | null;
		if ( ! link || ! host.contains( link ) || '_blank' === link.target ) {
			return;
		}
		// A download from this site stays in place; across sites the browser
		// ignores `download` and would navigate. "This site" is the base URL:
		// the editor canvas is a blob document based on the editor's URL.
		if (
			link.hasAttribute( 'download' ) &&
			link.origin === new URL( doc.baseURI ).origin
		) {
			return;
		}
		event.preventDefault();
		win?.open( link.href, '_blank', 'noopener,noreferrer' );
	};

	// Capturing, so they run before anything inside the map or the block.
	doc.addEventListener( 'pointerdown', press, true );
	doc.addEventListener( 'dragstart', drag, true );
	host.addEventListener( 'click', follow, true );
	return () => {
		doc.removeEventListener( 'pointerdown', press, true );
		doc.removeEventListener( 'dragstart', drag, true );
		host.removeEventListener( 'click', follow, true );
	};
}
