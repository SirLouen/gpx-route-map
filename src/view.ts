/**
 * Frontend entry point.
 */

import { initInstance, viewMessages } from './view/map-instance';
import { listenForPreviews } from './view/preview';
import { loadMapLibre } from './view/load-maplibre';

/**
 * Boot one map element: load the library, then initialize the instance.
 *
 * @param mapEl Map element.
 */
function boot( mapEl: HTMLElement ): void {
	if ( mapEl.dataset.gpxrmBooted ) {
		return;
	}
	mapEl.dataset.gpxrmBooted = '1';
	loadMapLibre()
		.then( ( maplibregl ) => initInstance( mapEl, maplibregl ) )
		.catch( () => {
			delete mapEl.dataset.gpxrmBooted;
			// Built as a node with textContent rather than an innerHTML
			// string: the message is translated server-side, and a translation
			// must never be able to inject markup.
			const error = document.createElement( 'div' );
			error.className = 'gpxrm-error';
			error.textContent = viewMessages( mapEl ).maplibre;
			mapEl.replaceChildren( error );
			mapEl.addEventListener( 'click', () => boot( mapEl ), {
				once: true,
			} );
		} );
}

/**
 * Observe all maps and boot them as they approach the viewport.
 */
function setup(): void {
	const maps: HTMLElement[] = Array.prototype.slice.call(
		document.querySelectorAll( '.gpxrm-map[data-gpxrm-gpx]' )
	);
	if ( ! maps.length ) {
		return;
	}

	if ( ! ( 'IntersectionObserver' in window ) ) {
		maps.forEach( boot );
		return;
	}

	const observer = new window.IntersectionObserver(
		( entries, obs ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					obs.unobserve( entry.target );
					boot( entry.target as HTMLElement );
				}
			} );
		},
		{ rootMargin: '200px' }
	);

	maps.forEach( ( el ) => observer.observe( el ) );
}

// The block editor loads this module into its canvas for the live preview.
listenForPreviews( document, loadMapLibre, initInstance );

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', setup );
} else {
	setup();
}
