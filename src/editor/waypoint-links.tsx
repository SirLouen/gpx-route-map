/**
 * The block's Waypoints panel: link each waypoint of the GPX file to a
 * Waypoint post, and add posts at their own coordinates.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	Button,
	ExternalLink,
	PanelBody,
	SelectControl,
} from '@wordpress/components';

import { computeBoundsFromCoords, kmFromBox, reachKm } from '../view/map-core';
import type { Bounds, ParsedGpx, Waypoint } from '../view/types';

/** The post type, as registered by Waypoints::POST_TYPE. */
export const WAYPOINT_POST_TYPE = 'gpxrm_waypoint';

/**
 * Waypoints a block links to posts: post IDs by each waypoint's position
 * among the file's <wpt> elements, counting from 0.
 */
export type WaypointLinkMap = Record< string, number >;

/**
 * The links as a block stores them. Saving a post through the REST API, as the
 * block editor does, re-encodes block attributes in PHP, which writes an
 * object keyed 0, 1, 2… as a list.
 */
export type StoredWaypointLinks = WaypointLinkMap | number[];

/**
 * The posts the panel offers. In the view context, as the edit context the
 * editor asks for by default lists only the posts the user may edit: an
 * Author would see none of the site's own waypoints. Drafts and pending
 * posts the user may read come too, so a map can be linked to a waypoint
 * before it is published.
 */
const QUERY = {
	context: 'view',
	per_page: -1,
	status: 'publish,future,draft,pending',
	orderby: 'title',
	order: 'asc',
	_fields: 'id,title,status,gpxrm_coords',
};

/** The fields of a Waypoint post the panel reads. */
interface WaypointRecord {
	id: number;
	status: string;
	title?: { rendered?: string };
	/** Where it stands, or null without coordinates. */
	gpxrm_coords?: { lat: number; lon: number } | null;
}

/** What the server says about Waypoint posts on this site. */
interface SiteDefaults {
	waypoints?: unknown;
	waypointFiles?: unknown;
	uploadsUrl?: unknown;
}

/**
 * What the server says about Waypoint posts on this site.
 */
function siteDefaults(): SiteDefaults {
	return (
		( window as unknown as { gpxrmDefaults?: SiteDefaults } )
			.gpxrmDefaults ?? {}
	);
}

/**
 * Whether the site has turned Waypoint posts on, as the server says.
 */
export function waypointsEnabled(): boolean {
	return true === siteDefaults().waypoints;
}

/**
 * Whether the server can read the links a GPX file holds, which takes PHP's
 * XMLReader extension. Taken as yes unless the server says no.
 */
function siteReadsFiles(): boolean {
	return false !== siteDefaults().waypointFiles;
}

/**
 * Whether a pasted address is one of the site's own uploads, which the server
 * takes for the Media Library file it is, by either scheme. A query or a
 * fragment makes it another address, which the server looks up in vain.
 *
 * @param url The address.
 */
