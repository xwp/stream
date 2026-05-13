/**
 * ESLint flat config.
 *
 * Migrated from .eslintrc.js + .eslintignore for ESLint 9 / @wordpress/scripts 32.
 */

/**
 * External dependencies
 */
const globals = require( 'globals' );

/**
 * WordPress dependencies
 */
const wpPlugin = require( '@wordpress/eslint-plugin' );

const recommendedWithFormatting = wpPlugin.configs[ 'recommended-with-formatting' ];

module.exports = [
	{
		ignores: [
			'vendor/**',
			'node_modules/**',
			'build/**',
			'local/public/**',
			'dist/**',
		],
	},
	...recommendedWithFormatting,
	{
		languageOptions: {
			globals: {
				...globals.browser,
			},
		},
		settings: {
			// Use node resolver to avoid "typescript with invalid interface" from eslint-import-resolver-typescript.
			'import/resolver': {
				node: {
					extensions: [ '.js', '.jsx', '.mjs', '.scss' ],
				},
			},
		},
		rules: {
			'@wordpress/no-global-event-listener': 'off',
			'jsdoc/check-indentation': 'error',
			'@wordpress/dependency-group': 'error',
			'import/order': [
				'error',
				{
					groups: [
						'builtin',
						[ 'external', 'unknown' ],
						'internal',
						'parent',
						'sibling',
						'index',
					],
				},
			],
			'jsdoc/require-param': 'off',
			'jsdoc/require-param-type': 'off',
			'jsdoc/check-param-names': 'off',
		},
	},
];
