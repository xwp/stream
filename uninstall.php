<?php
/**
 * Uninstall cleanup for WP Stream.
 *
 * Removes alert configuration, plugin options, user meta, and scheduled jobs.
 * The activity log tables wp_stream and wp_streammeta are kept, log data is
 * not deleted because the removal is irreversible.
 *
 * @package WP_Stream
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

if ( ! function_exists( 'wp_stream_uninstall_delete_site_data' ) ) {

	/**
	 * Removes all Stream data stored for the current site.
	 *
	 * Runs inside the blog switch loop on multisite, so the data below is
	 * read and removed per site, and caches are invalidated on the site that
	 * owns them.
	 *
	 * @return void
	 */
	function wp_stream_uninstall_delete_site_data() {
		global $wpdb;

		// Remove all alert posts of every status. Core API keeps hooks,
		// post caches, and object caches in sync.
		$alert_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'wp_stream_alerts' )
		);
		foreach ( $alert_ids as $alert_id ) {
			wp_delete_post( (int) $alert_id, true );
		}

		// Remove all Stream options, including transients and legacy keys.
		// delete_option() invalidates the alloptions cache on every site.
		$option_names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( 'wp_stream' ) . '%',
				$wpdb->esc_like( '_transient_wp_stream' ) . '%'
			)
		);
		foreach ( $option_names as $option_name ) {
			delete_option( $option_name );
		}

		// Remove the legacy WP-Cron purge event for this site.
		wp_clear_scheduled_hook( 'wp_stream_auto_purge' );

		// Remove the recurring purge / reset jobs. WP-Cron events live in the
		// per-site cron option. Action Scheduler tables are per-site, and the
		// AS API is only loaded when another plugin or Stream itself provides
		// it, so the tables are cleaned directly as a fallback.
		$stream_scheduled_hooks = array(
			'stream_auto_purge_action',
			'stream_auto_purge_batch_action',
			'stream_auto_purge_reaper_action',
			'stream_erase_large_records_action',
		);
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( $stream_scheduled_hooks as $stream_scheduled_hook ) {
				as_unschedule_all_actions( $stream_scheduled_hook );
			}
		} else {
			foreach ( $stream_scheduled_hooks as $stream_scheduled_hook ) {
				wp_clear_scheduled_hook( $stream_scheduled_hook );
			}
		}
	}
}

if ( is_multisite() ) {
	$stream_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $stream_site_ids as $stream_site_id ) {
		switch_to_blog( (int) $stream_site_id );
		wp_stream_uninstall_delete_site_data();
		restore_current_blog();
	}

	// Network options live outside the per-site loop. delete_site_option()
	// covers wp_stream, wp_stream_network, wp_stream_db*, and any leftover
	// Stream keys that were written via update_site_option().
	$stream_network_option_names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT meta_key FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			$wpdb->esc_like( 'wp_stream' ) . '%',
			$wpdb->esc_like( '_site_transient_wp_stream' ) . '%'
		)
	);
	foreach ( $stream_network_option_names as $stream_network_option_name ) {
		delete_site_option( $stream_network_option_name );
	}
} else {
	wp_stream_uninstall_delete_site_data();
	// delete_site_option() works on single site too.
	delete_site_option( 'wp_stream_network' );
	// Network options that use update_site_option outside the settings class.
	delete_site_option( 'wp_stream_db' );
	delete_site_option( 'wp_stream_db_connectors' );
	delete_site_option( 'wp_stream_db_registered_connectors' );
}

// User meta is global, so it is cleaned once. delete_user_meta() keeps the
// user meta cache in sync, unlike a direct query against the table.
global $wpdb;
$stream_user_meta_keys = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT user_id, meta_key FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s OR meta_key IN ( 'stream_live_update_records', 'stream_last_read', 'stream_unread_count', 'stream_user_feed_key' )",
		'%' . $wpdb->esc_like( 'wp_stream' ) . '%',
		'%' . $wpdb->esc_like( 'edit_stream_per_page' )
	)
);
if ( $stream_user_meta_keys ) {
	foreach ( $stream_user_meta_keys as $stream_user_meta_key ) {
		delete_user_meta( (int) $stream_user_meta_key->user_id, $stream_user_meta_key->meta_key );
	}
}
