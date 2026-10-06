/**
 * How far a map zooms: as far as its tile provider has tiles, and no further.
 *
 * The raster source learns the provider's last tile level, so the map draws
 * those tiles larger rather than asking for ones that do not exist. The map
 * itself stops one level earlier: its 256 pixel tiles show at their own size
 * one map zoom below their tile level, so further in would only enlarge them.
 */

import { describe, expect, it } from 'vitest';

import { buildRasterStyle, createMap } from '../../src/view/map-core';
import type { MapLibreGl } from '../../src/view/types';

const URL = 'https://tile.opentopomap.org/{z}/{x}/{y}.png';

/**
 * Build a map with a fake library and return the options it was given.
 *
 * @param tileMaxZoom The provider's last tile level, if known.
 */
function mapOptionsFor( tileMaxZoom?: number ): Record< string, unknown > {
	let options: Record< string, unknown > = {};
	const control = class {};
	const maplibregl = {
		Map: class {
			constructor( given: Record< string, unknown > ) {
				options = given;
			}
			addControl() {
				return this;
			}
		},
		NavigationControl: control,
		ScaleControl: control,
		FullscreenControl: control,
		GeolocateControl: control,
	} as unknown as MapLibreGl;

	createMap( {
		maplibregl,
		container: document.createElement( 'div' ),
		style: buildRasterStyle( URL, '', tileMaxZoom ),
		bounds: [
			[ 0, 0 ],
			[ 1, 1 ],
		],
		maxZoom: 15,
		tileMaxZoom,
	} );
	return options;
}

describe( 'buildRasterStyle', () => {
	it( 'tells the source where the provider’s tiles stop', () => {
		expect( buildRasterStyle( URL, '', 17 ).sources.osm ).toMatchObject( {
			maxzoom: 17,
			tileSize: 256,
		} );
	} );

	it( 'leaves the source as MapLibre has it when that is not known', () => {
		expect( buildRasterStyle( URL, '' ).sources.osm ).not.toHaveProperty(
			'maxzoom'
		);
	} );
} );

describe( 'createMap', () => {
	it( 'stops visitors where the tiles stop, keeping the starting view', () => {
		const options = mapOptionsFor( 17 );

		expect( options.maxZoom ).toBe( 16 );
		expect( options.fitBoundsOptions ).toMatchObject( { maxZoom: 15 } );
	} );

	it( 'leaves the zoom open when the last tile level is not known', () => {
		expect( mapOptionsFor() ).not.toHaveProperty( 'maxZoom' );
	} );
} );
