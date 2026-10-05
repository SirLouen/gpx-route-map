/**
 * Tearing a map instance down.
 *
 * The front end builds each map once and never removes it. The editor preview
 * will build and remove maps as blocks mount, change and unmount, so an
 * instance has to be stoppable at any point: before its GPX file arrives,
 * while it is being set up, or long after it is built. Whatever it leaves
 * behind keeps running against a map that is gone, and every map left alive
 * holds one of the browser's few WebGL contexts.
 *
 * MapLibre needs WebGL, which jsdom lacks, so a fake stands in for it and
 * records what the instance does to the map.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { initInstance } from '../../src/view/map-instance';
import { ElevationProfile } from '../../src/view/elevation';
import type { MapLibreGl } from '../../src/view/types';

const GPX =
	'<?xml version="1.0" encoding="UTF-8"?>' +
	'<gpx xmlns="http://www.topografix.com/GPX/1/1"><trk><trkseg>' +
	'<trkpt lat="43.0" lon="-6.0"><ele>100</ele></trkpt>' +
	'<trkpt lat="43.01" lon="-6.0"><ele>150</ele></trkpt>' +
	'<trkpt lat="43.02" lon="-6.0"><ele>120</ele></trkpt>' +
	'</trkseg></trk>' +
	'<wpt lat="43.01" lon="-6.0"><name>Refuge</name></wpt></gpx>';

/** A stand-in for the MapLibre module that records what is done to it. */
function fakeMapLibre() {
	const maps: FakeMap[] = [];
	const controls: string[] = [];
	const setData = vi.fn( () => Promise.resolve() );

	class FakeMap {
		container: HTMLElement;
		removed = 0;
		eased = 0;
		handlers: Record< string, Array< () => void > > = {};

		constructor( options: { container: HTMLElement } ) {
			this.container = options.container;
			maps.push( this );
		}

		on( type: string, layerOrListener: unknown, listener?: unknown ) {
			const fn = (
				'function' === typeof layerOrListener
					? layerOrListener
					: listener
			) as () => void;
			( this.handlers[ type ] ??= [] ).push( fn );
			return this;
		}

		fire( type: string ) {
			( this.handlers[ type ] ?? [] ).forEach( ( fn ) => fn() );
		}

		addControl( control: { name?: string } ) {
			controls.push( control.name ?? 'other' );
			return this;
		}

		addSource() {}

		addLayer() {}

		getSource() {
			return { setData };
		}

		getCanvas() {
			return document.createElement( 'canvas' );
		}

		easeTo() {
			this.eased++;
		}

		remove() {
			this.removed++;
		}
	}

	class FakePopup {
		setDOMContent() {
			return this;
		}
		on() {
			return this;
		}
		isOpen() {
			return false;
		}
		remove() {
			return this;
		}
		getElement() {
			return undefined;
		}
	}

	class FakeMarker {
		element: HTMLElement;
		constructor( options?: { element?: HTMLElement } ) {
			this.element = options?.element ?? document.createElement( 'div' );
		}
		setLngLat() {
			return this;
		}
		setPopup() {
			return this;
		}
		getElement() {
			return this.element;
		}
		togglePopup() {
			return this;
		}
		addTo() {
			return this;
		}
	}

	const control = ( name: string ) =>
		class {
			name = name;
		};

	const lib = {
		Map: FakeMap,
		Popup: FakePopup,
		Marker: FakeMarker,
		NavigationControl: control( 'navigation' ),
		ScaleControl: control( 'scale' ),
		FullscreenControl: control( 'fullscreen' ),
		GeolocateControl: control( 'geolocate' ),
	} as unknown as MapLibreGl;

	return { lib, maps, controls, setData };
}

type FakeMap = ReturnType< typeof fakeMapLibre >[ 'maps' ][ number ];

/** The markup the server renders, with an elevation canvas jsdom can draw on. */
function mount() {
	const root = document.createElement( 'div' );
	root.className = 'gpxrm';
	const mapEl = document.createElement( 'div' );
	mapEl.className = 'gpxrm-map';
	mapEl.dataset.gpxrmGpx = 'https://example.test/route.gpx';

	const canvas = document.createElement( 'canvas' );
	canvas.dataset.gpxrmElevation = '';
	canvas.getBoundingClientRect = () =>
		( {
			width: 400,
			height: 160,
			left: 0,
			top: 0,
			right: 400,
			bottom: 160,
			x: 0,
			y: 0,
		} ) as DOMRect;
	canvas.getContext = ( () =>
		new Proxy(
			{},
			{
				get: ( _target, prop ) => {
					if ( 'createLinearGradient' === prop ) {
						return () => ( { addColorStop: () => {} } );
					}
					if ( 'measureText' === prop ) {
						return () => ( { width: 50 } );
					}
					return () => {};
				},
				set: () => true,
			}
		) ) as unknown as HTMLCanvasElement[ 'getContext' ];

	root.append( mapEl, canvas );
	document.body.append( root );
	return { mapEl, canvas };
}

