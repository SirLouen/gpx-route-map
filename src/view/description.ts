/**
 * A Waypoint post's description in a popup: the markup its editor writes,
 * built afresh with DOM APIs so the page only ever gets elements made here.
 */

import { safeLinkUrl } from './safe-url';

/**
 * The elements a description may hold, as the server keeps them
 * (Waypoints::description_tags()). Any other element becomes its text.
 */
const TAGS = new Set( [
	'A',
	'B',
	'BR',
	'EM',
	'I',
	'LI',
	'OL',
	'P',
	'STRONG',
	'UL',
] );

/** Elements left out with all they hold, their text included. */
const DROPPED = new Set( [
	'SCRIPT',
	'STYLE',
	'TEMPLATE',
	'NOSCRIPT',
	'IFRAME',
	'OBJECT',
	'SVG',
	'MATH',
] );

/**
 * Copy a link's address, when the map may open it, and opening in a new tab
 * when its author chose so.
 *
 * @param from The link as written.
 * @param to   Its copy.
 */
function copyLink( from: Element, to: HTMLElement ): void {
	const href = safeLinkUrl( from.getAttribute( 'href' ) ?? '' );
	if ( ! href ) {
		return;
	}
	to.setAttribute( 'href', href );
	if ( '_blank' === from.getAttribute( 'target' ) ) {
		to.setAttribute( 'target', '_blank' );
		to.setAttribute( 'rel', 'noopener noreferrer' );
	}
}

/**
 * A copy of one node, keeping only what a description may hold.
 *
 * @param node A node of the parsed description.
 * @return Its copy, or null for one left out.
 */
function copyNode( node: Node ): Node | null {
	if ( Node.TEXT_NODE === node.nodeType ) {
		return document.createTextNode( node.textContent ?? '' );
	}
	if ( Node.ELEMENT_NODE !== node.nodeType ) {
		return null;
	}
	const element = node as Element;
	const tag = element.tagName.toUpperCase();
	if ( DROPPED.has( tag ) ) {
		return null;
	}
	const copy = TAGS.has( tag )
		? document.createElement( tag.toLowerCase() )
		: document.createDocumentFragment();
	if ( 'A' === tag ) {
		copyLink( element, copy as HTMLElement );
	}
	copyChildren( element, copy );
	return copy;
}

/**
 * Copy a node's children into another.
 *
 * @param from The node read.
 * @param to   The node written.
 */
function copyChildren( from: Node, to: Node ): void {
	for ( const child of Array.from( from.childNodes ) ) {
		const copy = copyNode( child );
		if ( copy ) {
			to.appendChild( copy );
		}
	}
}

/**
 * Add a description's row to a popup.
 *
 * Its markup is parsed in a document of its own, which runs no script and
 * loads nothing, and copied element by element: only the elements a
 * description may hold, and of their attributes only a link's address.
 *
 * @param parent    The popup's content.
 * @param className The row's class.
 * @param html      The description's markup.
 */
export function appendDescription(
	parent: HTMLElement,
	className: string,
	html: string
): void {
	const row = document.createElement( 'div' );
	row.className = className;
	copyChildren(
		new window.DOMParser().parseFromString( html, 'text/html' ).body,
		row
	);
	parent.appendChild( row );
}
