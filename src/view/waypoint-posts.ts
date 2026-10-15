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
 * One waypoint's entry, keeping only what is well formed.
 *
 * @param raw Entry as decoded from JSON.
 */
function readPost( raw: unknown ): WaypointPost | null {
	if ( ! raw || 'object' !== typeof raw ) {
		return null;
	}
	const entry = raw as Record< string, unknown >;
	const post: WaypointPost = {};

	const name = text( entry.name ).trim();
	if ( name ) {
		post.name = name;
	}
	const desc = text( entry.desc ).trim();
	if ( desc ) {
		post.desc = desc;
	}

	if ( entry.photo && 'object' === typeof entry.photo ) {
		const photo = entry.photo as Record< string, unknown >;
		const src = httpUrl( photo.src );
		if ( src ) {
			const read: WaypointPhoto = {
				src,
				width: pixels( photo.width ),
				height: pixels( photo.height ),
				alt: text( photo.alt ),
			};
			const set = srcset( photo.srcset );
			if ( set ) {
				read.srcset = set;
			}
			post.photo = read;
		}
	}

	const thumb = httpUrl( entry.thumb );
	if ( 'image' === entry.marker && thumb ) {
		post.marker = 'image';
		post.thumb = thumb;
	} else if ( 'icon' === entry.marker && ICON.test( text( entry.icon ) ) ) {
		post.marker = 'icon';
		post.icon = text( entry.icon );
		// Left out when malformed, for the marker's own defaults.
		const color = text( entry.color );
		if ( COLOR.test( color ) ) {
			post.color = color;
		}
		const background = text( entry.background );
		if ( COLOR.test( background ) ) {
			post.background = background;
		}
	}

	return post;
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
	const raw = mapEl.dataset.gpxrmWaypoints;
	if ( ! raw ) {
		return {};
	}

	let decoded: unknown;
	try {
		decoded = JSON.parse( raw );
	} catch {
		return {};
	}
	if ( ! decoded || 'object' !== typeof decoded ) {
		return {};
	}

	const posts: Record< number, WaypointPost > = {};
	for ( const [ key, value ] of Object.entries( decoded ) ) {
		const index = Number( key );
		const post = readPost( value );
		if ( Number.isInteger( index ) && index >= 0 && post ) {
			posts[ index ] = post;
		}
	}
	return posts;
}