function inUploads( url: string ): boolean {
	const uploads = siteDefaults().uploadsUrl;
	if ( 'string' !== typeof uploads || ! uploads ) {
		return false;
	}
	const bare = ( address: string ) => address.replace( /^https?:/i, '' );
	return (
		! /[?#]/.test( url ) &&
		bare( url ).startsWith( `${ bare( uploads ) }/` )
	);
}

/**
 * Why maps leave out the links a GPX file holds.
 *
 * @param fromLibrary Whether the file is in the Media Library.
 */
function unreadLinksNote( fromLibrary: boolean ): string {
	return fromLibrary
		? __(
				'This GPX file links waypoints to Waypoint posts itself, but this site cannot read those links, as its PHP lacks the XMLReader extension. Link them here instead.',
				'gpx-route-map'
			)
		: __(
				'This GPX file links waypoints to Waypoint posts itself, but maps only read those links from files in the Media Library. Link them here instead, or choose the file from the Media Library.',
				'gpx-route-map'
			);
}

/**
 * A post ID, or 0: a whole number above zero, as the server takes it.
 *
 * @param value Stored value.
 */
function postId( value: unknown ): number {
	if ( 'string' === typeof value && /^[0-9]{1,15}$/.test( value.trim() ) ) {
		value = Number( value.trim() );
	}
	return 'number' === typeof value && Number.isInteger( value ) && value > 0
		? value
		: 0;
}

/**
 * A waypoint's position from a key of the links, or -1 for a key that is not
 * one.
 *
 * @param key The key.
 */
function position( key: string ): number {
	const index = Number( key );
	return Number.isInteger( index ) && index >= 0 ? index : -1;
}

/**
 * A block's links, whichever shape they were stored in, keeping only well
 * formed ones.
 *
 * @param stored The block's attribute.
 * @return The links, or undefined when there are none.
 */
export function readLinks( stored: unknown ): WaypointLinkMap | undefined {
	if ( ! stored || 'object' !== typeof stored ) {
		return undefined;
	}
	const links: WaypointLinkMap = {};
	for ( const [ key, value ] of Object.entries( stored ) ) {
		const index = position( key );
		const id = postId( value );
		if ( index >= 0 && id ) {
			links[ index ] = id;
		}
	}
	return Object.keys( links ).length ? links : undefined;
}

/**
 * The posts a block adds to its map, keeping only well formed IDs, each once.
 *
 * @param stored The block's attribute.
 */
export function readAdded( stored: unknown ): number[] {
	return Array.isArray( stored )
		? Array.from( new Set( stored.map( postId ).filter( Boolean ) ) )
		: [];
}

/**
 * A new list of added posts with one added or removed.
 *
 * @param added Current list.
 * @param post  The post.
 * @param on    Whether to add it, at the end, or remove it.
 * @return The new list, or undefined when none are left, so the attribute
 *         goes back to unset rather than an empty list.
 */
export function withAdded(
	added: number[],
	post: number,
	on: boolean
): number[] | undefined {
	const next = added.filter( ( id ) => id !== post );
	if ( on ) {
		next.push( post );
	}
	return next.length ? next : undefined;
}

/**
 * A new link map with one waypoint's link changed.
 *
 * @param links Current links.
 * @param index The waypoint's position in the file.
 * @param post  The post to link, or 0 to drop the link.
 * @return The new links, or undefined when none are left, so the attribute
 *         goes back to unset rather than an empty object.
 */
export function withLink(
	links: WaypointLinkMap | undefined,
	index: number,
	post: number
): WaypointLinkMap | undefined {
	const next: WaypointLinkMap = { ...links };
	if ( post > 0 ) {
		next[ index ] = post;
	} else {
		delete next[ index ];
	}
	return Object.keys( next ).length ? next : undefined;
}

/**
 * The attributes that drop a block's links, which go by each waypoint's
 * position in the file: none while it has none, or when the file stays.
 *
 * @param stored      The block's links.
 * @param fileChanged Whether the block now shows another file.
 */
export function droppedLinks(
	stored: StoredWaypointLinks | undefined,
	fileChanged = true
): { waypointPosts?: undefined } {
	return stored && fileChanged ? { waypointPosts: undefined } : {};
}

/**
 * Text from markup, as the map shows a post's title: tags dropped and
 * entities decoded. Parsed in a document of its own, which runs no script
 * and loads nothing.
 *
 * @param html Markup, such as a rendered title.
 */
function textOf( html: string ): string {
	return (
		new window.DOMParser().parseFromString( html, 'text/html' ).body
			.textContent ?? ''
	).trim();
}

/**
 * A post's title as the list shows it.
 *
 * @param post The post.
 */
function titleOf( post: WaypointRecord ): string {
	const title = textOf( post.title?.rendered ?? '' );
	const named =
		title ||
		sprintf(
			/* translators: %d: a Waypoint post's ID. */
			__( '#%d (no title)', 'gpx-route-map' ),
			post.id
		);
	if ( 'publish' === post.status ) {
		return named;
	}
	return sprintf(
		/* translators: %s: a Waypoint post's title. */
		__( '%s (not published)', 'gpx-route-map' ),
		named
	);
}

/**
 * A post the list cannot show: deleted, private, or a draft of someone else.
 *
 * @param id The post's ID.
 */
function notFound( id: number ): string {
	return sprintf(
		/* translators: %d: a Waypoint post's ID. */
		__( '#%d (not found)', 'gpx-route-map' ),
		id
	);
}

/**
 * The Waypoint posts the panel offers, while they load and once they have.
 */
interface WaypointChoices {
	/** The posts by ID, or undefined while they load. */
	byId: Map< number, WaypointRecord > | undefined;
	/** The posts, as choices of a select. */
	choices: { label: string; value: string }[];
	/** Lists the posts again, with any added since. */
	refresh: () => void;
}

/**
 * The Waypoint posts the panel offers.
 */
function useWaypointChoices(): WaypointChoices {
	const posts = useSelect( ( select ) => {
		const core = select( 'core' ) as {
			getEntityRecords: (
				kind: string,
				name: string,
				query: Record< string, unknown >
			) => WaypointRecord[] | null;
		};
		return core.getEntityRecords( 'postType', WAYPOINT_POST_TYPE, QUERY );
	}, [] );

	const { invalidateResolution } = useDispatch( 'core' ) as {
		invalidateResolution: ( selector: string, args: unknown[] ) => void;
	};
	const refresh = useCallback(
		() =>
			invalidateResolution( 'getEntityRecords', [
				'postType',
				WAYPOINT_POST_TYPE,
				QUERY,
			] ),
		[ invalidateResolution ]
	);

	const list = posts ?? [];
	return {
		byId: posts
			? new Map( list.map( ( post ) => [ post.id, post ] ) )
			: undefined,
		choices: list.map( ( post ) => ( {
			label: titleOf( post ),
			value: String( post.id ),
		} ) ),
		refresh,
	};
}

/**
 * Call refresh each time the user comes back to this tab, while watching.
 *
 * @param watching Whether to.
 * @param refresh  What to call.
 */
function useRefreshOnReturn( watching: boolean, refresh: () => void ): void {
	useEffect( () => {
		if ( ! watching ) {
			return undefined;
		}
		const onVisible = () => {
			if ( 'visible' === document.visibilityState ) {
				refresh();
			}
		};
		document.addEventListener( 'visibilitychange', onVisible );
		return () =>
			document.removeEventListener( 'visibilitychange', onVisible );
	}, [ watching, refresh ] );
}

/**
 * What a waypoint shows while the block links it to nothing: the post the
 * file links it to, when the map reads the file's links.
 *
 * @param waypoint  The waypoint.
 * @param readsFile Whether the map reads the file's links.
 * @param byId      The posts by ID, or undefined while they load.
 */
function fileLinkLabel(
	waypoint: Waypoint,
	readsFile: boolean,
	byId: WaypointChoices[ 'byId' ]
): string {
	if ( ! readsFile || ! waypoint.postId ) {
		return __( 'None', 'gpx-route-map' );
	}
	const post = byId?.get( waypoint.postId );
	return sprintf(
		/* translators: %s: the Waypoint post a GPX file links a waypoint to. */
		__( 'As in the GPX file: %s', 'gpx-route-map' ),
		post ? titleOf( post ) : notFound( waypoint.postId )
	);
}

/**
 * Whether the file links some waypoints itself while the map leaves those
 * links out: the file is not in the Media Library, or the site cannot read
 * it.
 *
 * @param waypoints The waypoints of the file.
 * @param readsFile Whether the map reads the file's links.
 */
function ignoresFileLinks(
	waypoints: Waypoint[] | undefined,
	readsFile: boolean
): boolean {
	return (
		! readsFile &&
		( waypoints ?? [] ).some( ( waypoint ) => waypoint.postId )
	);
}

/**
 * The select that links one waypoint to a post.
 *
 * @param props           Props.
 * @param props.waypoint  The waypoint.
 * @param props.linked    The post the block links it to, or 0.
 * @param props.posts     The posts the panel offers.
 * @param props.readsFile Whether the map reads the file's links.
 * @param props.onChange  Called with the post to link, or 0 for none.
 */
function WaypointSelect( {
	waypoint,
	linked,
	posts,
	readsFile,
	onChange,
}: {
	waypoint: Waypoint;
	linked: number;
	posts: WaypointChoices;
	readsFile: boolean;
	onChange: ( post: number ) => void;
} ) {
	// Linked before it was deleted or made private.
	const missing =
		linked > 0 && posts.byId && ! posts.byId.has( linked )
			? [ { label: notFound( linked ), value: String( linked ) } ]
			: [];
	return (
		<SelectControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ sprintf(
				/* translators: 1: the waypoint's number in the GPX file, 2: its name. */
				__( '%1$d. %2$s', 'gpx-route-map' ),
				waypoint.index + 1,
				waypoint.name
			) }
			value={ linked ? String( linked ) : '' }
			options={ [
				{
					label: fileLinkLabel( waypoint, readsFile, posts.byId ),
					value: '',
				},
				...posts.choices,
				...missing,
			] }
			onChange={ ( value ) => onChange( Number( value ) || 0 ) }
		/>
	);
}

