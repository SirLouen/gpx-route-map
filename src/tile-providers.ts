/**
 * The tile providers a map can choose from, as the editor sees them.
 *
 * Free of WordPress imports so it can be unit tested directly.
 */

/** A provider, as the server describes it to the editor. */
export interface TileProvider {
	id: string;
	label: string;
	/** Whether maps can use it: false for a Thunderforest style without its key. */
	usable: boolean;
}

/** What the server says about providers. */
export interface TileProviders {
	providers: TileProvider[];
	/** The provider maps following the site use. */
	site: string;
}

/** Used when the server said nothing usable: the providers that need no key. */
const FALLBACK: TileProviders = {
	providers: [
		{ id: 'osm', label: 'OpenStreetMap', usable: true },
		{ id: 'opentopomap', label: 'OpenTopoMap', usable: true },
	],
	site: 'osm',
};

/**
 * Whether a value from the server describes a provider.
 *
 * @param value Value to check.
 */
function isProvider( value: unknown ): value is TileProvider {
	const p = value as Partial< TileProvider > | null;
	return (
		!! p &&
		'string' === typeof p.id &&
		'string' === typeof p.label &&
		'boolean' === typeof p.usable
	);
}

/**
 * The providers, printed by the server before the editor script.
 *
 * Not read from /wp/v2/settings: core gates that route on manage_options, so
 * an Editor or Author would be shown nothing, and it holds the setting as
 * stored, which may still be a 1.x value.
 */
export function readTileProviders(): TileProviders {
	const injected = (
		window as unknown as {
			gpxrmDefaults?: { tileProviders?: unknown; tileProvider?: unknown };
		}
	 ).gpxrmDefaults;
	const providers = Array.isArray( injected?.tileProviders )
		? injected.tileProviders.filter( isProvider )
		: [];
	if ( ! providers.length ) {
		return FALLBACK;
	}
	const site = providers.some( ( p ) => p.id === injected?.tileProvider )
		? ( injected?.tileProvider as string )
		: 'osm';
	return { providers, site };
}

/**
 * What a map's provider select offers.
 *
 * @param stored The map's stored provider; '' follows the site.
 * @param data   The providers.
 * @return The site's provider, for "Site default"; those the map can use; a
 *         stored one it cannot use, shown apart; and the value to select. A
 *         stored value that names no provider selects "Site default", which
 *         is what maps do with it.
 */
export function providerChoices(
	stored: string,
	data: TileProviders
): {
	site: TileProvider;
	usable: TileProvider[];
	locked: TileProvider | undefined;
	value: string;
} {
	const site =
		data.providers.find( ( p ) => p.id === data.site ) ??
		FALLBACK.providers[ 0 ];
	const usable = data.providers.filter( ( p ) => p.usable );
	const chosen = data.providers.find( ( p ) => p.id === stored );
	const locked = chosen && ! chosen.usable ? chosen : undefined;
	return { site, usable, locked, value: chosen ? stored : '' };
}
