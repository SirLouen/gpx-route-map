/**
 * The block's Waypoints panel: link each waypoint of the GPX file to a
 * Waypoint post.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { ExternalLink, PanelBody, SelectControl } from '@wordpress/components';

import type { Waypoint } from '../view/types';

/** The post type, as registered by Waypoints::POST_TYPE. */
export const WAYPOINT_POST_TYPE = 'gpxrm_waypoint';

/**
 * Waypoints a block links to posts: post IDs by each waypoint's position
 * among the file's <wpt> elements, counting from 0.
 */
export type WaypointLinkMap = Record< string, number >;

/**
 * The links as a block stores them. Saving a post for a user without the
 * unfiltered_html capability re-encodes block attributes in PHP, which
 * writes an object keyed 0, 1, 2… as a list.
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
	_fields: 'id,title,status',
};

/** The fields of a Waypoint post the panel reads. */
interface WaypointRecord {
	id: number;
	status: string;
	title?: { rendered?: string };
}

/**
 * What the server says about Waypoint posts on this site.
 */
function siteDefaults(): { waypoints?: unknown; waypointFiles?: unknown } {
	return (
		(
			window as unknown as {
				gpxrmDefaults?: {
					waypoints?: unknown;
					waypointFiles?: unknown;
				};
			}
		 ).gpxrmDefaults ?? {}
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
		const index = Number( key );
		const id = postId( value );
		if ( Number.isInteger( index ) && index >= 0 && id ) {
			links[ index ] = id;
		}
	}
	return Object.keys( links ).length ? links : undefined;
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

interface Props {
	/** The waypoints of the block's GPX file, once it has been read. */
	waypoints: Waypoint[] | undefined;
	/** The block's links. */
	links: WaypointLinkMap | undefined;
	/**
	 * Whether the file is in the Media Library, the only place the server
	 * reads the links a GPX file holds.
	 */
	fromLibrary: boolean;
	/** Called with the new links. */
	onChange: ( links: WaypointLinkMap | undefined ) => void;
}

/**
 * The Waypoints panel.
 *
 * @param props             Props.
 * @param props.waypoints   The waypoints of the block's GPX file.
 * @param props.links       The block's links.
 * @param props.fromLibrary Whether the file is in the Media Library.
 * @param props.onChange    Called with the new links.
 */
export function WaypointLinks( {
	waypoints,
	links,
	fromLibrary,
	onChange,
}: Props ) {
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
	// "Add a waypoint" opens a tab of its own; opening the panel, or coming
	// back to the tab while it is open, lists what was added there.
	const [ open, setOpen ] = useState( false );
	const refresh = useCallback(
		() =>
			invalidateResolution( 'getEntityRecords', [
				'postType',
				WAYPOINT_POST_TYPE,
				QUERY,
			] ),
		[ invalidateResolution ]
	);
	useEffect( () => {
		if ( ! open ) {
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
	}, [ open, refresh ] );

	const byId = new Map(
		( posts ?? [] ).map( ( post ) => [ post.id, post ] )
	);
	const choices = ( posts ?? [] ).map( ( post ) => ( {
		label: titleOf( post ),
		value: String( post.id ),
	} ) );

	// Links in a file elsewhere, or in any file on a site that cannot read
	// them, are never read, so those waypoints are unlinked as far as the map
	// goes.
	const readsFile = fromLibrary && siteReadsFiles();
	const ignoredLinks =
		! readsFile &&
		( waypoints ?? [] ).some( ( waypoint ) => waypoint.postId );

	/**
	 * What a waypoint shows while the block links it to nothing.
	 *
	 * @param waypoint The waypoint.
	 */
	const fromFile = ( waypoint: Waypoint ): string => {
		if ( ! readsFile || ! waypoint.postId ) {
			return __( 'None', 'gpx-route-map' );
		}
		const post = byId.get( waypoint.postId );
		return sprintf(
			/* translators: %s: the Waypoint post a GPX file links a waypoint to. */
			__( 'As in the GPX file: %s', 'gpx-route-map' ),
			post ? titleOf( post ) : notFound( waypoint.postId )
		);
	};

	return (
		<PanelBody
			title={ __( 'Waypoints', 'gpx-route-map' ) }
			initialOpen={ false }
			onToggle={ ( opened: boolean ) => {
				setOpen( opened );
				if ( opened ) {
					refresh();
				}
			} }
		>
			<p>
				{ __(
					'Link a waypoint of the GPX file to a Waypoint post to show its description, photo or icon. Only published waypoints show on the map.',
					'gpx-route-map'
				) }
			</p>
			{ ignoredLinks && <p>{ unreadLinksNote( fromLibrary ) }</p> }
			{ undefined !== waypoints && ! waypoints.length && (
				<p>
					{ __( 'This GPX file has no waypoints.', 'gpx-route-map' ) }
				</p>
			) }
			{ ( waypoints ?? [] ).map( ( waypoint ) => {
				const linked = links?.[ waypoint.index ] ?? 0;
				const missing = linked > 0 && posts && ! byId.has( linked );
				return (
					<SelectControl
						key={ waypoint.index }
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
							{ label: fromFile( waypoint ), value: '' },
							...choices,
							// Linked before it was deleted or made private.
							...( missing
								? [
										{
											label: notFound( linked ),
											value: String( linked ),
										},
									]
								: [] ),
						] }
						onChange={ ( value ) =>
							onChange(
								withLink(
									links,
									waypoint.index,
									Number( value ) || 0
								)
							)
						}
					/>
				);
			} ) }
			<p>
				<ExternalLink href="post-new.php?post_type=gpxrm_waypoint">
					{ __( 'Add a waypoint', 'gpx-route-map' ) }
				</ExternalLink>
			</p>
		</PanelBody>
	);
}
