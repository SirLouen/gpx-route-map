/**
 * The block's tile provider select, driven through the real Edit component.
 *
 * Only WordPress's UI and data stores are stood in for: what the select
 * offers comes from what the server printed for the editor.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createElement, createRoot, flushSync } from '@wordpress/element';

/** The select controls rendered, by label. */
const selects = new Map<
	string,
	{
		value: string;
		help?: string;
		options: Array< { label: string; value: string; disabled?: boolean } >;
		onChange: ( value: string ) => void;
	}
>();

vi.mock( '@wordpress/data', () => ( {
	useSelect: ( pick: ( select: () => unknown ) => unknown ) =>
		pick( () => ( { getEntityRecord: () => undefined } ) ),
} ) );

vi.mock( '@wordpress/block-editor', () => {
	const pass = ( { children }: { children?: unknown } ) => children ?? null;
	return {
		useBlockProps: () => ( {} ),
		InspectorControls: pass,
		BlockControls: pass,
		MediaUploadCheck: pass,
		MediaUpload: () => null,
	};
} );

vi.mock( '@wordpress/components', () => {
	const pass = ( { children }: { children?: unknown } ) => children ?? null;
	const none = () => null;
	return {
		PanelBody: pass,
		RangeControl: none,
		TextControl: none,
		Button: none,
		Placeholder: none,
		ToolbarGroup: pass,
		ToolbarButton: none,
		ExternalLink: none,
		SelectControl: ( props: { label: string } ) => {
			selects.set( props.label, props as never );
			return null;
		},
		ToggleControl: none,
		CheckboxControl: none,
	};
} );

vi.mock( '../../src/editor/map-preview', () => ( { MapPreview: () => null } ) );

import Edit from '../../src/edit';

const OSM = { id: 'osm', label: 'OpenStreetMap', usable: true };
const OTM = { id: 'opentopomap', label: 'OpenTopoMap', usable: true };
const CYCLE = {
	id: 'thunderforest-cycle',
	label: 'Thunderforest: OpenCycleMap (cycling)',
	usable: false,
};

let writes: Array< Record< string, unknown > >;
let root: ReturnType< typeof createRoot >;

/**
 * Open a block with the given tile provider, the server having printed the
 * given providers.
 *
 * @param provider      The block's stored provider.
 * @param tileProviders The providers the server printed.
 */
function open( provider: string, tileProviders: object[] ) {
	( window as unknown as { gpxrmDefaults?: object } ).gpxrmDefaults = {
		tileProviders,
		tileProvider: 'opentopomap',
	};
	flushSync( () =>
		root.render(
			createElement( Edit as never, {
				attributes: {
					gpxUrl: '',
					height: 0,
					heightTablet: 0,
					heightMobile: 0,
					showStats: '',
					showElevation: '',
					showDownload: '',
					statFields: '',
					maxZoom: 0,
					provider,
					units: '',
				},
				isSelected: true,
				setAttributes: ( next: Record< string, unknown > ) =>
					writes.push( next ),
			} )
		)
	);
	const select = selects.get( 'Tile provider' );
	if ( ! select ) {
		throw new Error( 'no tile provider select' );
	}
	return select;
}

beforeEach( () => {
	writes = [];
	selects.clear();
	const container = document.createElement( 'div' );
	document.body.append( container );
	root = createRoot( container );
} );

afterEach( () => {
	flushSync( () => root.unmount() );
	delete ( window as unknown as { gpxrmDefaults?: object } ).gpxrmDefaults;
} );

describe( 'The tile provider select', () => {
	it( 'offers the site default by name, then what the map can use', () => {
		const select = open( '', [ OSM, OTM, CYCLE ] );

		expect( select.value ).toBe( '' );
		expect( select.options ).toEqual( [
			{ label: 'Site default — OpenTopoMap', value: '' },
			{ label: 'OpenStreetMap', value: 'osm' },
			{ label: 'OpenTopoMap', value: 'opentopomap' },
		] );
	} );

	it( 'says where the missing providers come from, only when some are missing', () => {
		expect( open( '', [ OSM, OTM, CYCLE ] ).help ).toContain( 'API key' );
		expect(
			open( '', [ OSM, OTM, { ...CYCLE, usable: true } ] ).help
		).toBeUndefined();
	} );

	it( 'shows a choice that lost its key, disabled', () => {
		const select = open( 'thunderforest-cycle', [ OSM, OTM, CYCLE ] );

		expect( select.value ).toBe( 'thunderforest-cycle' );
		expect( select.options.at( -1 ) ).toEqual( {
			label: 'Thunderforest: OpenCycleMap (cycling) — needs an API key',
			value: 'thunderforest-cycle',
			disabled: true,
		} );
	} );

	// The map keeps its choice, even one it cannot use now or one from
	// elsewhere: opening the post must not change it.
	it.each( [ '', 'osm', 'thunderforest-cycle', 'mapbox', 'default' ] )(
		'changes nothing when a block set to "%s" is merely opened',
		( stored ) => {
			open( stored, [ OSM, OTM, CYCLE ] );

			expect( writes ).toEqual( [] );
		}
	);

	it( 'stores the provider chosen', () => {
		open( '', [ OSM, OTM ] ).onChange( 'opentopomap' );

		expect( writes ).toContainEqual( { provider: 'opentopomap' } );
	} );
} );
