<?php
/**
 * PHPUnit config for the wp-env tests-cli container.
 *
 * Isolated from the running tests site (table prefix `wptests_`, domain
 * example.org, no pre-set MULTISITE). Used when
 * WP_TESTS_CONFIG_FILE_PATH points here from tests/bootstrap.php.
 *
 * @package WP_Stream
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
 */

define( 'DB_NAME', 'tests-wordpress' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'password' );
define( 'DB_HOST', 'tests-mysql' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define(
	'WP_DEBUG',
	'yes' === getenv( 'WP_STREAM_TEST_DEBUG' )
);

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );

define( 'ABSPATH', '/var/www/html/' );
