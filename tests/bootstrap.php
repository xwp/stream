<?php
/**
 * Tests bootstrapper
 *
 * @author X-Team <x-team.com>
 * @author Jonathan Bardo <jonathan.bardo@x-team.com>
 */

require_once getenv( 'WP_TESTS_DIR' ) . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function() {
		// Manually load plugin
		require dirname( dirname( __FILE__ ) ) . '/stream.php';
		//Call Activate plugin function
		WP_Stream::install();
	}
);

// Removes sql tables on shutdown
// Do this action last
tests_add_filter(
		'shutdown',
	function() {
		//@Todo Trigger uninstall function
	},
	999999
);

require getenv( 'WP_TESTS_DIR' ) . '/includes/bootstrap.php';
