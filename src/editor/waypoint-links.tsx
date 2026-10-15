/**
 * The block's Waypoints panel: link each waypoint of the GPX file to a
 * Waypoint post.
 */

import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { ExternalLink, PanelBody, SelectControl } from '@wordpress/components';

import type { Waypoint } from '../view/types';

/** The post type, as registered by Waypoints::POST_TYPE. */
export const WAYPOINT_POST_TYPE = 'gpxrm_waypoint';

/**
 * Waypoints a block links to posts: post IDs by each waypoint's position
 * among the file's <wpt> elements, counting from 0.
 */
export type WaypointLinkMap = Record< string, number >;

/** The fields of a Waypoint post the panel reads. */
interface WaypointRecord {
	id: number;
	status: string;
	title?: { raw?: string; rendered?: string };
}

/**
 * Whether the site has turned Waypoint posts on, as the server says.
 */
export function waypointsEnabled(): boolean {
	return (
		true ===
		(
			window as unknown as {
				gpxrmDefaults?: { waypoints?: unknown };
			}
		 ).gpxrmDefaults?.waypoints
	);
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
 * A post's title as the list shows it.
 *
 * @param post The post.
 */
function titleOf( post: WaypointRecord ): string {
	const title = ( post.title?.raw ?? '' ).trim();
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

interface Props {
	/** The waypoints of the block's GPX file, once it has been read. */
	waypoints: Waypoint[] | undefined;
	/** The block's links. */
	links: WaypointLinkMap | undefined;
	/** Called with the new links. */
	onChange: ( links: WaypointLinkMap | undefined ) => void;
}

/**
 * The Waypoints panel.
 *
 * @param props           Props.
 * @param props.waypoints The waypoints of the block's GPX file.
 * @param props.links     The block's links.
 * @param props.onChange  Called with the new links.
 */
export function WaypointLinks( { waypoints, links, onChange }: Props ) {
	const posts = useSelect( ( select ) => {
		const core = select( 'core' ) as {
			getEntityRecords: (
				kind: string,
				name: string,
				query: Record< string, unknown >
			) => WaypointRecord[] | null;
		};
		// Drafts too, so a map can be linked to a waypoint before it is
		// published; the map shows only published ones.
		return core.getEntityRecords( 'postType', WAYPOINT_POST_TYPE, {
			per_page: -1,
			status: 'publish,future,draft,pending',
			orderby: 'title',
			order: 'asc',
			context: 'edit',
			_fields: 'id,title,status',
		} );
	}, [] );

	const byId = new Map(
		( posts ?? [] ).map( ( post ) => [ post.id, post ] )
	);
	const choices = ( posts ?? [] ).map( ( post ) => ( {
		label: titleOf( post ),
		value: String( post.id ),
	} ) );

	/**
	 * What a waypoint shows while the block links it to nothing.
	 *
	 * @param waypoint The waypoint.
	 */
	const fromFile = ( waypoint: Waypoint ): string => {
		if ( ! waypoint.postId ) {
			return __( 'None', 'gpx-route-map' );
		}
		const post = byId.get( waypoint.postId );
		return sprintf(
			/* translators: %s: the Waypoint post a GPX file links a waypoint to. */
			__( 'As in the GPX file: %s', 'gpx-route-map' ),
			post
				? titleOf( post )
				: sprintf(
						/* translators: %d: a Waypoint post's ID. */
						__( '#%d (not found)', 'gpx-route-map' ),
						waypoint.postId
					)
		);
	};

	return (
		<PanelBody
			title={ __( 'Waypoints', 'gpx-route-map' ) }
			initialOpen={ false }
		>
			<p>
				{ __(
					'Link a waypoint of the GPX file to a Waypoint post to show its description, photo or icon. Only published waypoints show on the map.',
					'gpx-route-map'
				) }
			</p>
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
											label: sprintf(
												/* translators: %d: a Waypoint post's ID. */
												__(
													'#%d (not found)',
													'gpx-route-map'
												),
												linked
											),
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
