/**
 * Display units for the stats bar and the elevation profile.
 *
 * Track statistics are always computed and stored in kilometres and metres.
 * The server owns the conversion: it resolves the site setting and the
 * per-map override, then passes the factors and the translated labels down on
 * the map element as `data-gpxrm-units`. Nothing here defines a conversion of
 * its own, so there is only ever one definition of "a mile".
 */

export interface UnitConfig {
	distFactor: number;
	distLabel: string;
	eleFactor: number;
	eleLabel: string;
}

/** Used when the attribute is missing or malformed. */
export const METRIC: UnitConfig = {
	distFactor: 1,
	distLabel: 'km',
	eleFactor: 1,
	eleLabel: 'm',
};

/**
 * Read the unit configuration the server attached to a map element.
 *
 * @param mapEl The `.gpxrm-map` element.
 */
export function readUnits( mapEl: HTMLElement ): UnitConfig {
	try {
		const raw = mapEl.dataset.gpxrmUnits;
		if ( raw ) {
			const parsed = JSON.parse( raw ) as Partial< UnitConfig >;
			return {
				distFactor:
					Number.isFinite( parsed.distFactor ) &&
					Number( parsed.distFactor ) > 0
						? Number( parsed.distFactor )
						: METRIC.distFactor,
				distLabel: parsed.distLabel || METRIC.distLabel,
				eleFactor:
					Number.isFinite( parsed.eleFactor ) &&
					Number( parsed.eleFactor ) > 0
						? Number( parsed.eleFactor )
						: METRIC.eleFactor,
				eleLabel: parsed.eleLabel || METRIC.eleLabel,
			};
		}
	} catch {
		// Fall through to metric.
	}
	return METRIC;
}

/**
 * Format a distance for display.
 *
 * @param km    Distance in kilometres.
 * @param units Unit configuration.
 */
export function formatDistance( km: number, units: UnitConfig ): string {
	return `${ ( km * units.distFactor ).toFixed( 2 ) } ${ units.distLabel }`;
}

/**
 * Format an elevation for display.
 *
 * @param metres Elevation in metres.
 * @param units  Unit configuration.
 */
export function formatElevation( metres: number, units: UnitConfig ): string {
	return `${ Math.round( metres * units.eleFactor ).toLocaleString() } ${
		units.eleLabel
	}`;
}
