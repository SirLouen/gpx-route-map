/**
 * What Waypoint posts change about a map's waypoints, as the server says.
 */

import type { WaypointPhoto, WaypointPost } from './types';

const COLOR = /^#(?:[0-9a-f]{3}){1,2}$/i;
const ICON = /^[a-z0-9-]+$/;

/**
 * A string, or '' for anything else.
 *
 * @param value Value.
 */
function text( value: unknown ): string {
	return 'string' === typeof value ? value : '';
}

/**
 * An http(s) address, or ''.
 *
 * @param value Value.
 */
function httpUrl( value: unknown ): string {
	const url = text( value );
	if ( ! url ) {
		return '';
	}
	try {
		const { protocol } = new URL( url, window.location.href );
		return 'http:' === protocol || 'https:' === protocol ? url : '';
	} catch {
		return '';
	}
}

/**
 * A srcset whose every candidate is an http(s) address, or ''.
 *
 * @param value Value.
 */
function srcset( value: unknown ): string {
	const set = text( value ).trim();
	if (
		! set ||
		! set
			.split( ',' )
			.every( ( candidate ) =>
				httpUrl( candidate.trim().split( /\s+/ )[ 0 ] )
			)
	) {
		return '';
	}
	return set;
}

/**
 * The fields that have a value: a string that is not empty, or an object.
 *
 * @param fields Fields, some of them without a value.
 */
function filled< T extends Record< string, string | object | undefined > >(
	fields: T
): Partial< T > {
	return Object.fromEntries(
		Object.entries( fields ).filter( ( [ , value ] ) => value )
	) as Partial< T >;
}

/**
 * A size in pixels, or 0.
 *
 * @param value Value.
 */
function pixels( value: unknown ): number {
	return 'number' === typeof value && Number.isInteger( value ) && value > 0
		? value
		: 0;
}

/**
 * A popup photo, or undefined for one without an http(s) address.
 *
 * @param raw Photo as decoded from JSON.
 */
function readPhoto( raw: unknown ): WaypointPhoto | undefined {
	if ( ! raw || 'object' !== typeof raw ) {
		return undefined;
	}
	const photo = raw as Record< string, unknown >;
	const src = httpUrl( photo.src );
	if ( ! src ) {
		return undefined;
	}
	return {
		src,
		width: pixels( photo.width ),
		height: pixels( photo.height ),
		alt: text( photo.alt ),
		...filled( { srcset: srcset( photo.srcset ) } ),
	};
}

/**
 * An icon marker, or {} for one without a well formed icon. A malformed
 * colour is left out, for the marker's own default.
 *
 * @param entry Entry as decoded from JSON.
 */
function readIcon( entry: Record< string, unknown > ): WaypointPost {
	const icon = text( entry.icon );
	if ( ! ICON.test( icon ) ) {
		return {};
	}
	const post: WaypointPost = { marker: 'icon', icon };
	const color = text( entry.color );
	if ( COLOR.test( color ) ) {
		post.color = color;
	}
	const background = text( entry.background );
	if ( COLOR.test( background ) ) {
		post.background = background;
	}
	return post;
}

/**
 * The marker an entry asks for, or {} for the usual one.
 *
 * @param entry Entry as decoded from JSON.
 */
function readMarker( entry: Record< string, unknown > ): WaypointPost {
	if ( 'icon' === entry.marker ) {
		return readIcon( entry );
	}
	const thumb = httpUrl( entry.thumb );
	return 'image' === entry.marker && thumb ? { marker: 'image', thumb } : {};
}

/**
 * One waypoint's entry, keeping only what is well formed.
 *
 * @param raw Entry as decoded from JSON.
 */
function readPost( raw: unknown ): WaypointPost | null {
	if ( ! raw || 'object' !== typeof raw ) {
		return null;
	}
	const entry = raw as Record< string, unknown >;
	return {
		...filled( {
			name: text( entry.name ).trim(),
			desc: text( entry.desc ).trim(),
			photo: readPhoto( entry.photo ),
		} ),
		...readMarker( entry ),
	};
}

/**
 * The map's entries, as decoded from JSON, or null for none.
 *
 * @param raw The map's attribute.
 */
function decode( raw: string | undefined ): object | null {
	if ( ! raw ) {
		return null;
	}
	try {
		const decoded: unknown = JSON.parse( raw );
		return decoded && 'object' === typeof decoded ? decoded : null;
	} catch {
		return null;
	}
}

/**
 * A waypoint's position from a key of the entries, or -1 for a key that is
 * not one.
 *
 * @param key The key.
 */
function position( key: string ): number {
	const index = Number( key );
	return Number.isInteger( index ) && index >= 0 ? index : -1;
}

/**
 * What Waypoint posts change about the map's waypoints, by each waypoint's
 * position in the GPX file. Only maps that link some carry the attribute.
 *
 * @param mapEl The map element.
 */
export function readWaypointPosts(
	mapEl: HTMLElement
): Record< number, WaypointPost > {
	const posts: Record< number, WaypointPost > = {};
	for ( const [ key, value ] of Object.entries(
		decode( mapEl.dataset.gpxrmWaypoints ) ?? {}
	) ) {
		const index = position( key );
		const post = readPost( value );
		if ( index >= 0 && post ) {
			posts[ index ] = post;
		}
	}
	return posts;
}
