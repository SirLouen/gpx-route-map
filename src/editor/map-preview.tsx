/**
 * Live preview of the map in the block editor.
 */

import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

import { PREVIEW_FAILED_EVENT, requestPreview } from '../view/preview-events';
import { loadViewModule } from './load-view';
import { guardPreview } from './preview-guards';
import { needsRebuild, parseRendered, patchPreview } from './preview-markup';
import { useRenderedMarkup } from './use-rendered-markup';

/**
 * Live maps per canvas. Browsers give a page about 16 WebGL contexts and take
 * the oldest back when more are asked for, so past this the preview shows the
 * card instead of risking maps going blank.
 */
const MAX_LIVE_MAPS = 8;

const liveCounts = new WeakMap< Document, number >();

/** Previews waiting for a free slot, by canvas; each tries again when told. */
const waiting = new WeakMap< Document, Set< () => void > >();

/**
 * Take one of a canvas's live map slots.
 *
 * @param doc The canvas document.
 * @return Whether one was free.
 */
function claimSlot( doc: Document ): boolean {
	const count = liveCounts.get( doc ) ?? 0;
	if ( count >= MAX_LIVE_MAPS ) {
		return false;
	}
	liveCounts.set( doc, count + 1 );
	return true;
}

/**
 * Give back a live map slot, and tell the previews waiting for one.
 *
 * @param doc The canvas document.
 */
function releaseSlot( doc: Document ): void {
	liveCounts.set( doc, Math.max( 0, ( liveCounts.get( doc ) ?? 1 ) - 1 ) );
	// All of them try; the first to claim it wins, the rest wait on.
	[ ...( waiting.get( doc ) ?? [] ) ].forEach( ( retry ) => retry() );
}

/** The view module's URL, which the server passes to the editor. */
function viewModuleUrl(): string {
	const url = (
		window as unknown as { gpxrmDefaults?: { viewModule?: unknown } }
	 ).gpxrmDefaults?.viewModule;
	return 'string' === typeof url ? url : '';
}

/** The map on screen. */
interface LiveMap {
	/** The `.gpxrm` element shown. */
	preview: HTMLElement;
	/** Aborted to tear the map down. Made in the canvas's window. */
	controller: AbortController;
	/** The canvas document holding its slot. */
	doc: Document;
}

interface MapPreviewProps {
	attributes: Record< string, unknown >;
	isSelected: boolean;
	/** Shown when no live map can be: block previews, errors, too many maps. */
	fallback: ReactNode;
}

/**
 * The map as the front end shows it, live, updated as the settings change.
 *
 * WordPress renders the block's markup, exactly as for the front end, and the
 * front end's own view module, loaded into the editor canvas, builds the map
 * on it. A new height or new stats values are patched into the live map;
 * anything else it was built from rebuilds it. When WordPress renders a notice
 * instead of a map, as for a missing file, the preview shows that notice.
 *
 * The map stays inert until the block is selected, so clicking it selects the
 * block and scrolling past does not zoom it.
 *
 * @param props            Props.
 * @param props.attributes Block attributes.
 * @param props.isSelected Whether the block is selected.
 * @param props.fallback   Shown instead when there can be no live map.
 */