/** A fetch that answers with the GPX file and ignores any abort. */
function answeringFetch() {
	return vi.fn( () =>
		Promise.resolve( {
			ok: true,
			status: 200,
			text: () => Promise.resolve( GPX ),
		} as Response )
	);
}

/** A fetch that never answers, and rejects as a browser does when aborted. */
function pendingFetch() {
	return vi.fn(
		( _url: string, init?: RequestInit ) =>
			new Promise< Response >( ( _resolve, reject ) => {
				init?.signal?.addEventListener( 'abort', () =>
					reject( new DOMException( 'Aborted', 'AbortError' ) )
				);
			} )
	);
}

/**
 * Build an instance all the way: GPX loaded, map loaded, profile drawn.
 *
 * @param mapEl  Map element.
 * @param lib    Fake MapLibre.
 * @param maps   Maps the fake has built so far.
 * @param signal Abort signal, if any.
 */
async function buildFully(
	mapEl: HTMLElement,
	lib: MapLibreGl,
	maps: FakeMap[],
	signal?: AbortSignal
) {
	await initInstance( mapEl, lib, signal );
	maps[ maps.length - 1 ].fire( 'load' );
	// The profile is drawn on the next animation frame.
	vi.advanceTimersByTime( 50 );
}

/**
 * Scrub the elevation profile once, as a visitor pressing on it would.
 *
 * @param canvas Elevation canvas.
 */
function scrub( canvas: HTMLCanvasElement ) {
	canvas.dispatchEvent(
		new MouseEvent( 'mousedown', { clientX: 200, bubbles: true } )
	);
}

/** Resize the window and wait out the profile's resize debounce. */
function resizeWindow() {
	window.dispatchEvent( new Event( 'resize' ) );
	vi.advanceTimersByTime( 300 );
}

type BuildSpy = { mock: { contexts: unknown[] } };

/**
 * How often build() ran on one particular profile.
 *
 * Counted per instance: the front-end path keeps its window listeners for
 * the life of the page by design, so profiles from earlier tests in this
 * file still answer a resize.
 *
 * @param build   Spy on ElevationProfile.prototype.build.
 * @param profile The profile to count.
 */
function buildsOf( build: BuildSpy, profile: unknown ): number {
	return build.mock.contexts.filter( ( p ) => p === profile ).length;
}

/**
 * The profile most recently built.
 *
 * @param build Spy on ElevationProfile.prototype.build.
 */
function lastProfile( build: BuildSpy ): unknown {
	return build.mock.contexts[ build.mock.contexts.length - 1 ];
}

beforeEach( () => {
	vi.useFakeTimers();
	document.body.replaceChildren();
} );

afterEach( () => {
	vi.useRealTimers();
	vi.unstubAllGlobals();
	vi.restoreAllMocks();
} );

