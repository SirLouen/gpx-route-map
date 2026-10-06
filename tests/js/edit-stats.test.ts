/**
 * The stats the block editor stores, driven through the real Edit component.
 *
 * The editor works the stats out from the GPX file and stores them on the
 * block, so the server can print the stats bar. Opening a post must not
 * change it, yet choosing the min elevation - which stats stored before 2.0.0
 * lack - is the author's own edit, and stores them with it. Which stats, read
 * from which file, and when, is what goes wrong here, so the component runs
 * for real; only WordPress's UI and data stores are stood in for.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	createElement,
	createRoot,
	flushSync,
	useState,
} from '@wordpress/element';

/** Change handlers of the toggles and checkboxes, by label. */
const toggles = new Map< string, ( checked: boolean ) => void >();
/** Text fields, by label. */
const fields = new Map<
	string,
	{ onChange: ( value: string ) => void; onBlur: () => void }
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
	const toggle = ( props: {
		label: string;
		onChange: ( checked: boolean ) => void;
	} ) => {
		toggles.set( props.label, props.onChange );
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
		SelectControl: none,
		ToggleControl: toggle,
		CheckboxControl: toggle,
		Notice: none,
	};
} );

vi.mock( '../../src/editor/map-preview', () => ( { MapPreview: () => null } ) );

import Edit from '../../src/edit';
import { bakeStats } from '../../src/baked-stats';
import type { GpxStats } from '../../src/baked-stats';
import { parseGPX } from '../../src/view/map-core';

const GPX =
	'<gpx><trk><trkseg>' +
	'<trkpt lat="43.0" lon="-6.0"><ele>150</ele></trkpt>' +
	'<trkpt lat="43.01" lon="-6.0"><ele>100</ele></trkpt>' +
	'</trkseg></trk></gpx>';

/** The stats of GPX, as stored before 2.0.0: no min. */
const OLD: GpxStats = { ...bakeStats( parseGPX( GPX ) ) };
delete OLD.min;

const ATTRIBUTES = {
	gpxUrl: 'https://example.test/a.gpx',
	height: 0,
	heightTablet: 0,
	heightMobile: 0,
	showStats: '',
	showElevation: '',
	showDownload: '',
	statFields: 'distance,max',
	maxZoom: 0,
	provider: '',
	units: '',
	stats: OLD,
};

/** Downloads of the file, each answered by hand: read fine, or failed. */
let downloads: Array< ( ok: boolean ) => void >;
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
 * Open the post.
 *
 * @param changes Attributes that differ from ATTRIBUTES.
 */
function open( changes: Record< string, unknown > = {} ) {
	flushSync( () =>
		root.render(
			createElement( Block, { initial: { ...ATTRIBUTES, ...changes } } )
		)
	);
}

/** Let downloads and React catch up. */
const settle = async () => {
	for ( let i = 0; i < 5; i++ ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}
	flushSync( () => {} );
};

/**
 * Click a toggle or checkbox.
 *
 * @param label   Its label.
 * @param checked Its new state.
 */
const click = ( label: string, checked: boolean ) =>
	flushSync( () => toggles.get( label )?.( checked ) );

/**
 * Type an address into the GPX URL field and leave it.
 *
 * @param url The address.
 */
const typeUrl = ( url: string ) => {
	flushSync( () => fields.get( 'or GPX file URL' )?.onChange( url ) );
	flushSync( () => fields.get( 'or GPX file URL' )?.onBlur() );
};

/**
 * Answer the latest download.
 *
 * @param ok Whether the file reads fine.
 */
const answer = async ( ok: boolean ) => {
	downloads[ downloads.length - 1 ]( ok );
	await settle();
};

/** The stats the editor stored with a min in them. */
const storedWithMin = () =>
	writes.filter(
		( write ) => undefined !== ( write.stats as GpxStats | undefined )?.min
	);

beforeEach( () => {
	downloads = [];
	writes = [];
	toggles.clear();
	fields.clear();
	( window as unknown as { gpxrmDefaults?: object } ).gpxrmDefaults = {
		statFields: [ 'distance', 'gain', 'loss', 'max', 'min', 'waypoints' ],
	};
	vi.stubGlobal(
		'fetch',
		vi.fn(
			() =>
				new Promise< Response >( ( resolve ) => {
					downloads.push( ( ok ) =>
						resolve( {
							ok,
							status: ok ? 200 : 404,
							text: () => Promise.resolve( GPX ),
						} as Response )
					);
				} )
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

describe( 'The stats the editor stores', () => {
	it( 'stores nothing when a post is merely opened', async () => {
		open();
		await settle();
		await answer( true );

		expect( writes ).toEqual( [] );
	} );

	it( 'stores them, min included, when the author chooses the min', async () => {
		open();
		await settle();
		await answer( true );

		click( 'Min elevation', true );
		await settle();

		expect( storedWithMin() ).toHaveLength( 1 );
		expect( writes.at( -1 ) ).toMatchObject( {
			statFields: 'distance,max,min',
			stats: { min: 100 },
		} );
	} );

	it( 'stores them once the file is read when the min was chosen first', async () => {
		open();
		await settle();
		click( 'Min elevation', true );
		await answer( true );

		expect( storedWithMin() ).toHaveLength( 1 );
	} );

	it( 'never stores the stats of a file the block no longer shows', async () => {
		open();
		await settle();
		await answer( true );

		typeUrl( 'https://example.test/b.gpx' );
		await settle();
		await answer( false );
		click( 'Min elevation', true );
		await settle();

		expect( storedWithMin() ).toEqual( [] );
	} );

	it( 'never stores stats from an earlier read of a file that now fails', async () => {
		open();
		await settle();
		await answer( true );

		typeUrl( 'https://example.test/b.gpx' );
		await settle();
		await answer( false );
		typeUrl( 'https://example.test/a.gpx' );
		await settle();
		await answer( false );
		click( 'Min elevation', true );
		await settle();

		expect( storedWithMin() ).toEqual( [] );
	} );

	// Stopping following the site must not change what the map shows. The
	// site's list has the min here, so the stats go with it.
	it( 'starts from the site’s own list when it stops following the site', async () => {
		open( { statFields: '' } );
		await settle();
		await answer( true );
		expect( writes ).toEqual( [] );

		click( 'Use site default', false );
		await settle();

		expect( writes ).toEqual( [
			expect.objectContaining( {
				statFields: 'distance,gain,loss,max,min,waypoints',
				stats: expect.objectContaining( { min: 100 } ),
			} ),
		] );
	} );
} );