/**
 * The posts the map shows at a waypoint of the file: the block's links, and
 * the file's where the map reads them and the block links nothing there.
 *
 * @param waypoints The waypoints of the file.
 * @param links     The block's links.
 * @param readsFile Whether the map reads the file's links.
 */
function linkedPosts(
	waypoints: Waypoint[] | undefined,
	links: WaypointLinkMap | undefined,
	readsFile: boolean
): number[] {
	const fromFile = readsFile
		? ( waypoints ?? [] )
				.filter( ( waypoint ) => ! links?.[ waypoint.index ] )
				.map( ( waypoint ) => waypoint.postId )
				.filter( Boolean )
		: [];
	return [ ...Object.values( links ?? {} ), ...fromFile ];
}

/**
 * What an added post's row says about where it will show: why it will not,
 * or how far it lies from the route, when it lies outside the route's box.
 * In kilometres whatever the site's units, as it is a check for the author.
 *
 * @param post The post, if the panel found it.
 * @param box  The box of the file's track, once it has been read.
 */
function addedNote(
	post: WaypointRecord | undefined,
	box: Bounds | undefined
): string {
	if ( ! post ) {
		return '';
	}
	const coords = post.gpxrm_coords;
	if ( ! coords ) {
		return __(
			'Not shown on the map until it has coordinates.',
			'gpx-route-map'
		);
	}
	const km = box ? kmFromBox( box, coords.lat, coords.lon ) : 0;
	if ( ! box || ! km ) {
		return '';
	}
	const distance = Math.max( 1, Math.round( km ) );
	return km > reachKm( box )
		? sprintf(
				/* translators: %d: a distance in kilometres. */
				__(
					'%d km from the route, so the map does not open on it',
					'gpx-route-map'
				),
				distance
			)
		: sprintf(
				/* translators: %d: a distance in kilometres. */
				__( '%d km from the route', 'gpx-route-map' ),
				distance
			);
}

