/**
 * Vite-based build for the GPX Route Map plugin.
 *
 * Produces the same build/ layout that register_block_type() expects:
 *   index.js + index.asset.php            editor script (IIFE, wp.* externals)
 *   view.js + chunks + view.asset.php     front-end script module (ESM)
 *   style-index.css / index.css (+ -rtl)  compiled SCSS
 *   block.json / render.php               copied from src/
 *
 * Usage: node bin/build.mjs [--watch]
 */

import { build } from 'vite';
import * as sass from 'sass-embedded';
import rtlcss from 'rtlcss';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';

const ROOT = path.dirname( path.dirname( fileURLToPath( import.meta.url ) ) );
const SRC = path.join( ROOT, 'src' );
const OUT = path.join( ROOT, 'build' );

const camel = ( s ) => s.replace( /-([a-z])/g, ( m, c ) => c.toUpperCase() );

/**
 * Collect @wordpress/* imports across the editor source tree.
 *
 * @param {string[]} files Source files (relative to src/) to scan.
 * @return {string[]} Sorted script-handle dependencies.
 */
function wpDependencies( files ) {
	const deps = new Set( [ 'react-jsx-runtime' ] );
	for ( const file of files ) {
		const code = fs.readFileSync( path.join( SRC, file ), 'utf8' );
		for ( const m of code.matchAll( /@wordpress\/([a-z0-9-]+)/g ) ) {
			deps.add( 'wp-' + m[ 1 ] );
		}
	}
	return [ ...deps ].sort();
}

function assetPhp( dependencies, versionSource ) {
	const version = crypto
		.createHash( 'md5' )
		.update( versionSource )
		.digest( 'hex' )
		.slice( 0, 20 );
	const deps = dependencies.map( ( d ) => `'${ d }'` ).join( ', ' );
	return `<?php return array('dependencies' => array(${ deps }), 'version' => '${ version }');\n`;
}

async function buildEditor() {
	await build( {
		configFile: false,
		logLevel: 'warn',
		build: {
			outDir: OUT,
			emptyOutDir: false,
			sourcemap: false,
			minify: false,
			lib: {
				entry: path.join( SRC, 'index.tsx' ),
				formats: [ 'iife' ],
				name: 'gpxrmBlock',
				fileName: () => 'index.js',
			},
			rollupOptions: {
				external: ( id ) =>
					id.startsWith( '@wordpress/' ) ||
					'react' === id ||
					'react/jsx-runtime' === id,
				output: {
					globals: ( id ) => {
						if ( 'react/jsx-runtime' === id ) {
							return 'ReactJSXRuntime';
						}
						if ( 'react' === id ) {
							return 'React';
						}
						return 'wp.' + camel( id.replace( '@wordpress/', '' ) );
					},
				},
			},
		},
	} );

	const code = fs.readFileSync( path.join( OUT, 'index.js' ), 'utf8' );
	fs.writeFileSync(
		path.join( OUT, 'index.asset.php' ),
		assetPhp( wpDependencies( [ 'index.tsx', 'edit.tsx' ] ), code )
	);
}

async function buildView() {
	await build( {
		configFile: false,
		logLevel: 'warn',
		base: './',
		build: {
			outDir: OUT,
			emptyOutDir: false,
			sourcemap: false,
			target: 'es2020',
			modulePreload: { polyfill: false },
			rollupOptions: {
				input: { view: path.join( SRC, 'view.ts' ) },
				preserveEntrySignatures: false,
				output: {
					format: 'es',
					entryFileNames: 'view.js',
					chunkFileNames: '[name]-[hash].js',
					assetFileNames: '[name]-[hash][extname]',
				},
			},
		},
	} );

	const code = fs.readFileSync( path.join( OUT, 'view.js' ), 'utf8' );

	fs.writeFileSync(
		path.join( OUT, 'view.asset.php' ),
		assetPhp( [], code )
	);
}

function buildStyles() {
	const targets = [
		{ src: 'style.scss', out: 'style-index.css' },
		{ src: 'editor.scss', out: 'index.css' },
	];
	for ( const { src, out } of targets ) {
		const css = sass.compile( path.join( SRC, src ), {
			style: 'compressed',
		} ).css;
		fs.writeFileSync( path.join( OUT, out ), css );
		fs.writeFileSync(
			path.join( OUT, out.replace( /\.css$/, '-rtl.css' ) ),
			rtlcss.process( css )
		);
	}
}

function copyStatic() {
	for ( const file of [ 'block.json', 'render.php' ] ) {
		fs.copyFileSync( path.join( SRC, file ), path.join( OUT, file ) );
	}
}

async function buildAll() {
	const started = Date.now();
	await buildEditor();
	await buildView();
	buildStyles();
	copyStatic();
	// eslint-disable-next-line no-console
	console.log( `built in ${ Date.now() - started }ms` );
}

fs.rmSync( OUT, { recursive: true, force: true } );
fs.mkdirSync( OUT, { recursive: true } );
await buildAll();

if ( process.argv.includes( '--watch' ) ) {
	// eslint-disable-next-line no-console
	console.log( 'watching src/ …' );
	let timer = null;
	let running = false;
	fs.watch( SRC, { recursive: true }, () => {
		clearTimeout( timer );
		timer = setTimeout( async () => {
			if ( running ) {
				return;
			}
			running = true;
			try {
				await buildAll();
			} catch ( err ) {
				// eslint-disable-next-line no-console
				console.error( err.message );
			}
			running = false;
		}, 150 );
	} );
}
