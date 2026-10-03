/**
 * MapLibre's own interface strings in the site's language.
 *
 * MapLibre writes its button tooltips, screen reader names and scale bar units
 * in English, and takes replacements through the Map `locale` option, keyed by
 * its own string IDs ("NavigationControl.ZoomIn"). The server translates them
 * and passes them down on the map element as `data-gpxrm-map-ui`.
 */

/**
 * Read the translated MapLibre strings the server attached to a map element.
 *
 * Returns only non-empty strings, so a bad entry leaves that one string in
 * English rather than breaking the map: MapLibre throws, and the map never
 * builds, if a string it asks for is null, and an empty one would leave a
 * button with no name.
 *
 * @param mapEl The `.gpxrm-map` element.
 * @return The `locale` option for the MapLibre map; empty means English.
 */
export function readMapUi( mapEl: HTMLElement ): Record< string, string > {
	let parsed: unknown;
	try {
		parsed = JSON.parse( mapEl.dataset.gpxrmMapUi || '{}' );
	} catch {
		return {};
	}
	if ( ! parsed || 'object' !== typeof parsed || Array.isArray( parsed ) ) {
		return {};
	}

	const strings: Record< string, string > = {};
	for ( const [ key, value ] of Object.entries( parsed ) ) {
		if ( 'string' === typeof value && '' !== value ) {
			strings[ key ] = value;
		}
	}
	return strings;
}