/**
 * The posts added to the map, and the select that adds another.
 *
 * @param props          Props.
 * @param props.added    The posts added to the map.
 * @param props.linked   The posts the map shows at a waypoint of the file.
 * @param props.posts    The posts the panel offers.
 * @param props.box      The box of the file's track, once it has been read.
 * @param props.onChange Called with the new list.
 */
function AddedWaypoints( {
	added,
	linked,
	posts,
	box,
	onChange,
}: {
	added: number[];
	linked: number[];
	posts: WaypointChoices;
	box: Bounds | undefined;
	onChange: ( added: number[] | undefined ) => void;
} ) {
	const { byId } = posts;
	if ( ! byId ) {
		return null;
	}
	const placed = posts.choices.filter(
		( { value } ) => byId.get( Number( value ) )?.gpxrm_coords
	);
	const offered = placed.filter(
		( { value } ) => ! [ ...added, ...linked ].includes( Number( value ) )
	);
	return (
		<>
			<h3 className="gpxrm-added-heading">
				{ __( 'Added waypoints', 'gpx-route-map' ) }
			</h3>
			<p>
				{ __(
					'Waypoints shown at their own coordinates, with or without a matching waypoint in the GPX file.',
					'gpx-route-map'
				) }
			</p>
			{ !! added.length && (
				<ul className="gpxrm-added-list">
					{ added.map( ( id ) => {
						const post = byId.get( id );
						const title = post ? titleOf( post ) : notFound( id );
						const note = addedNote( post, box );
						return (
							<li key={ id }>
								<span className="gpxrm-added-title">
									{ title }
								</span>
								{ note && (
									<span className="gpxrm-added-note">
										{ note }
									</span>
								) }
								<Button
									variant="link"
									isDestructive
									label={ sprintf(
										/* translators: %s: a Waypoint post's title. */
										__( 'Remove %s', 'gpx-route-map' ),
										title
									) }
									onClick={ () =>
										onChange(
											withAdded( added, id, false )
										)
									}
								>
									{ __( 'Remove', 'gpx-route-map' ) }
								</Button>
							</li>
						);
					} ) }
				</ul>
			) }
			{ !! offered.length && (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Add to this map', 'gpx-route-map' ) }
					value=""
					options={ [
						{
							label: __( 'Choose a waypoint', 'gpx-route-map' ),
							value: '',
						},
						...offered,
					] }
					onChange={ ( value ) =>
						onChange( withAdded( added, Number( value ), true ) )
					}
				/>
			) }
			{ ! placed.length && (
				<p>
					{ __(
						"No waypoint has coordinates yet. Add them on a Waypoint's screen.",
						'gpx-route-map'
					) }
				</p>
			) }
		</>
	);
}

interface Props {
	/** The block's GPX file, once it has been read. */
	file: ParsedGpx | undefined;
	/** The block's links. */
	links: WaypointLinkMap | undefined;
	/** The posts the block adds to its map. */
	added: number[];
	/**
	 * Whether the file is in the Media Library, the only place the server
	 * reads the links a GPX file holds.
	 */
	fromLibrary: boolean;
	/** Called with the new links. */
	onChange: ( links: WaypointLinkMap | undefined ) => void;
	/** Called with the new list of added posts. */
	onAddedChange: ( added: number[] | undefined ) => void;
}

