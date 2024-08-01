const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: './ui/js/admin.js',
		alerts: './ui/js/alerts.js',
		'alerts-list': './ui/js/alerts-list.js',
		exclude: './ui/js/exclude.js',
		global: './ui/js/global.js',
		'live-updates': './ui/js/live-updates.js',
		settings: './ui/js/settings.js',
		'wpseo-admin': './ui/js/wpseo-admin.js',
	},
};
