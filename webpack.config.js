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
};
