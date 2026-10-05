/**
 * The front end's side of the block editor's live preview.
 */

import { PREVIEW_EVENT, PREVIEW_FAILED_EVENT } from './preview-events';
import type { PreviewRequest } from './preview-events';
import type { initInstance } from './map-instance';
import type { MapLibreGl } from './types';

/**
 * Build maps the block editor asks for in this document.
 *
 * The editor dispatches PREVIEW_EVENT on a `.gpxrm-map` element with the
 * signal that will tear the map down. A request without one is ignored: a map
 * the editor could not remove would hold on to a WebGL context, and a page
 * only gets a few.
 *
 * @param doc  The document to answer in: the editor canvas.
 * @param load Loads MapLibre.
 * @param init Builds a map instance.
 */
export function listenForPreviews(
	doc: Document,
	load: () => Promise< MapLibreGl >,
	init: typeof initInstance
): void {
	doc.addEventListener( PREVIEW_EVENT, ( event ) => {
		// Two copies of this module can share a document, for instance one
		// printed by WordPress and one the editor loaded. The first to hear a
		// request takes it.
		if ( event.defaultPrevented ) {
			return;
		}
		const signal = ( event as CustomEvent< PreviewRequest | null > ).detail
			?.signal;
		const mapEl = event.target;
		if ( ! signal || ! ( mapEl instanceof HTMLElement ) ) {
			return;
		}
		event.preventDefault();

		load()
			.then( ( lib ) =>
				signal.aborted ? undefined : init( mapEl, lib, signal )
			)
			.catch( () => {
				if ( ! signal.aborted ) {
					mapEl.dispatchEvent(
						new CustomEvent( PREVIEW_FAILED_EVENT, {
							bubbles: true,
						} )
					);
				}
			} );
	} );
}