describe( 'initInstance', () => {
	it( 'builds a live map when given no signal, as the front end calls it', async () => {
		vi.stubGlobal( 'fetch', answeringFetch() );
		const { lib, maps, setData } = fakeMapLibre();
		const { mapEl, canvas } = mount();
		const build = vi.spyOn( ElevationProfile.prototype, 'build' );

		await buildFully( mapEl, lib, maps );

		expect( maps ).toHaveLength( 1 );
		expect( maps[ 0 ].container ).toBe( mapEl );
		scrub( canvas );
		expect( setData ).toHaveBeenCalled();
		const profile = lastProfile( build );
		const builds = buildsOf( build, profile );
		resizeWindow();
		expect( buildsOf( build, profile ) ).toBe( builds + 1 );
	} );

	it( 'builds nothing and shows no error when aborted before the GPX arrives', async () => {
		const fetch = pendingFetch();
		vi.stubGlobal( 'fetch', fetch );
		const { lib, maps } = fakeMapLibre();
		const { mapEl } = mount();
		const controller = new AbortController();

		const done = initInstance( mapEl, lib, controller.signal );
		controller.abort();
		await done;

		// The download itself is cancelled, not just ignored when it lands.
		expect( fetch.mock.calls[ 0 ][ 1 ]?.signal ).toBe( controller.signal );
		expect( maps ).toHaveLength( 0 );
		expect( mapEl.querySelector( '.gpxrm-error' ) ).toBeNull();
	} );

	it( 'still builds nothing when the download ignores the abort', async () => {
		vi.stubGlobal( 'fetch', answeringFetch() );
		const { lib, maps } = fakeMapLibre();
		const { mapEl } = mount();
		const controller = new AbortController();

		const done = initInstance( mapEl, lib, controller.signal );
		controller.abort();
		await done;

		expect( maps ).toHaveLength( 0 );
		expect( mapEl.querySelector( '.gpxrm-error' ) ).toBeNull();
	} );

	it( 'removes a built map and stops reacting to the page when aborted', async () => {
		vi.stubGlobal( 'fetch', answeringFetch() );
		const { lib, maps, setData } = fakeMapLibre();
		const { mapEl, canvas } = mount();
		const build = vi.spyOn( ElevationProfile.prototype, 'build' );
		const controller = new AbortController();
		await buildFully( mapEl, lib, maps, controller.signal );
		const profile = lastProfile( build );

		// Live before: scrubbing reaches the map. Otherwise the checks below
		// would pass for the wrong reason.
		scrub( canvas );
		expect( setData ).toHaveBeenCalled();

		controller.abort();

		expect( maps[ 0 ].removed ).toBe( 1 );
		setData.mockClear();
		const builds = buildsOf( build, profile );
		scrub( canvas );
		canvas.dispatchEvent(
			new MouseEvent( 'mousemove', { clientX: 220, bubbles: true } )
		);
		resizeWindow();
		expect( setData ).not.toHaveBeenCalled();
		expect( maps[ 0 ].eased ).toBe( 0 );
		// Not even called: its resize listener is gone, not merely ignored.
		expect( buildsOf( build, profile ) ).toBe( builds );
		expect( maps[ 0 ].removed ).toBe( 1 );
	} );

	it( 'does not draw the profile when aborted before its first frame', async () => {
		vi.stubGlobal( 'fetch', answeringFetch() );
		const { lib } = fakeMapLibre();
		const { mapEl, canvas } = mount();
		const controller = new AbortController();
		const unsized = canvas.width;

		await initInstance( mapEl, lib, controller.signal );
		controller.abort();
		vi.advanceTimersByTime( 50 );

		// Drawing sizes the canvas to its box (400px here); it never happened.
		expect( canvas.width ).toBe( unsized );
	} );

	// Every editor map will carry a signal, so having one must not be
	// mistaken for having been aborted.
	it( 'still reports a failed download while its signal is live', async () => {
		vi.stubGlobal(
			'fetch',
			vi.fn( () =>
				Promise.resolve( { ok: false, status: 404 } as Response )
			)
		);
		const { lib, maps } = fakeMapLibre();
		const { mapEl } = mount();

		await initInstance( mapEl, lib, new AbortController().signal );

		expect( maps ).toHaveLength( 0 );
		expect( mapEl.querySelector( '.gpxrm-error' )?.textContent ).toBe(
			'Could not load GPX file.'
		);
	} );

	it( 'stops quietly when aborted while the file body is downloading', async () => {
		let bodyStarted: () => void = () => {};
		const reading = new Promise< void >( ( resolve ) => {
			bodyStarted = resolve;
		} );
		vi.stubGlobal(
			'fetch',
			vi.fn( ( _url: string, init?: RequestInit ) =>
				Promise.resolve( {
					ok: true,
					status: 200,
					text: () =>
						new Promise< string >( ( _resolve, reject ) => {
							bodyStarted();
							init?.signal?.addEventListener( 'abort', () =>
								reject(
									new DOMException( 'Aborted', 'AbortError' )
								)
							);
						} ),
				} as Response )
			)
		);
		const { lib, maps } = fakeMapLibre();
		const { mapEl } = mount();
		const controller = new AbortController();

		const done = initInstance( mapEl, lib, controller.signal );
		await reading;
		controller.abort();

		await expect( done ).resolves.toBeUndefined();
		expect( maps ).toHaveLength( 0 );
		expect( mapEl.querySelector( '.gpxrm-error' ) ).toBeNull();
	} );

	// A file that failed, then a good one, as in the editor after a bad pick.
	it( 'clears an earlier error from the element it builds on', async () => {
		const { lib, maps } = fakeMapLibre();
		const { mapEl } = mount();
		vi.stubGlobal(
			'fetch',
			vi.fn( () =>
				Promise.resolve( { ok: false, status: 404 } as Response )
			)
		);
		await initInstance( mapEl, lib, new AbortController().signal );
		expect( mapEl.querySelector( '.gpxrm-error' ) ).not.toBeNull();

		vi.stubGlobal( 'fetch', answeringFetch() );
		await buildFully( mapEl, lib, maps, new AbortController().signal );

		expect( maps ).toHaveLength( 1 );
		expect( mapEl.querySelector( '.gpxrm-error' ) ).toBeNull();
	} );

	it.each( [
		[
			'leaves out fullscreen and location on a map the editor marks',
			true,
			[ 'navigation', 'scale' ],
		],
		[
			'keeps every control on a front-end map',
			false,
			[ 'navigation', 'scale', 'fullscreen', 'geolocate' ],
		],
	] )( '%s', async ( _label, marked, expected ) => {
		vi.stubGlobal( 'fetch', answeringFetch() );
		const { lib, maps, controls } = fakeMapLibre();
		const { mapEl } = mount();
		if ( marked ) {
			mapEl.dataset.gpxrmPreview = '';
		}

		await buildFully( mapEl, lib, maps, new AbortController().signal );

		expect( controls ).toEqual( expected );
	} );

	describe( 'where the browser can watch an element’s size', () => {
		const observers: Array< {
			callback: () => void;
			targets: Element[];
			disconnected: boolean;
		} > = [];

		beforeEach( () => {
			observers.length = 0;
			vi.stubGlobal(
				'ResizeObserver',
				class {
					entry: ( typeof observers )[ number ];
					constructor( callback: () => void ) {
						this.entry = {
							callback,
							targets: [],
							disconnected: false,
						};
						observers.push( this.entry );
					}
					observe( target: Element ) {
						this.entry.targets.push( target );
					}
					disconnect() {
						this.entry.disconnected = true;
					}
				}
			);
		} );

		// A theme, or the block editor changing the block's alignment, can
		// resize the profile without the window changing size at all.
		it( 'redraws the profile when its own width changes', async () => {
			vi.stubGlobal( 'fetch', answeringFetch() );
			const { lib, maps } = fakeMapLibre();
			const { mapEl, canvas } = mount();
			const build = vi.spyOn( ElevationProfile.prototype, 'build' );
			await buildFully( mapEl, lib, maps, new AbortController().signal );
			const profile = lastProfile( build );
			const builds = buildsOf( build, profile );

			const watcher = observers.find( ( o ) =>
				o.targets.includes( canvas )
			);
			expect( watcher ).toBeDefined();
			watcher?.callback();
			vi.advanceTimersByTime( 300 );

			expect( buildsOf( build, profile ) ).toBe( builds + 1 );
		} );

		it( 'stops watching the profile when torn down', async () => {
			vi.stubGlobal( 'fetch', answeringFetch() );
			const { lib, maps } = fakeMapLibre();
			const { mapEl, canvas } = mount();
			const controller = new AbortController();
			await buildFully( mapEl, lib, maps, controller.signal );

			controller.abort();

			const watcher = observers.find( ( o ) =>
				o.targets.includes( canvas )
			);
			expect( watcher?.disconnected ).toBe( true );
		} );
	} );

	// What the editor does under React's mount, unmount, mount.
	it( 'builds a fresh map on the same element once the old one is gone', async () => {
		vi.stubGlobal( 'fetch', answeringFetch() );
		const { lib, maps } = fakeMapLibre();
		const { mapEl } = mount();
		const build = vi.spyOn( ElevationProfile.prototype, 'build' );
		const first = new AbortController();
		await buildFully( mapEl, lib, maps, first.signal );
		const oldProfile = lastProfile( build );
		first.abort();

		await buildFully( mapEl, lib, maps, new AbortController().signal );
		const newProfile = lastProfile( build );

		expect( maps ).toHaveLength( 2 );
		expect( maps[ 1 ].container ).toBe( mapEl );
		expect( maps[ 0 ].removed ).toBe( 1 );
		expect( maps[ 1 ].removed ).toBe( 0 );
		expect( newProfile ).not.toBe( oldProfile );
		// Only the live instance answers a resize.
		const oldBuilds = buildsOf( build, oldProfile );
		const newBuilds = buildsOf( build, newProfile );
		resizeWindow();
		expect( buildsOf( build, oldProfile ) ).toBe( oldBuilds );
		expect( buildsOf( build, newProfile ) ).toBe( newBuilds + 1 );
	} );
} );