export function MapPreview( {
	attributes,
	isSelected,
	fallback,
}: MapPreviewProps ) {
	const { isPreviewMode, postId } = useSelect( ( select ) => {
		const settings = (
			select( 'core/block-editor' ) as {
				getSettings: () => { isPreviewMode?: boolean };
			}
		 ).getSettings();
		// Absent outside the post editor, as in the widgets editor.
		const id = (
			select( 'core/editor' ) as
				{ getCurrentPostId?: () => unknown } | undefined
		 )?.getCurrentPostId?.();
		return {
			// Inserter, pattern and style previews: a static card.
			isPreviewMode: !! settings.isPreviewMode,
			postId: 'number' === typeof id ? id : undefined,
		};
	}, [] );

	// Failures that no change of settings undoes: the map could not be built,
	// or lost its WebGL context.
	const [ failed, setFailed ] = useState( false );
	// Past the limit of live maps: the card, until another preview frees a
	// slot. The canvas it waits on is kept, since the host is gone meanwhile.
	const [ slotWait, setSlotWait ] = useState< Document | null >( null );
	const src = viewModuleUrl();
	const live = ! isPreviewMode && ! failed && '' !== src;
	const { rendered, json } = useRenderedMarkup( attributes, live, postId );
	// A failed render counts only for the settings it was for: the next
	// change renders again.
	const renderFailed =
		!! rendered && null === rendered.html && rendered.json === json;
	const showMap = live && ! renderFailed && ! slotWait;

	const hostRef = useRef< HTMLDivElement >( null );
	const mapRef = useRef< LiveMap | null >( null );
	const [ visible, setVisible ] = useState( false );

	// Fall back to the card when the map cannot be built or loses its
	// context, and keep the editor from hijacking it. Declared before the
	// teardown below, so on unmount these are gone before tearing down the
	// map makes it lose its context on purpose.
	useEffect( () => {
		const host = hostRef.current;
		if ( ! showMap || ! host ) {
			return;
		}
		const fail = () => setFailed( true );
		// The event does not bubble; capturing still sees it. Only the live
		// map can raise it here: a replaced map leaves the host before it is
		// torn down.
		const lost = () => {
			if ( mapRef.current ) {
				setFailed( true );
			}
		};
		host.addEventListener( PREVIEW_FAILED_EVENT, fail );
		host.addEventListener( 'webglcontextlost', lost, true );
		const unguard = guardPreview( host );
		return () => {
			host.removeEventListener( PREVIEW_FAILED_EVENT, fail );
			host.removeEventListener( 'webglcontextlost', lost, true );
			unguard();
		};
	}, [ showMap ] );

	// Tear the map down when the block goes or shows the card instead.
	useEffect( () => {
		if ( ! showMap ) {
			return;
		}
		return () => {
			const current = mapRef.current;
			if ( current ) {
				mapRef.current = null;
				current.controller.abort();
				releaseSlot( current.doc );
			}
		};
	}, [ showMap ] );

	// Wait for a slot: try again when another preview gives one back.
	useEffect( () => {
		if ( ! slotWait ) {
			return;
		}
		const retry = () => setSlotWait( null );
		const set = waiting.get( slotWait ) ?? new Set< () => void >();
		waiting.set( slotWait, set );
		set.add( retry );
		return () => {
			set.delete( retry );
		};
	}, [ slotWait ] );

	// Build only once the block comes near the viewport.
	useEffect( () => {
		const host = hostRef.current;
		const win = host?.ownerDocument.defaultView;
		if ( ! showMap || ! host || ! win ) {
			return;
		}
		if ( ! ( 'IntersectionObserver' in win ) ) {
			setVisible( true );
			return;
		}
		const observer = new win.IntersectionObserver(
			( entries ) => {
				if ( entries.some( ( entry ) => entry.isIntersecting ) ) {
					setVisible( true );
					observer.disconnect();
				}
			},
			{ rootMargin: '300px' }
		);
		observer.observe( host );
		return () => observer.disconnect();
	}, [ showMap ] );

	// Show each new render: patch the live map, rebuild it, or show the
	// notice WordPress rendered instead of a map.
	useEffect( () => {
		const host = hostRef.current;
		const doc = host?.ownerDocument;
		const win = doc?.defaultView;
		if (
			! showMap ||
			! visible ||
			! rendered ||
			null === rendered.html ||
			! host ||
			! doc ||
			! win
		) {
			return;
		}
		const content = parseRendered( rendered.html, doc );
		const next = content.querySelector< HTMLElement >( '.gpxrm' );
		const mapEl = next?.querySelector< HTMLElement >( '.gpxrm-map' );
		const current = mapRef.current;

		if ( ! next || ! mapEl ) {
			// The notice leaves the host before the map is torn down, as a
			// replaced map does.
			host.replaceChildren( content );
			if ( current ) {
				mapRef.current = null;
				current.controller.abort();
				releaseSlot( current.doc );
			}
			return;
		}

		if ( current && ! needsRebuild( current.preview, next ) ) {
			patchPreview( current.preview, next );
			return;
		}
		if ( ! current && ! claimSlot( doc ) ) {
			setSlotWait( doc );
			return;
		}

		// Marked so the front end's own boot in this document leaves it alone,
		// and so it is built as a preview.
		mapEl.dataset.gpxrmBooted = '1';
		mapEl.dataset.gpxrmPreview = '';
		// The old map leaves the host before it is torn down, so the context
		// it loses is not taken for the new map's.
		host.replaceChildren( next );
		current?.controller.abort();

		const controller = new win.AbortController();
		mapRef.current = { preview: next, controller, doc };
		loadViewModule( doc, src ).then(
			() => {
				if ( ! controller.signal.aborted ) {
					requestPreview( mapEl, controller.signal );
				}
			},
			() => setFailed( true )
		);
	}, [ rendered, visible, showMap, src ] );

	if ( ! showMap ) {
		return <>{ fallback }</>;
	}

	return (
		<div className="gpxrm-preview">
			<div ref={ hostRef } className="gpxrm-preview-host" />
			{ ! isSelected && (
				<div className="gpxrm-preview-shield" aria-hidden="true" />
			) }
		</div>
	);
}
