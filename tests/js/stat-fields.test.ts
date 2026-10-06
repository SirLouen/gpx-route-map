/**
 * The figures a map shows when it follows the site, as the editor sees them.
 */

import { afterEach, describe, expect, it } from 'vitest';

import { DEFAULT_STAT_KEYS, siteStatFields } from '../../src/stat-fields';

const setInjected = ( statFields: unknown ) => {
	( window as unknown as { gpxrmDefaults?: object } ).gpxrmDefaults = {
		statFields,
	};
};

afterEach( () => {
	delete ( window as unknown as { gpxrmDefaults?: object } ).gpxrmDefaults;
} );

describe( 'siteStatFields', () => {
	it( 'takes the list the server printed, in display order', () => {
		setInjected( [ 'waypoints', 'min', 'distance' ] );
		expect( siteStatFields() ).toEqual( [
			'distance',
			'min',
			'waypoints',
		] );
	} );

	it.each( [
		[ 'nothing printed', undefined ],
		[ 'not a list', 'distance,min' ],
		[ 'no known key', [ 'bogus' ] ],
		[ 'an empty list', [] ],
	] )( 'falls back to the defaults for %s', ( _label, value ) => {
		setInjected( value );
		expect( siteStatFields() ).toEqual( DEFAULT_STAT_KEYS );
	} );

	it( 'leaves the min elevation out of the defaults', () => {
		expect( DEFAULT_STAT_KEYS ).not.toContain( 'min' );
	} );
} );
