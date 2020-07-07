<?php
/**
 * Config file used only for phpunit tests inside Docker.
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
 * phpcs:disable Squiz.Commenting.FileComment.MissingPackageTag
 */

define( 'WP_DEBUG', true );

define( 'DB_NAME', 'wordpress_test' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'db_phpunit' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );

define( 'ABSPATH', __DIR__ . '/' );
