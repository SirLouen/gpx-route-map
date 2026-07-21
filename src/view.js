/**
 * Frontend entry point.
 */

import { initInstance } from './view/map-instance';

let maplibrePromise = null;

/**
 * Load MapLibre GL (and its CSS) once, on demand.
 *
 * @return {Promise<Object>} The MapLibre GL module default export.
 */
function loadMapLibre() {
	if ( ! maplibrePromise ) {
		maplibrePromise = Promise.all( [
			import( 'maplibre-gl' ),
			import( 'maplibre-gl/dist/maplibre-gl.css' ),
		] )
			.then( ( [ mod ] ) => mod.default || mod )
			.catch( ( err ) => {
				maplibrePromise = null;
				throw err;
			} );
	}
	return maplibrePromise;
}

/**
 * Boot one map element: load the library, then initialize the instance.
 *
 * @param {HTMLElement} mapEl Map element.
 * @return {void}
 */
function boot( mapEl ) {
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
 *
 * @return {void}
 */
function setup() {
	const maps = Array.prototype.slice.call(
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
					boot( entry.target );
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
