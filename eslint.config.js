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

	// The project has no Babel config of its own, so the parser needs the
	// WordPress preset to read JSX and modern syntax.
	{
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
		},
	},

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

	// No test-unit config here, deliberately. It is built on
	// eslint-plugin-jest, which only recognises describe/it/expect as test
	// functions when they are globals; our tests import them from vitest, so
	// all 19 of its rules sit inert (verified: a file with it.only and two
	// identical titles reports nothing). Its one rule worth having,
	// no-focused-tests, is already covered - vitest's allowOnly defaults to
	// !CI, so a stray .only fails the run in CI rather than passing green.
	// If these ever need linting, the answer is @vitest/eslint-plugin.
];
