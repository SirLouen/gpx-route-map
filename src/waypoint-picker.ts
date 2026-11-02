/**
 * The entry point of the map on a Waypoint post's screen. A script module of
 * its own, sharing MapLibre and the map code with the front end's, so that
 * visitors never load it.
 */

import { initPicker } from './admin/coordinate-picker';
import { loadMapLibre } from './view/load-maplibre';

const picker = document.querySelector< HTMLElement >( '.gpxrm-picker' );

if ( picker ) {
	initPicker( picker, loadMapLibre );
}
