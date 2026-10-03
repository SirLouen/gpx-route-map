/**
 * ESLint flat config.
 *
 * Replaces .eslintrc.js, which ESLint 10 no longer reads. wp-scripts falls
 * back to its own bundled config when a project has no eslint.config.*, so
 * the old file had quietly stopped applying - what linted the code was that
 * fallback. This config keeps that same behaviour and makes it explicit,
 * which is why the output is unchanged by the migration.
 */

const wpPlugin = require( '@wordpress/eslint-plugin' );

module.exports = [
	// Replaces .eslintignore, which ESLint 10 also dropped.
	{
		ignores: [ '**/build/**', '**/node_modules/**', '**/vendor/**' ],
	},

	...wpPlugin.configs.recommended,

	// The build script is a Node ES module, not browser code.
	{
		files: [ 'bin/**/*.mjs' ],
		languageOptions: {
			sourceType: 'module',
			globals: {
				console: 'readonly',
				process: 'readonly',
			},
		},
	},

	// No test-unit config here yet. Up to @wordpress/eslint-plugin 26 it was
	// eslint-plugin-jest, whose rules sat inert on these tests because they
	// import describe/it/expect from vitest rather than using globals. From
	// 27 it is @vitest/eslint-plugin, which would apply, but it has no `files`
	// scope of its own, so adopting it means scoping it to tests/js and
	// clearing whatever it reports. Until then, the one rule that matters
	// most, no-focused-tests, is covered anyway: vitest's allowOnly defaults
	// to !CI, so a stray .only fails the run in CI rather than passing green.
];
