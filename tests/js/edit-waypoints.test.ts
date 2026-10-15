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
	title: { rendered: string };
} > | null;
/** Media Library files, by ID. */
let media: Record< number, string >;
/** The queries the panel listed posts with. */
let queries: unknown[][];
/** The listings the panel asked to have read again. */
let refreshed: unknown[][];

vi.mock( '@wordpress/data', () => ( {
	useSelect: ( pick: ( select: () => unknown ) => unknown ) =>
		pick( () => ( {
			getEntityRecord: ( _kind: string, _name: string, id: number ) =>
				media[ id ] ? { source_url: media[ id ] } : undefined,
			getEntityRecords: ( ...args: unknown[] ) => {
				queries.push( args );
				return posts;
			},
		} ) ),
	useDispatch: () => ( {
		invalidateResolution: ( selector: string, args: unknown[] ) =>
			refreshed.push( [ selector, ...args ] ),
	} ),
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

/** The Waypoints panel's open/close handler. */
let togglePanel: ( ( opened: boolean ) => void ) | undefined;

vi.mock( '@wordpress/components', () => {
	const pass = ( { children }: { children?: unknown } ) => children ?? null;
	const panel = ( props: {
		title: string;
		children?: unknown;
		onToggle?: ( opened: boolean ) => void;
	} ) => {
		if ( 'Waypoints' === props.title ) {
			togglePanel = props.onToggle;
		}
		return props.children ?? null;
	};
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
		PanelBody: panel,
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
import { readLinks, withLink } from '../../src/editor/waypoint-links';
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
let container: HTMLElement;

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
	media = {};
	queries = [];
	refreshed = [];
	togglePanel = undefined;
	posts = [
		{ id: 10, status: 'publish', title: { rendered: 'Fountain post' } },
		{ id: 11, status: 'publish', title: { rendered: 'Refuge post' } },
		{ id: 12, status: 'draft', title: { rendered: '' } },
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
	container = document.createElement( 'div' );
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
	} );

	// The edit context would list only the posts the user may edit, which
	// for an Author leaves out every waypoint the site made.
	it( 'lists the posts the user may read, not only edit', async () => {
		await open();
		const [ , , query ] = queries.at( -1 ) ?? [];
		expect( queries.at( -1 )?.slice( 0, 2 ) ).toEqual( [
			'postType',
			'gpxrm_waypoint',
		] );
		expect( query ).toHaveProperty( 'context', 'view' );
	} );

	it( 'names posts as the map does, entities decoded and tags dropped', async () => {
		posts = [
			{
				id: 10,
				status: 'publish',
				title: {
					rendered:
						'Fish &amp; Chips <em>stall</em> &#8220;No.1&#8221;',
				},
			},
			{
				id: 11,
				status: 'publish',
				title: { rendered: '<img src=x onerror="window.hacked=1">' },
			},
		];
		await open();
		expect(
			selects.get( '1. Fountain' )?.options.map( ( { label } ) => label )
		).toEqual( [ 'None', 'Fish & Chips stall “No.1”', '#11 (no title)' ] );
		expect(
			( window as unknown as { hacked?: number } ).hacked
		).toBeUndefined();
	} );

	it( 'lists again what was added in another tab, while it is open', async () => {
		await open();
		refreshed = [];
		Object.defineProperty( document, 'visibilityState', {
			configurable: true,
			get: () => 'visible',
		} );
		const comeBack = () =>
			document.dispatchEvent( new Event( 'visibilitychange' ) );

		// Closed, it lists nothing, so there is nothing to read again.
		comeBack();
		expect( refreshed ).toHaveLength( 0 );

		flushSync( () => togglePanel?.( true ) );
		await settle();
		comeBack();
		expect( refreshed ).toHaveLength( 2 );
		expect( refreshed[ 0 ] ).toEqual( [
			'getEntityRecords',
			...( queries.at( -1 ) ?? [] ),
		] );

		flushSync( () => togglePanel?.( false ) );
		await settle();
		comeBack();
		delete ( document as unknown as { visibilityState?: string } )
			.visibilityState;
		expect( refreshed ).toHaveLength( 2 );
	} );

	// Maps read the links in a file only from the Media Library.
	it( 'says a file elsewhere links its waypoints in vain', async () => {
		await open();
		expect( selects.get( '2. Refuge' )?.options[ 0 ].label ).toBe( 'None' );
		expect( container.textContent ).toContain(
			'maps only read those links from files in the Media Library'
		);
	} );

	it( 'says what the file links when it is in the Media Library', async () => {
		media[ 7 ] = 'https://example.test/a.gpx';
		await open( { gpxId: 7 } );
		expect( selects.get( '1. Fountain' )?.options[ 0 ].label ).toBe(
			'None'
		);
		expect( selects.get( '2. Refuge' )?.options[ 0 ].label ).toBe(
			'As in the GPX file: Refuge post'
		);
		expect( container.textContent ).not.toContain( 'Media Library' );
	} );

	// Its PHP lacks XMLReader, so the server reads no links from any file.
	it( 'says when the site cannot read what a file links', async () => {
		( window as unknown as { gpxrmDefaults?: object } ).gpxrmDefaults = {
			waypoints: true,
			waypointFiles: false,
		};
		media[ 7 ] = 'https://example.test/a.gpx';
		await open( { gpxId: 7 } );
		expect( selects.get( '2. Refuge' )?.options[ 0 ].label ).toBe( 'None' );
		expect( container.textContent ).toContain(
			'its PHP lacks the XMLReader extension'
		);
		expect( container.textContent ).not.toContain( 'Media Library' );
	} );

	// Saved by a user without unfiltered_html, PHP writes {"0":10,"1":11}
	// as a list.
	it( 'reads links stored as a list', async () => {
		await open( { waypointPosts: [ 10, 11 ] } );
		expect( selects.get( '1. Fountain' )?.value ).toBe( '10' );
		expect( selects.get( '2. Refuge' )?.value ).toBe( '11' );
		expect( writes ).toEqual( [] );

		choose( '1. Fountain', '' );
		expect( writes.at( -1 ) ).toEqual( { waypointPosts: { 1: 11 } } );
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

describe( 'readLinks', () => {
	it( 'reads links stored as an object or a list', () => {
		expect( readLinks( { 1: 10, 3: '12' } ) ).toEqual( { 1: 10, 3: 12 } );
		expect( readLinks( [ 10, 11 ] ) ).toEqual( { 0: 10, 1: 11 } );
	} );

	it( 'keeps only whole post IDs above zero at whole positions', () => {
		expect(
			readLinks( {
				0: 0,
				1: -2,
				2: 1.5,
				3: 'x',
				4: null,
				'-1': 5,
				'1.5': 6,
				x: 7,
				5: 8,
			} )
		).toEqual( { 5: 8 } );
	} );

	it( 'reads nothing from anything else', () => {
		expect( readLinks( undefined ) ).toBeUndefined();
		expect( readLinks( 'x' ) ).toBeUndefined();
		expect( readLinks( {} ) ).toBeUndefined();
		expect( readLinks( [ 0 ] ) ).toBeUndefined();
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
