/**
 * Load the front-end view module into the editor canvas.
 */

const loads = new WeakMap< Document, Promise< void > >();

/**
 * Load the view module into a document, once.
 *
 * The editor canvas is usually an iframe with a document of its own, and
 * WordPress gives that document no script modules, so the editor adds the view
 * module itself. It then runs in the canvas's own window, as it does on the
 * front end.
 *
 * Resolves once the module has run. A module script fires `load` even when
 * the browser already ran that module, so this also works in a canvas that
 * WordPress gave the module to.
 *
 * @param doc The canvas document.
 * @param src The view module's URL.
 */
export function loadViewModule( doc: Document, src: string ): Promise< void > {
	const pending = loads.get( doc );
	if ( pending ) {
		return pending;
	}

	const loading = new Promise< void >( ( resolve, reject ) => {
		const script = doc.createElement( 'script' );
		script.type = 'module';
		script.src = src;
		script.addEventListener( 'load', () => resolve(), { once: true } );
		script.addEventListener(
			'error',
			() => {
				// Let the next preview try again rather than share the failure.
				loads.delete( doc );
				script.remove();
				reject( new Error( `Could not load ${ src }` ) );
			},
			{ once: true }
		);
		doc.head.appendChild( script );
	} );
	loads.set( doc, loading );
	return loading;
}
