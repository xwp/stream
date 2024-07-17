<?php
/**
 * Config file used for the local development environment.
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
 */

# Configured in docker-compose.yml.
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

$table_prefix = 'wp_';

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'JETPACK_DEV_DEBUG', true );

// Keep the wp-contents outside of WP core directory.
define( 'WP_CONTENT_DIR', __DIR__ . '/wp-content' );

define( 'ABSPATH', __DIR__ . '/wp/' );

// For mercator.
define( 'SUNRISE', true );

require_once ABSPATH . 'wp-settings.php';
