/**
 * Waypoint posts on the map: reading what the server says about them, and
 * the markers and popups they change.
 */

import { describe, expect, it } from 'vitest';

import { addWaypointMarkers } from '../../src/view/map-core';
import { readWaypointPosts } from '../../src/view/waypoint-posts';
import type { MapLibreGl, Waypoint, WaypointPost } from '../../src/view/types';

/**
 * A map element carrying the attribute as the server writes it.
 *
 * @param value The attribute's value, or undefined for none.
 */
function mapWith( value: unknown ): HTMLElement {
	const el = document.createElement( 'div' );
	if ( undefined !== value ) {
		el.dataset.gpxrmWaypoints =
			'string' === typeof value ? value : JSON.stringify( value );
	}
	return el;
}

describe( 'readWaypointPosts', () => {
	it( 'reads nothing from a map without the attribute', () => {
		expect( readWaypointPosts( mapWith( undefined ) ) ).toEqual( {} );
	} );

	it.each( [
		[ 'malformed JSON', '{"0":' ],
		[ 'a string', '"hello"' ],
		[ 'a number', '42' ],
		[ 'null', 'null' ],
	] )( 'reads nothing from %s', ( _label, value ) => {
		expect( readWaypointPosts( mapWith( value ) ) ).toEqual( {} );
	} );

	it( 'keys entries by waypoint position, skipping bad keys', () => {
		const posts = readWaypointPosts(
			mapWith( {
				0: { name: 'Fountain' },
				3: { desc: 'Cold water' },
				'-1': { name: 'Negative' },
				'1.5': { name: 'Fraction' },
				x: { name: 'Word' },
				4: 'not an object',
			} )
		);
		expect( posts ).toEqual( {
			0: { name: 'Fountain' },
			3: { desc: 'Cold water' },
		} );
	} );

	it( 'reads a photo marker with its popup photo', () => {
		const posts = readWaypointPosts(
			mapWith( {
				2: {
					name: 'Steam Heritage Trail',
					desc: 'Line one\nLine two',
					marker: 'image',
					thumb: 'https://example.com/a-150x150.jpg',
					photo: {
						src: 'https://example.com/a-300x200.jpg',
						width: 300,
						height: 200,
						alt: 'A locomotive',
						srcset: 'https://example.com/a-300x200.jpg 300w, https://example.com/a.jpg 1200w',
					},
				},
			} )
		);
		expect( posts[ 2 ] ).toEqual( {
			name: 'Steam Heritage Trail',
			desc: 'Line one\nLine two',
			marker: 'image',
			thumb: 'https://example.com/a-150x150.jpg',
			photo: {
				src: 'https://example.com/a-300x200.jpg',
				width: 300,
				height: 200,
				alt: 'A locomotive',
				srcset: 'https://example.com/a-300x200.jpg 300w, https://example.com/a.jpg 1200w',
			},
		} );
	} );

	// The attribute is the server's own output, but a cached page or a filter
	// can hand over anything: only http(s) addresses become a src.
	it.each( [
		[ 'javascript:alert(1)' ],
		[ 'data:image/svg+xml,<svg/>' ],
		[ 'http://[bad' ],
		[ 42 ],
	] )( 'drops a photo or thumbnail at %s', ( src ) => {
		const posts = readWaypointPosts(
			mapWith( {
				0: {
					marker: 'image',
					thumb: src,
					photo: { src, width: 10, height: 10, alt: '' },
				},
			} )
		);
		expect( posts[ 0 ] ).toEqual( {} );
	} );

	it( 'drops a srcset with any address that is not http(s)', () => {
		const posts = readWaypointPosts(
			mapWith( {
				0: {
					photo: {
						src: 'https://example.com/a.jpg',
						width: 10,
						height: 10,
						alt: '',
						srcset: 'https://example.com/a.jpg 300w, javascript:x 600w',
					},
				},
			} )
		);
		expect( posts[ 0 ].photo ).toEqual( {
			src: 'https://example.com/a.jpg',
			width: 10,
			height: 10,
			alt: '',
		} );
	} );

	/**
	 * The srcset a popup photo keeps of the one the server sent.
	 *
	 * @param set The srcset sent.
	 */
	const keptSrcset = ( set: string ) =>
		readWaypointPosts(
			mapWith( {
				0: {
					photo: {
						src: 'https://example.com/a.jpg',
						width: 10,
						height: 10,
						alt: '',
						srcset: set,
					},
				},
			} )
		)[ 0 ].photo?.srcset;

	// However its candidates are separated, none may slip an address past
	// the check.
	it.each( [
		[
			'no space after a comma',
			'https://example.com/a.jpg 300w,javascript:x 600w',
		],
		[
			'an address that ends its candidate',
			'https://example.com/a.jpg, javascript:x 2x',
		],
		[
			'a comma after parentheses',
			'https://example.com/a.jpg 1x (y),javascript:x 2x',
		],
		[ 'no candidate at all', ' , , ' ],
	] )( 'drops a srcset with %s', ( _label, set ) => {
		expect( keptSrcset( set ) ).toBeUndefined();
	} );

	it( 'reads no size that is not a whole number of pixels', () => {
		const posts = readWaypointPosts(
			mapWith( {
				0: {
					photo: {
						src: 'https://example.com/a.jpg',
						width: 10.5,
						height: '20',
						alt: 7,
					},
				},
			} )
		);
		expect( posts[ 0 ].photo ).toEqual( {
			src: 'https://example.com/a.jpg',
			width: 0,
			height: 0,
			alt: '',
		} );
	} );

	it( 'reads an icon marker and its colours', () => {
		const posts = readWaypointPosts(
			mapWith( {
				0: {
					marker: 'icon',
					icon: 'location-alt',
					color: '#FFF',
					background: '#2e7d32',
				},
			} )
		);
		expect( posts[ 0 ] ).toEqual( {
			marker: 'icon',
			icon: 'location-alt',
			color: '#FFF',
			background: '#2e7d32',
		} );
	} );

	// The icon becomes part of a class name, and the colours inline styles.
	it.each( [
		[ 'an icon outside the font names', 'x" onclick="y', '#fff', '#000' ],
		[ 'an upper-case icon', 'Location', '#fff', '#000' ],
	] )( 'drops %s', ( _label, icon, color, background ) => {
		const posts = readWaypointPosts(
			mapWith( { 0: { marker: 'icon', icon, color, background } } )
		);
		expect( posts[ 0 ] ).toEqual( {} );
	} );

	it( 'leaves out colours that are not hex', () => {
		const posts = readWaypointPosts(
			mapWith( {
				0: {
					marker: 'icon',
					icon: 'flag',
					color: 'red; background: url(x)',
					background: '#12345',
				},
			} )
		);
		expect( posts[ 0 ] ).toEqual( { marker: 'icon', icon: 'flag' } );
	} );

	it( 'reads an image marker only with its thumbnail', () => {
		const posts = readWaypointPosts(
			mapWith( { 0: { marker: 'image', name: 'A' } } )
		);
		expect( posts[ 0 ] ).toEqual( { name: 'A' } );
	} );

	it( 'leaves out a name or description of only whitespace', () => {
		const posts = readWaypointPosts(
			mapWith( { 0: { name: '  ', desc: '\n\t' } } )
		);
		expect( posts[ 0 ] ).toEqual( {} );
	} );
} );

