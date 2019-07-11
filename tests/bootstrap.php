<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( empty( $_tests_dir ) || ! file_exists( $_tests_dir . '/includes' ) ) {
	trigger_error( 'Unable to locate WP_TESTS_DIR', E_USER_ERROR );
}

// Use in code to trigger custom actions
define( 'WP_STREAM_TESTS', true );
define( 'WP_STREAM_DEV_DEBUG', true );

// @see https://core.trac.wordpress.org/browser/trunk/tests/phpunit/includes/functions.php
require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function() {
		// Manually load the plugin.
		require dirname( __DIR__ ) . '/stream.php';
	}
);

// @see https://core.trac.wordpress.org/browser/trunk/tests/phpunit/includes/bootstrap.php
require $_tests_dir . '/includes/bootstrap.php';

require __DIR__ . '/testcase.php';

// Base class for future tests
require __DIR__ . '/tests/test-class-alert-trigger.php';