/**
 * The Waypoints panel.
 *
 * @param props               Props.
 * @param props.file          The block's GPX file, once it has been read.
 * @param props.links         The block's links.
 * @param props.added         The posts the block adds to its map.
 * @param props.fromLibrary   Whether the file is in the Media Library.
 * @param props.onChange      Called with the new links.
 * @param props.onAddedChange Called with the new list of added posts.
 */
export function WaypointLinks( {
	file,
	links,
	added,
	fromLibrary,
	onChange,
	onAddedChange,
}: Props ) {
	const posts = useWaypointChoices();
	const waypoints = file?.waypoints;
	// Not on each render: a track can have many thousands of points.
	const box = useMemo(
		() => file && computeBoundsFromCoords( file.coords ),
		[ file ]
	);
	// "Add a waypoint" opens a tab of its own; opening the panel, or coming
	// back to the tab while it is open, lists what was added there.
	const [ open, setOpen ] = useState( false );
	useRefreshOnReturn( open, posts.refresh );

	// Links in a file elsewhere, or in any file on a site that cannot read
	// them, are never read, so those waypoints are unlinked as far as the map
	// goes.
	const readsFile = fromLibrary && siteReadsFiles();

	return (
		<PanelBody
			title={ __( 'Waypoints', 'gpx-route-map' ) }
			initialOpen={ false }
			onToggle={ ( opened: boolean ) => {
				setOpen( opened );
				if ( opened ) {
					posts.refresh();
				}
			} }
		>
			<p>
				{ __(
					'Link a waypoint of the GPX file to a Waypoint post to show its description, photo or icon. Only published waypoints show on the map.',
					'gpx-route-map'
				) }
			</p>
			{ ignoresFileLinks( waypoints, readsFile ) && (
				<p>{ unreadLinksNote( fromLibrary ) }</p>
			) }
			{ undefined !== waypoints && ! waypoints.length && (
				<p>
					{ __( 'This GPX file has no waypoints.', 'gpx-route-map' ) }
				</p>
			) }
			{ ( waypoints ?? [] ).map( ( waypoint ) => (
				<WaypointSelect
					key={ waypoint.index }
					waypoint={ waypoint }
					linked={ links?.[ waypoint.index ] ?? 0 }
					posts={ posts }
					readsFile={ readsFile }
					onChange={ ( post ) =>
						onChange( withLink( links, waypoint.index, post ) )
					}
				/>
			) ) }
			<AddedWaypoints
				added={ added }
				linked={ linkedPosts( waypoints, links, readsFile ) }
				posts={ posts }
				box={ box }
				onChange={ onAddedChange }
			/>
			<p>
				<ExternalLink href="post-new.php?post_type=gpxrm_waypoint">
					{ __( 'Add a waypoint', 'gpx-route-map' ) }
				</ExternalLink>
			</p>
		</PanelBody>
	);
}

/**
 * The Waypoints panel, while the block has a GPX file and the site has
 * turned Waypoint posts on.
 *
 * @param props                           Props.
 * @param props.shown                     Whether the block has a GPX file.
 * @param props.file                      The block's GPX file, once read.
 * @param props.attributes                The block's attributes.
 * @param props.attributes.gpxId          Its Media Library file, if any.
 * @param props.attributes.gpxUrl         Its address.
 * @param props.attributes.waypointPosts  Its links, as stored.
 * @param props.attributes.addedWaypoints The posts it adds, as stored.
 * @param props.setAttributes             Sets the block's attributes.
 */
export function WaypointsPanel( {
	shown,
	file,
	attributes,
	setAttributes,
}: {
	shown: boolean;
	file: ParsedGpx | undefined;
	attributes: {
		gpxId?: number;
		gpxUrl?: string;
		waypointPosts?: StoredWaypointLinks;
		addedWaypoints?: number[];
	};
	setAttributes: ( next: {
		waypointPosts?: WaypointLinkMap | undefined;
		addedWaypoints?: number[] | undefined;
	} ) => void;
} ) {
	if ( ! shown || ! waypointsEnabled() ) {
		return null;
	}
	return (
		<WaypointLinks
			file={ file }
			links={ readLinks( attributes.waypointPosts ) }
			added={ readAdded( attributes.addedWaypoints ) }
			fromLibrary={
				!! attributes.gpxId || inUploads( attributes.gpxUrl ?? '' )
			}
			onChange={ ( links ) => setAttributes( { waypointPosts: links } ) }
			onAddedChange={ ( added ) =>
				setAttributes( { addedWaypoints: added } )
			}
		/>
	);
}
