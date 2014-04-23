<?php
/**
 * Multisite Stream Notifications Network Class
 *
 * @author X-Team <x-team.com>
 * @author Chris Olbekson <chris@x-team.com>
 *
 */

class WP_Stream_Notifications_Network {

	function __construct() {
		$this->actions();
		$this->filters();
	}

	function actions() {
	}

	function filters() {
		add_filter( 'wp_stream_notifications_options_fields', array( $this, 'get_network_notifications_admin_fields' ) );
	}

	/**
	 * Adjusts the settings fields displayed in the Network Notifications Settings
	 *
	 * @param $fields
	 *
	 * @return mixed
	 */
	function get_network_notifications_admin_fields( $fields ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		if ( ! is_plugin_active_for_network( WP_STREAM_PLUGIN ) ) {
			return $fields;
		}

		$hidden_options = apply_filters(
			'wp_stream_notifications_hidden_option_fields',
			array(
				'notifications' => array(
					'role_access',
				),
			)
		);

		// Remove settings
		foreach ( $fields as $section_key => $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				if ( ! isset( $hidden_options[ $section_key ] ) ) {
					continue;
				}
				if ( in_array( $field['name'], $hidden_options[ $section_key ] ) ) {
					unset( $fields[ $section_key ]['fields'][ $key ] );
				}
			}
		}

		// Remove empty settings sections
		foreach ( $fields as $section_key => $section ) {
			if ( empty( $section['fields'] ) ) {
				unset( $fields[ $section_key ] );
			}
		}

		return $fields;

	}

}
