/**
 * Load MapLibre GL, for the front end and for the Waypoint screen's map.
 */

import type { MapLibreGl } from './types';

// MapLibre 6 is ESM-only and locates its worker from `import.meta.url` with a
// computed specifier, which the bundler cannot follow. After bundling that
// resolves next to our own chunk, where no worker exists, and the map then
// hangs with the base tiles drawn but no track. Vite emits the worker as its
// own asset and hands us the URL, which we pass to MapLibre explicitly.
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';

let maplibrePromise: Promise< MapLibreGl > | null = null;

/**
 * Load MapLibre GL (and its CSS) once, on demand.
 */
export function loadMapLibre(): Promise< MapLibreGl > {
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
			const lib = ns.default || ns;
			lib.setWorkerUrl( workerUrl );
			return lib;
		} )
		.catch( ( err ) => {
			maplibrePromise = null;
			throw err;
		} );
	maplibrePromise = loading;
	return loading;
}
