<?php
/**
 * Tests bootstrapper
 *
 * @author X-Team <x-team.com>
 * @author Jonathan Bardo <jonathan.bardo@x-team.com>
 */

// Use in code to trigger custom actions
define( 'STREAM_TESTS', true );

$_tests_dir = getenv('WP_TESTS_DIR');
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib/';
}
require_once $_tests_dir . 'includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function() {
		// Manually load plugin
		require dirname( dirname( __FILE__ ) ) . '/stream.php';

		// Call Activate plugin function
		WP_Stream::install();
	}
);

// Removes all sql tables on shutdown
// Do this action last
tests_add_filter(
		'shutdown',
	function() {
		// Empty all tables so we don't deal with leftovers
		drop_tables();
	},
	999999
);

require $_tests_dir . '/includes/bootstrap.php';
require dirname( __FILE__ ) . '/testcase.php';
