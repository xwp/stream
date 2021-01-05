<?php
/**
 * Config file used for the local development environment.
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
 */

define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'password' );
define( 'DB_HOST', 'mysql' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

// Enable a subdirectory multisite for testing purposes.
define( 'WP_ALLOW_MULTISITE', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', false );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );
$base = '/';

$table_prefix = 'wptests_';

define( 'WP_DEBUG', false );

define( 'ABSPATH', __DIR__ . '/' );

// For mercator.
define( 'SUNRISE', true );

require_once ABSPATH . 'wp-settings.php';
