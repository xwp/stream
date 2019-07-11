<?php

namespace WP_Stream;

# Include Composer autoloader for dependency resolution.
if ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';
}

// Use in code to trigger custom actions
define( 'WP_STREAM_TESTS', true );
define( 'WP_STREAM_DEV_DEBUG', true );

$_tests_dir = getenv('WP_TESTS_DIR');
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib/';
}
require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function() {
		// Manually load plugin
		require dirname( dirname( __FILE__ ) ) . '/stream.php';
	}
);

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';
require dirname( __FILE__ ) . '/testcase.php';

// Base class for future tests
require dirname( __FILE__ ) . '/tests/test-class-alert-trigger.php';
