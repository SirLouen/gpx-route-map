/**
 * A Waypoint post's settings box: the colour pickers, the icon search, and a
 * preview and an icon list that follow the choices.
 *
 * Loaded on the post's edit screen only, by Waypoint_Edit_Screen, after the
 * colour picker it depends on, a jQuery plugin. Copied to build/ as it is.
 *
 * @param {(target: Element|NodeList|EventTarget) => Object} $ jQuery.
 */
( function ( $ ) {
	const box = document.getElementById( 'gpxrm-waypoint-box' );
	if ( ! box ) {
		return;
	}
	const settings = box.querySelector( '.gpxrm-icon-settings' );
	const preview = box.querySelector( '.gpxrm-marker-preview' );
	const colors = box.querySelectorAll( '.gpxrm-color' );
	const search = box.querySelector( '.gpxrm-icon-search' );

	/**
	 * The value of the checked radio of a group.
	 *
	 * @param {string} name The group's name.
	 * @return {string} Its value, or '' when none is checked.
	 */
	function checked( name ) {
		const input = box.querySelector( 'input[name="' + name + '"]:checked' );
		return input ? input.value : '';
	}

	/**
	 * Scroll the icon list to the chosen icon.
	 */
	function reveal() {
		const picked = box.querySelector( '.gpxrm-icon-choice input:checked' );
		if ( picked ) {
			box.querySelector( '.gpxrm-icons' ).scrollTop =
				picked.parentNode.offsetTop - 6;
		}
	}

	/**
	 * Show the icon settings while the icon marker is chosen. The icon is
	 * kept while another marker is chosen, so on the way back show it rather
	 * than the start of the list.
	 */
	function toggleSettings() {
		const wasHidden = settings.hidden;
		settings.hidden = 'icon' !== checked( 'gpxrm_marker' );
		if ( wasHidden && ! settings.hidden ) {
			reveal();
		}
	}

	/**
	 * Draw the preview with the chosen icon and colours.
	 *
	 * @param {HTMLInputElement} [changed] A colour field the picker is
	 *                                     changing, whose field is not
	 *                                     updated yet.
	 * @param {string}           [value]   Its new colour.
	 */
	function drawPreview( changed, value ) {
		preview.querySelector( '.dashicons' ).className =
			'dashicons dashicons-' + ( checked( 'gpxrm_icon' ) || 'location' );
		colors.forEach( ( input ) => {
			const color = input === changed ? value : input.value;
			const property =
				'gpxrm_icon_color' === input.name ? 'color' : 'backgroundColor';
			preview.style[ property ] =
				color || input.getAttribute( 'data-default-color' );
		} );
	}

	/**
	 * Follow the choices.
	 *
	 * @param {HTMLInputElement} [changed] See drawPreview().
	 * @param {string}           [value]   See drawPreview().
	 */
	function sync( changed, value ) {
		toggleSettings();
		if ( preview ) {
			drawPreview( changed, value );
		}
	}

	/**
	 * Show only the icons whose name has the search text in it.
	 */
	function filterIcons() {
		const query = search.value.trim().toLowerCase();
		box.querySelectorAll( '.gpxrm-icon-choice' ).forEach( ( choice ) => {
			choice.hidden =
				'' !== query &&
				! choice.getAttribute( 'data-icon' ).includes( query );
		} );
	}

	/**
	 * The colour field of the picker an event happened in.
	 *
	 * @param {Event} event The picker's event.
	 * @return {HTMLInputElement} The field.
	 */
	function pickerField( event ) {
		return $( event.target )
			.closest( '.wp-picker-container' )
			.find( '.gpxrm-color' )[ 0 ];
	}

	box.addEventListener( 'change', () => sync() );

	// Enter would submit the post form, saving whatever is half done.
	search.addEventListener( 'keydown', ( event ) => {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
		}
	} );
	search.addEventListener( 'input', filterIcons );

	// The picker changes its field only after this fires, so the new
	// colour comes from the event.
	$( colors ).wpColorPicker( {
		change: ( event, ui ) => sync( event.target, ui.color.toString() ),
		clear: ( event ) => sync( pickerField( event ), '' ),
	} );
	// The picker hides its field until opened, so its button is named by
	// the field's label.
	colors.forEach( ( input ) => {
		$( input )
			.closest( '.wp-picker-container' )
			.find( '.wp-color-result' )
			.attr(
				'aria-describedby',
				input.getAttribute( 'aria-labelledby' )
			);
	} );

	if ( ! settings.hidden ) {
		reveal();
	}
	sync();
} )( window.jQuery );
