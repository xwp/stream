<?php
/**
 * Config file used only for phpunit tests inside Docker.
 *
 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
 * phpcs:disable Squiz.Commenting.FileComment.MissingPackageTag
 */

// Rely on Composer autoload to pull in tooling helpers.
require_once __DIR__ . '/wp-content/plugins/stream-src/vendor/autoload.php';

define( 'WP_DEBUG', true );

# Configured in docker-compose.yml.
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

// Keep the wp-contents outside of WP core directory.
define( 'WP_CONTENT_DIR', __DIR__ . '/wp-content' );

define( 'ABSPATH', __DIR__ . '/wp/' );

// Ensure the DB host is ready to accept connections.
$connection = new XWP\Wait_For\Tcp_Connection( DB_HOST, 3306 );

try {
	$connection->connect( 10 );
} catch ( Exception $e ) {
	trigger_error( $e->getMessage(), E_USER_ERROR ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped
}
