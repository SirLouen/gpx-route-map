/**
 * How the block editor's live preview and the front-end view module talk.
 *
 * The editor runs the front-end view module inside its canvas, which is
 * usually an iframe with a window of its own, so the two cannot share imports.
 * The editor asks for a map by dispatching PREVIEW_EVENT on a `.gpxrm-map`
 * element, with the signal that will tear that map down, and hears
 * PREVIEW_FAILED_EVENT back from the element if the map could not be built.
 */

export const PREVIEW_EVENT = 'gpxrm-preview';

export const PREVIEW_FAILED_EVENT = 'gpxrm-preview-failed';

/** The detail of a PREVIEW_EVENT. */
export interface PreviewRequest {
	/** Aborted to tear the map down. Created in the element's own window. */
	signal: AbortSignal;
}

/**
 * Ask the view module in a map element's document for a live map.
 *
 * The event bubbles to the document, where the module listens, and is
 * cancelable, which is how a module claims it so a second copy does not build
 * the same map again. It is made with the element's own window, which in the
 * editor is the canvas iframe's rather than the editor's.
 *
 * @param mapEl  The `.gpxrm-map` element.
 * @param signal Aborted to tear the map down.
 * @return Whether a view module took the request.
 */
export function requestPreview(
	mapEl: HTMLElement,
	signal: AbortSignal
): boolean {
	const RequestEvent =
		mapEl.ownerDocument.defaultView?.CustomEvent ?? CustomEvent;
	const detail: PreviewRequest = { signal };
	return ! mapEl.dispatchEvent(
		new RequestEvent( PREVIEW_EVENT, {
			bubbles: true,
			cancelable: true,
			detail,
		} )
	);
}