describe( 'addWaypointMarkers with Waypoint posts', () => {
	/**
	 * Draw waypoints with fakes standing in for MapLibre, keeping what each
	 * marker and popup was given.
	 *
	 * @param waypoints Waypoints.
	 * @param posts     Waypoint posts by position.
	 */
	function draw(
		waypoints: Waypoint[],
		posts?: Record< number, WaypointPost >
	) {
		const drawn: Array< { el: HTMLElement; content: HTMLElement } > = [];

		class FakePopup {
			content: HTMLElement = document.createElement( 'div' );
			setDOMContent( content: HTMLElement ) {
				this.content = content;
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
			constructor( options: { element: HTMLElement } ) {
				this.element = options.element;
			}
			setLngLat() {
				return this;
			}
			setPopup( popup: FakePopup ) {
				drawn.push( { el: this.element, content: popup.content } );
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

		addWaypointMarkers(
			{ Popup: FakePopup, Marker: FakeMarker } as unknown as MapLibreGl,
			{
				getContainer: () => document.createElement( 'div' ),
			} as unknown as Parameters< typeof addWaypointMarkers >[ 1 ],
			waypoints,
			'#ffffff',
			posts
		);
		return drawn;
	}

	const waypoint = ( index: number, extra: Partial< Waypoint > = {} ) => ( {
		lon: -5.8,
		lat: 43.3,
		name: 'From the file',
		desc: 'GPX description',
		type: '',
		link: '',
		index,
		postId: 0,
		...extra,
	} );

	it( 'draws the usual marker when no post is linked', () => {
		const [ { el, content } ] = draw( [ waypoint( 0 ) ] );
		expect( el.querySelector( '.gpxrm-marker' )?.textContent ).not.toBe(
			''
		);
		expect( el.querySelector( 'img, .dashicons' ) ).toBeNull();
		expect(
			content.querySelector( '.gpxrm-popup-name' )?.textContent
		).toBe( 'From the file' );
		expect(
			content.querySelector( '.gpxrm-popup-desc' )?.textContent
		).toBe( 'GPX description' );
	} );

	it( 'matches posts by position, not by order on the map', () => {
		const drawn = draw( [ waypoint( 0 ), waypoint( 2 ) ], {
			2: { name: 'Linked' },
		} );
		expect(
			drawn.map(
				( { content } ) =>
					content.querySelector( '.gpxrm-popup-name' )?.textContent
			)
		).toEqual( [ 'From the file', 'Linked' ] );
	} );

	it( 'shows the photo marker and puts the photo above the title', () => {
		const [ { el, content } ] = draw( [ waypoint( 0 ) ], {
			0: {
				name: 'Steam Heritage Trail',
				desc: 'Line one\nLine two',
				marker: 'image',
				thumb: 'https://example.com/a-150x150.jpg',
				photo: {
					src: 'https://example.com/a-300x200.jpg',
					width: 300,
					height: 200,
					alt: 'A locomotive',
					srcset: 'https://example.com/a-300x200.jpg 300w',
				},
			},
		} );

		expect( el.classList.contains( 'gpxrm-waypoint-image' ) ).toBe( true );
		const thumb = el.querySelector( '.gpxrm-marker-image img' );
		expect( thumb?.getAttribute( 'src' ) ).toBe(
			'https://example.com/a-150x150.jpg'
		);
		expect( thumb?.getAttribute( 'alt' ) ).toBe( '' );

		const first = content.firstElementChild as HTMLImageElement;
		expect( first.className ).toBe( 'gpxrm-popup-photo' );
		expect( first.getAttribute( 'src' ) ).toBe(
			'https://example.com/a-300x200.jpg'
		);
		expect( first.alt ).toBe( 'A locomotive' );
		expect( first.width ).toBe( 300 );
		expect( first.height ).toBe( 200 );
		expect( first.getAttribute( 'sizes' ) ).toBe( '240px' );
		expect( first.nextElementSibling?.textContent ).toBe(
			'Steam Heritage Trail'
		);

		// The post's description replaces the file's, line breaks and all.
		const desc = content.querySelectorAll( '.gpxrm-popup-desc' );
		expect( desc ).toHaveLength( 1 );
		expect( desc[ 0 ].classList.contains( 'gpxrm-popup-desc-post' ) ).toBe(
			true
		);
		expect( desc[ 0 ].textContent ).toBe( 'Line one\nLine two' );
	} );

	it( 'draws a Dashicon in the chosen colours', () => {
		const [ { el } ] = draw( [ waypoint( 0 ) ], {
			0: {
				marker: 'icon',
				icon: 'flag',
				color: '#ff0000',
				background: '#0000ff',
			},
		} );
		const dot = el.querySelector< HTMLElement >( '.gpxrm-marker-icon' );
		expect( dot ).not.toBeNull();
		expect( dot?.style.color ).toBe( 'rgb(255, 0, 0)' );
		expect( dot?.style.background ).toContain( 'rgb(0, 0, 255)' );
		const glyph = dot?.querySelector( '.dashicons' );
		expect( glyph?.className ).toBe( 'dashicons dashicons-flag' );
		expect( glyph?.getAttribute( 'aria-hidden' ) ).toBe( 'true' );
	} );

	it( 'gives an icon without colours a white glyph on the track colour', () => {
		const [ { el } ] = draw( [ waypoint( 0 ) ], {
			0: { marker: 'icon', icon: 'flag' },
		} );
		const dot = el.querySelector< HTMLElement >( '.gpxrm-marker-icon' );
		expect( dot?.style.color ).toBe( 'rgb(255, 255, 255)' );
		expect( dot?.style.background ).not.toBe( '' );
	} );

	it( 'keeps the file name and description when the post has none', () => {
		const [ { el, content } ] = draw( [ waypoint( 0 ) ], {
			0: { marker: 'icon', icon: 'flag' },
		} );
		expect( el.querySelector( '.dashicons' ) ).not.toBeNull();
		expect(
			content.querySelector( '.gpxrm-popup-name' )?.textContent
		).toBe( 'From the file' );
		expect(
			content.querySelector( '.gpxrm-popup-desc' )?.textContent
		).toBe( 'GPX description' );
		expect( content.querySelector( 'img' ) ).toBeNull();
	} );

	// Text from a post is text, whatever it looks like.
	it( 'writes a post name as text', () => {
		const [ { content } ] = draw( [ waypoint( 0 ) ], {
			0: { name: '<img src=x onerror=alert(1)>' },
		} );
		expect( content.querySelector( 'img' ) ).toBeNull();
		expect(
			content.querySelector( '.gpxrm-popup-name' )?.textContent
		).toBe( '<img src=x onerror=alert(1)>' );
	} );

	/**
	 * A post description's row, as a popup shows it.
	 *
	 * @param desc The description's markup.
	 */
	const descriptionRow = ( desc: string ) =>
		draw( [ waypoint( 0 ) ], { 0: { desc } } )[ 0 ].content.querySelector(
			'.gpxrm-popup-desc-post'
		) as HTMLElement;

	it( 'shows a description with its links, bold and italic text and lists', () => {
		const row = descriptionRow(
			'<p>Book on <a href="https://example.com/book">the site</a>, <strong>early</strong>.</p>\n<ul><li><em>Water</em></li></ul>'
		);
		expect( row.innerHTML ).toBe(
			'<p>Book on <a href="https://example.com/book">the site</a>, <strong>early</strong>.</p>\n<ul><li><em>Water</em></li></ul>'
		);
	} );

	it( 'opens a link in a new tab only as its author chose, and safely', () => {
		const row = descriptionRow(
			'<a href="https://example.com/" target="_blank" rel="opener">New tab</a> <a href="https://example.com/" target="_top">Same tab</a>'
		);
		const [ blank, same ] = Array.from( row.querySelectorAll( 'a' ) );
		expect( blank.getAttribute( 'target' ) ).toBe( '_blank' );
		expect( blank.getAttribute( 'rel' ) ).toBe( 'noopener noreferrer' );
		expect( same.hasAttribute( 'target' ) ).toBe( false );
		expect( same.hasAttribute( 'rel' ) ).toBe( false );
	} );

	it( 'keeps a link that is not http(s) or mailto only as its text', () => {
		const row = descriptionRow(
			'<a href="javascript:alert(1)">Go</a> <a href="mailto:hut@example.com">Mail</a>'
		);
		const [ go, mail ] = Array.from( row.querySelectorAll( 'a' ) );
		expect( go.hasAttribute( 'href' ) ).toBe( false );
		expect( go.textContent ).toBe( 'Go' );
		expect( mail.getAttribute( 'href' ) ).toBe( 'mailto:hut@example.com' );
	} );

	it( 'drops every other element and attribute a description is sent with', () => {
		const row = descriptionRow(
			'<p onclick="alert(1)" style="color:red" class="x">Text</p><img src=x onerror="window.hacked=1"><script>window.hacked=2</script><style>p{}</style><span title="t">Shade</span><svg><a href="https://example.com/">svg</a></svg>'
		);
		expect( row.innerHTML ).toBe( '<p>Text</p>Shade' );
		expect(
			( window as unknown as { hacked?: number } ).hacked
		).toBeUndefined();
	} );
} );

// A photo's popup on a short map would run past the map's edge, which clips
// it, so the name and description below the photo could not be reached.
describe( 'popups taller than the map', () => {
	/**
	 * Open the popup of one waypoint on a map of a given height.
	 *
	 * @param mapHeight     The map's height.
	 * @param contentHeight The popup content's height before it is fitted.
	 * @param pointY        How far down the map the waypoint is.
	 */
	function openPopup(
		mapHeight: number,
		contentHeight: number,
		pointY: number
	) {
		const element = document.createElement( 'div' );
		const box = document.createElement( 'div' );
		box.className = 'maplibregl-popup-content';
		Object.defineProperty( box, 'offsetHeight', {
			get: () => contentHeight,
		} );
		element.appendChild( box );
		const placed: unknown[] = [];
		const onOpen: Array< () => void > = [];

		class FakePopup {
			setDOMContent() {
				return this;
			}
			on( type: string, fn: () => void ) {
				if ( 'open' === type ) {
					onOpen.push( fn );
				}
				return this;
			}
			isOpen() {
				return true;
			}
			remove() {
				return this;
			}
			getElement() {
				return element;
			}
			getLngLat() {
				return { lng: -5.8, lat: 43.3 };
			}
			setLngLat( lngLat: unknown ) {
				placed.push( lngLat );
				return this;
			}
		}

		class FakeMarker {
			element: HTMLElement;
			constructor( options: { element: HTMLElement } ) {
				this.element = options.element;
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

		const container = document.createElement( 'div' );
		Object.defineProperty( container, 'clientHeight', {
			get: () => mapHeight,
		} );
		addWaypointMarkers(
			{ Popup: FakePopup, Marker: FakeMarker } as unknown as MapLibreGl,
			{
				getContainer: () => container,
				project: () => ( { x: 100, y: pointY } ),
			} as unknown as Parameters< typeof addWaypointMarkers >[ 1 ],
			[
				{
					lon: -5.8,
					lat: 43.3,
					name: 'A',
					desc: '',
					type: '',
					link: '',
					index: 0,
					postId: 0,
				},
			],
			'#ffffff'
		);
		onOpen.forEach( ( fn ) => fn() );
		return { box, placed };
	}

	it( 'scrolls a popup taller than the map, and places it again', () => {
		// Opening above the waypoint, where there is more room.
		const { box, placed } = openPopup( 220, 300, 150 );
		expect( box.style.maxHeight ).toBe( '118px' );
		expect( box.style.overflowY ).toBe( 'auto' );
		expect( placed ).toEqual( [ { lng: -5.8, lat: 43.3 } ] );
	} );

	it( 'leaves a popup that fits as it is', () => {
		const { box, placed } = openPopup( 480, 300, 400 );
		expect( box.style.maxHeight ).toBe( '' );
		expect( placed ).toEqual( [] );
	} );

	it( 'gives a waypoint near the top the room below it', () => {
		const { box } = openPopup( 220, 300, 30 );
		expect( box.style.maxHeight ).toBe( '158px' );
	} );
} );
