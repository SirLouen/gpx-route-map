/**
 * The block's markup as WordPress renders it.
 */

import apiFetch from '@wordpress/api-fetch';
import { useEffect, useRef, useState } from '@wordpress/element';

import metadata from '../block.json';

/** How long the settings must stay unchanged before rendering again. */
const SETTLE_MS = 300;

/**
 * The attributes the block registers. The render endpoint refuses any other,
 * and a plugin may add its own to every block; those of the block wrapper -
 * alignment, margins - do not change the map either.
 */
const OWN_ATTRIBUTES = Object.keys( metadata.attributes );

/** A render of the block. */
export interface RenderedMarkup {
	/** The settings it was rendered for, as JSON. */
	json: string;
	/** The markup, or null if rendering failed. */
	html: string | null;
}

/**
 * Render the block on the server, as the front end shows it.
 *
 * The first render happens at once. Later ones wait until the settings have
 * stayed unchanged for a moment, so dragging a slider does not send a request
 * per step, and a render the settings have moved past is cancelled.
 *
 * @param attributes Block attributes.
 * @param enabled    Whether to render at all.
 * @param postId     The post being edited, if any: the endpoint then checks
 *                   the right to edit that post rather than posts in general.
 * @return The latest render, null until there is one, and the current
 *         settings as JSON, to tell whether that render is for them.
 */
export function useRenderedMarkup(
	attributes: Record< string, unknown >,
	enabled: boolean,
	postId?: number
): { rendered: RenderedMarkup | null; json: string } {
	const [ rendered, setRendered ] = useState< RenderedMarkup | null >( null );
	const hasRequested = useRef( false );
	// A string, so the effect re-runs on a change of value, not of identity.
	const json = JSON.stringify(
		Object.fromEntries(
			OWN_ATTRIBUTES.filter( ( key ) => key in attributes ).map(
				( key ) => [ key, attributes[ key ] ]
			)
		)
	);

	useEffect( () => {
		if ( ! enabled ) {
			return;
		}
		const controller = new AbortController();
		const path =
			'/wp/v2/block-renderer/gpx-route-map/map?context=edit' +
			( postId ? `&post_id=${ postId }` : '' );
		const timer = setTimeout(
			() => {
				hasRequested.current = true;
				apiFetch< { rendered: string } >( {
					path,
					method: 'POST',
					data: { attributes: JSON.parse( json ) },
					signal: controller.signal,
				} ).then(
					( response ) =>
						setRendered( { json, html: response.rendered } ),
					() => {
						if ( ! controller.signal.aborted ) {
							setRendered( { json, html: null } );
						}
					}
				);
			},
			hasRequested.current ? SETTLE_MS : 0
		);
		return () => {
			clearTimeout( timer );
			controller.abort();
		};
	}, [ json, enabled, postId ] );

	return { rendered, json };
}
