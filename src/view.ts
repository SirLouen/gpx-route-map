/**
 * Frontend entry point.
 */

import { initInstance } from './view/map-instance';
import type { MapLibreGl } from './view/types';

let maplibrePromise: Promise< MapLibreGl > | null = null;

/**
 * Load MapLibre GL (and its CSS) once, on demand.
 */
function loadMapLibre(): Promise< MapLibreGl > {
	if ( maplibrePromise ) {
		return maplibrePromise;
	}
	const loading = Promise.all( [
		import( 'maplibre-gl' ),
		import( 'maplibre-gl/dist/maplibre-gl.css' ),
	] )
		.then( ( [ mod ] ) => {
			const ns = mod as unknown as MapLibreGl & {
				default?: MapLibreGl;
			};
			return ns.default || ns;
		} )
		.catch( ( err ) => {
			maplibrePromise = null;
			throw err;
		} );
	maplibrePromise = loading;
	return loading;
}

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
			mapEl.innerHTML =
				'<div class="gpxrm-error">Map failed to load. Click to retry.</div>';
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

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', setup );
} else {
	setup();
}
