/**
 * The tile providers a map can choose from, as the editor sees them.
 */

import { afterEach, describe, expect, it } from 'vitest';

import { providerChoices, readTileProviders } from '../../src/tile-providers';

const OSM = { id: 'osm', label: 'OpenStreetMap', usable: true };
const OTM = { id: 'opentopomap', label: 'OpenTopoMap', usable: true };
const CYCLE = {
	id: 'thunderforest-cycle',
	label: 'Thunderforest: OpenCycleMap (cycling)',
	usable: false,
};

/**
 * Stand in for what the server prints before the editor script.
 *
 * @param tileProviders The providers.
 * @param tileProvider  The site's.
 */
const inject = ( tileProviders: unknown, tileProvider: unknown ) => {
	( window as unknown as { gpxrmDefaults?: object } ).gpxrmDefaults = {
		tileProviders,
		tileProvider,
	};
};

afterEach( () => {
	delete ( window as unknown as { gpxrmDefaults?: object } ).gpxrmDefaults;
} );

describe( 'readTileProviders', () => {
	it( 'takes what the server printed', () => {
		inject( [ OSM, OTM, CYCLE ], 'opentopomap' );

		expect( readTileProviders() ).toEqual( {
			providers: [ OSM, OTM, CYCLE ],
			site: 'opentopomap',
		} );
	} );

	it.each( [
		[ 'nothing printed', undefined, undefined ],
		[ 'not a list', 'osm', 'osm' ],
		[ 'entries that are not providers', [ { id: 'osm' }, 42 ], 'osm' ],
		[
			'entries that do not say whether they can be used',
			[ { id: 'osm', label: 'OpenStreetMap' } ],
			'osm',
		],
	] )(
		'offers the providers that need no key for %s',
		( _label, providers, site ) => {
			inject( providers, site );

			expect( readTileProviders() ).toEqual( {
				providers: [ OSM, OTM ],
				site: 'osm',
			} );
		}
	);

	it( 'takes the site as OpenStreetMap when it names no listed provider', () => {
		inject( [ OSM, OTM ], 'mapbox' );

		expect( readTileProviders().site ).toBe( 'osm' );
	} );
} );

describe( 'providerChoices', () => {
	const data = { providers: [ OSM, OTM, CYCLE ], site: 'opentopomap' };

	it( 'offers the providers a map can use, after the site default', () => {
		expect( providerChoices( '', data ) ).toEqual( {
			site: OTM,
			usable: [ OSM, OTM ],
			locked: undefined,
			value: '',
		} );
	} );

	it( 'keeps a usable choice selected', () => {
		expect( providerChoices( 'osm', data ) ).toMatchObject( {
			locked: undefined,
			value: 'osm',
		} );
	} );

	// Its maps show the site's provider meanwhile, and the author should see
	// why rather than find the choice gone.
	it( 'still shows a choice that lost its key, apart', () => {
		expect( providerChoices( 'thunderforest-cycle', data ) ).toMatchObject(
			{
				locked: CYCLE,
				value: 'thunderforest-cycle',
			}
		);
	} );

	it( 'shows anything else as the site default, as maps treat it', () => {
		expect( providerChoices( 'mapbox', data ) ).toMatchObject( {
			locked: undefined,
			value: '',
		} );
	} );
} );
