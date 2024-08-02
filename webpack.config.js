/**
 * External dependencies
 */
const path = require( 'path' );
const CopyPlugin = require( 'copy-webpack-plugin' );

/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: './ui/js/admin.js',
		'admin-exclude': './ui/js/admin-exclude.js',
		'alert-type-highlight': './ui/js/alert-type-highlight.js',
		alerts: './ui/js/alerts.js',
		'alerts-list': './ui/js/alerts-list.js',
		global: './ui/js/global.js',
		'live-updates': './ui/js/live-updates.js',
		settings: './ui/js/settings.js',
		'wpseo-admin': './ui/js/wpseo-admin.js',
	},
	plugins: [
		...defaultConfig.plugins,
		new CopyPlugin( {
			patterns: [
				{
					from: 'node_modules/select2/dist',
					// Convert filenames to lowercase.
					to( { context, absoluteFilename } ) {
						const baseName = path.basename( absoluteFilename ).toLowerCase();
						const relativePath = path.relative( context, path.dirname( absoluteFilename ) );

						return path.join( 'select2', relativePath, baseName );
					},
				},
				{
					from: 'node_modules/timeago/jquery.timeago.js',
					to: 'timeago/js/jquery.timeago.js',
				},
				{
					from: 'node_modules/timeago/locales',
					// Convert filenames to lowercase.
					to( { context, absoluteFilename } ) {
						const baseName = path.basename( absoluteFilename ).toLowerCase();
						const relativePath = path.relative( context, path.dirname( absoluteFilename ) );

						return path.join( 'timeago', 'js', 'locales', relativePath, baseName );
					},
				},
			],
		} ),
	],
};
