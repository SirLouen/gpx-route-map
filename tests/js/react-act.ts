/**
 * Drive React through act() in a test file: import it before rendering.
 *
 * act() is what keeps React's work off its scheduler. The scheduler took the
 * real setImmediate before the timers were faked and yields every 5 ms, so how
 * soon it applied an update depended on the load. A file using this renders,
 * unmounts and dispatches the events that update state inside act(), and
 * waits with settle().
 *
 * It also tells React the file uses act(), so that an update made outside
 * act() warns, and turns that warning into a failure: vitest hides the console
 * of passing tests. React's warning about an update loop throws, since act()
 * runs the loop synchronously and the run would hang rather than fail.
 */

import { afterEach, beforeEach, expect, vi } from 'vitest';
import { act } from 'react';

export { act };

vi.stubGlobal( 'IS_REACT_ACT_ENVIRONMENT', true );

let outsideAct: string[] = [];
// eslint-disable-next-line no-console
const logError = console.error.bind( console );

vi.spyOn( console, 'error' ).mockImplementation( ( ...args: unknown[] ) => {
	const message = String( args[ 0 ] );
	if ( message.includes( 'Maximum update depth exceeded' ) ) {
		throw new Error( message );
	}
	if ( message.includes( 'not wrapped in act(' ) ) {
		outsideAct.push( message );
		return;
	}
	logError( ...args );
} );

beforeEach( () => {
	outsideAct = [];
} );

afterEach( () => {
	expect( outsideAct, 'React updates outside act()' ).toEqual( [] );
} );

/**
 * Let settled promises run their callbacks and React apply what they set:
 * act() runs whatever React queued, waits a real macrotask, and repeats until
 * nothing more is queued, without touching a faked clock.
 */
export const settle = () => act( async () => {} );
