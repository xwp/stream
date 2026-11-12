/**
 * WordPress dependencies
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: './src/js/admin.js',
		'admin-exclude': './src/js/admin-exclude.js',
		'alert-type-highlight': './src/js/alert-type-highlight.js',
		alerts: './src/js/alerts.js',
		'alerts-list': './src/js/alerts-list.js',
		global: './src/js/global.js',
		'live-updates': './src/js/live-updates.js',
		settings: './src/js/settings.js',
		'wpseo-admin': './src/js/wpseo-admin.js',
	},
};
