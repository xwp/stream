<?php

// Default mu-plugins directory if you haven't set it.
defined( 'WPMU_PLUGIN_DIR' ) or define( 'WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins' );

if ( is_readable( WPMU_PLUGIN_DIR . '/mercator/mercator.php' ) ) {
	require WPMU_PLUGIN_DIR . '/mercator/mercator.php';
}
