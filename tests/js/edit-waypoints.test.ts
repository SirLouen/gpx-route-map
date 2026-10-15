/**
 * The block's Waypoints panel, driven through the real Edit component: which
 * waypoints it lists, the links it stores, and when it drops them.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	createElement,
	createRoot,
	flushSync,
	useState,
} from '@wordpress/element';

interface Select {
	label: string;
	value: string;
	options: Array< { label: string; value: string } >;
	onChange: ( value: string ) => void;
}

/** Select controls as last rendered, by label. */
const selects = new Map< string, Select >();
/** Text fields, by label. */
const fields = new Map<
	string,
	{ onChange: ( value: string ) => void; onBlur: () => void }
>();
/** Waypoint posts the site has, or null while they load. */
let posts: Array< {
	id: number;
	status: string;
	title: { raw: string };
} > | null;

vi.mock( '@wordpress/data', () => ( {
	useSelect: ( pick: ( select: () => unknown ) => unknown ) =>
		pick( () => ( {
			getEntityRecord: () => undefined,
			getEntityRecords: () => posts,
		} ) ),
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
	const select = ( props: Select ) => {
		selects.set( props.label, props );
		return null;
	};
	const text = ( props: {
		label: string;
		onChange: ( value: string ) => void;
		onBlur: () => void;
	} ) => {
		fields.set( props.label, props );
		return null;
	};
	return {
		PanelBody: pass,
		RangeControl: none,
		TextControl: text,
		Button: none,
		Placeholder: none,
		ToolbarGroup: pass,
		ToolbarButton: none,
		ExternalLink: none,
		SelectControl: select,
		ToggleControl: none,
		CheckboxControl: none,
		Notice: none,
	};
} );

vi.mock( '../../src/editor/map-preview', () => ( { MapPreview: () => null } ) );

import Edit from '../../src/edit';
import { withLink } from '../../src/editor/waypoint-links';
import { bakeStats } from '../../src/baked-stats';
import { parseGPX } from '../../src/view/map-core';

const GPX =
	'<gpx xmlns:gpxrm="https://wordpress.org/plugins/gpx-route-map/">' +
	'<wpt lat="43.0" lon="-6.0"><name>Fountain</name></wpt>' +
	'<wpt lat="43.1" lon="-6.0"><name>Refuge</name><extensions>' +
	'<gpxrm:waypoint>11</gpxrm:waypoint></extensions></wpt>' +
	'<trk><trkseg>' +
	'<trkpt lat="43.0" lon="-6.0"><ele>150</ele></trkpt>' +
	'<trkpt lat="43.01" lon="-6.0"><ele>100</ele></trkpt>' +
	'</trkseg></trk></gpx>';

const ATTRIBUTES = {
	gpxUrl: 'https://example.test/a.gpx',
	height: 0,
	heightTablet: 0,
	heightMobile: 0,
	showStats: '',
	showElevation: '',
	showDownload: '',
	statFields: '',
	maxZoom: 0,
	provider: '',
	units: '',
	// As the editor stores them, so reading the file changes nothing.
	stats: bakeStats( parseGPX( GPX ) ),
};

/** Every attribute change the editor made. */
let writes: Array< Record< string, unknown > >;
let root: ReturnType< typeof createRoot >;

/**
 * The block, holding its attributes as the editor would.
 *
 * @param props         Props.
 * @param props.initial Its attributes when the post is opened.
 */
function Block( { initial }: { initial: Record< string, unknown > } ) {
	const [ attributes, setAttributes ] = useState( initial );
	return createElement( Edit as never, {
		attributes,
		isSelected: true,
		setAttributes: ( next: Record< string, unknown > ) => {
			writes.push( next );
			setAttributes( ( current ) => ( { ...current, ...next } ) );
		},
	} );
}

/**
 * Open the post and let the file be read.
 *
 * @param changes Attributes that differ from ATTRIBUTES.
 */
async function open( changes: Record< string, unknown > = {} ) {
	flushSync( () =>
		root.render(
			createElement( Block, { initial: { ...ATTRIBUTES, ...changes } } )
		)
	);
	await settle();
}

/** Let downloads and React catch up. */
const settle = async () => {
	for ( let i = 0; i < 5; i++ ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}
	flushSync( () => {} );
};

/**
 * Pick an option of a select control.
 *
 * @param label Its label.
 * @param value The option's value.
 */
const choose = ( label: string, value: string ) =>
	flushSync( () => selects.get( label )?.onChange( value ) );

/** The waypoints the panel lists, by label. */
const listed = () =>
	Array.from( selects.keys() ).filter( ( label ) => /^\d+\. /.test( label ) );

/**
 * Turn Waypoint posts on or off, as the server tells the editor.
 *
 * @param on Whether they are on.
 */
const feature = ( on: boolean ) => {
	( window as unknown as { gpxrmDefaults?: object } ).gpxrmDefaults = {
		waypoints: on,
	};
};

beforeEach( () => {
	writes = [];
	selects.clear();
	fields.clear();
	posts = [
		{ id: 10, status: 'publish', title: { raw: 'Fountain post' } },
		{ id: 11, status: 'publish', title: { raw: 'Refuge post' } },
		{ id: 12, status: 'draft', title: { raw: '' } },
	];
	feature( true );
	vi.stubGlobal(
		'fetch',
		vi.fn( () =>
			Promise.resolve( {
				ok: true,
				status: 200,
				text: () => Promise.resolve( GPX ),
			} as Response )
		)
	);
	const container = document.createElement( 'div' );
	document.body.append( container );
	root = createRoot( container );
} );

afterEach( () => {
	flushSync( () => root.unmount() );
	vi.unstubAllGlobals();
	delete ( window as unknown as { gpxrmDefaults?: object } ).gpxrmDefaults;
} );

describe( 'The Waypoints panel', () => {
	it( 'is not there while Waypoint posts are off', async () => {
		feature( false );
		await open();
		expect( listed() ).toEqual( [] );
	} );

	it( 'lists the waypoints of the file, by their place in it', async () => {
		await open();
		expect( listed() ).toEqual( [ '1. Fountain', '2. Refuge' ] );
	} );

	it( 'offers every Waypoint post, and says what the file links', async () => {
		await open();
		const fountain = selects.get( '1. Fountain' );
		expect( fountain?.value ).toBe( '' );
		expect( fountain?.options ).toEqual( [
			{ label: 'None', value: '' },
			{ label: 'Fountain post', value: '10' },
			{ label: 'Refuge post', value: '11' },
			{ label: '#12 (no title) (not published)', value: '12' },
		] );
		expect( selects.get( '2. Refuge' )?.options[ 0 ] ).toEqual( {
			label: 'As in the GPX file: Refuge post',
			value: '',
		} );
	} );

	it( 'stores nothing when a post is merely opened', async () => {
		await open( { waypointPosts: { 0: 10 } } );
		expect( writes ).toEqual( [] );
	} );

	it( 'stores the post chosen for a waypoint, and drops it on None', async () => {
		await open();
		choose( '2. Refuge', '10' );
		expect( writes.at( -1 ) ).toEqual( { waypointPosts: { 1: 10 } } );
		expect( selects.get( '2. Refuge' )?.value ).toBe( '10' );

		choose( '2. Refuge', '' );
		expect( writes.at( -1 ) ).toEqual( { waypointPosts: undefined } );
	} );

	it( 'keeps a link to a post that is gone, so it can be seen', async () => {
		await open( { waypointPosts: { 0: 99 } } );
		const fountain = selects.get( '1. Fountain' );
		expect( fountain?.value ).toBe( '99' );
		expect( fountain?.options.at( -1 ) ).toEqual( {
			label: '#99 (not found)',
			value: '99',
		} );
	} );

	it( 'drops the links when the block shows another file', async () => {
		await open( { waypointPosts: { 0: 10 } } );
		flushSync( () =>
			fields
				.get( 'or GPX file URL' )
				?.onChange( 'https://example.test/b.gpx' )
		);
		flushSync( () => fields.get( 'or GPX file URL' )?.onBlur() );
		expect( writes.at( -1 ) ).toEqual( {
			gpxUrl: 'https://example.test/b.gpx',
			gpxId: undefined,
			waypointPosts: undefined,
		} );
	} );

	it( 'writes no links when there were none to drop', async () => {
		await open();
		flushSync( () =>
			fields
				.get( 'or GPX file URL' )
				?.onChange( 'https://example.test/b.gpx' )
		);
		flushSync( () => fields.get( 'or GPX file URL' )?.onBlur() );
		expect( writes.at( -1 ) ).toEqual( {
			gpxUrl: 'https://example.test/b.gpx',
			gpxId: undefined,
		} );
	} );
} );

describe( 'withLink', () => {
	it( 'adds, replaces and removes one link', () => {
		expect( withLink( undefined, 2, 5 ) ).toEqual( { 2: 5 } );
		expect( withLink( { 2: 5 }, 2, 6 ) ).toEqual( { 2: 6 } );
		expect( withLink( { 2: 5, 3: 7 }, 2, 0 ) ).toEqual( { 3: 7 } );
	} );

	it( 'goes back to unset once no link is left', () => {
		expect( withLink( { 2: 5 }, 2, 0 ) ).toBeUndefined();
		expect( withLink( undefined, 2, 0 ) ).toBeUndefined();
	} );

	it( 'leaves the links it was given as they were', () => {
		const links = { 2: 5 };
		withLink( links, 3, 6 );
		expect( links ).toEqual( { 2: 5 } );
	} );
} );
