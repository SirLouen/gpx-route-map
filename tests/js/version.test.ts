/**
 * The version number must agree everywhere a release sets it.
 *
 * block.json's is the one that is easy to forget and costly to miss: WordPress
 * versions a block's stylesheets with it, and only uses the file's time under
 * SCRIPT_DEBUG. Without it, live sites load the block's CSS as
 * `?ver=<WordPress version>`, so after a plugin update a browser or cache can
 * keep the old stylesheet while the scripts, versioned by content hash in
 * *.asset.php, are new.
 */

import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.dirname(
	path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) )
);

/**
 * Read a file from the plugin root.
 *
 * @param file Path relative to the plugin root.
 */
function read( file: string ): string {
	return fs.readFileSync( path.join( ROOT, file ), 'utf8' );
}

describe( 'version', () => {
	const plugin = /^\s*\*\s*Version:\s*(\S+)/m.exec(
		read( 'gpx-route-map.php' )
	)?.[ 1 ];

	it( 'is set in the plugin header', () => {
		expect( plugin ).toMatch( /^\d+\.\d+\.\d+$/ );
	} );

	it( 'versions the block, so its stylesheets change with each release', () => {
		expect( JSON.parse( read( 'src/block.json' ) ).version ).toBe( plugin );
	} );

	it( 'matches the readme stable tag and package.json', () => {
		expect(
			/^Stable tag:\s*(\S+)/m.exec( read( 'readme.txt' ) )?.[ 1 ]
		).toBe( plugin );
		expect( JSON.parse( read( 'package.json' ) ).version ).toBe( plugin );
	} );
} );
