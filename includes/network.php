<?php
/**
 * Multisite Network Class
 *
 * @author X-Team <x-team.com>
 * @author Chris Olbekson <chris@x-team.com>
 *
 */

class WP_Stream_Network {

	function __construct() {
		$this->actions();
		$this->filters();
	}

	function actions() {
		add_action( 'wpmuadminedit', array( $this, 'network_options_action' ) );
	}

	function filters() {
		add_filter( 'wp_stream_options_fields', array( $this, 'get_fields' ) );
		add_filter( 'wp_stream_options', array( $this, 'get_network_options' ) );
		add_filter( 'stream_toggle_filters', array( $this, 'toggle_filters' ) );
	}

	/**
	 * Adds a network settings field to stream options in network admin
	 *
	 * @param $fields
	 *
	 * @return mixed
	 */
	function get_fields( $fields ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		if ( ! is_plugin_active_for_network( WP_STREAM_PLUGIN ) ) {
			return $fields;
		}

		$option_key = WP_Stream_Settings::get_option_key();

		$network_only_options = apply_filters(
			'wp_stream_network_only_option_fields',
			array(
				'general' => array(
					'delete_all_records',
					'records_ttl',
				),
			)
		);

		$site_only_options = apply_filters(
			'wp_stream_site_only_option_fields',
			array(
				'general' => array(
					'role_access',
					'private_feeds',
				),
			)
		);

		if ( WP_Stream_Settings::SETTINGS_KEY === $option_key && is_network_admin() ) {
			$hidden_options = $site_only_options;
		} else {
			$hidden_options = $network_only_options;
		}

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

		if ( WP_Stream_Settings::SETTINGS_KEY === $option_key && is_network_admin() ) {
			$new_fields['general']['fields'][] = array(
				'name'        => 'enable_site_access',
				'title'       => __( 'Enable Site Access', 'stream' ),
				'after_field' => __( 'Enabled' ),
				'default'     => 1,
				'desc'        => __( 'When site access is disabled Stream can only be accessed from the network administration.', 'stream' ),
				'type'        => 'checkbox',
			);

			$fields = array_merge_recursive( $new_fields, $fields );
		}

		return $fields;
	}

	/**
	 * Wrapper for the settings API to work on the network settings page
	 */
	function network_options_action() {
		if ( ! isset( $_GET['action'] ) || ( 'wp_stream_settings' !== $_GET['action'] && 'wp_stream_default_settings' !== $_GET['action'] ) ) {
			return;
		}

		$options = isset( $_POST['option_page'] ) ? explode( ',', stripslashes( $_POST['option_page'] ) ) : null;

		if ( $options ) {

			foreach ( $options as $option ) {
				$option = trim( $option );
				$value  = null;

				$sections = WP_Stream_Settings::get_fields();
				foreach ( $sections as $section_name => $section ) {
					foreach ( $section['fields'] as $field_idx => $field ) {
						$option_key = $section_name . '_' . $field['name'];
						if ( isset( $_POST[ $option ][ $option_key ] ) ) {
							$value[ $option_key ] = $_POST[ $option ][ $option_key ];
						} else {
							$value[ $option_key ] = false;
						}
					}
				}

				if ( ! is_array( $value ) ) {
					$value = trim( $value );
				}

				update_site_option( $option, $value );
			}
		}

		if ( ! count( get_settings_errors() ) ) {
			add_settings_error( 'general', 'settings_updated', __( 'Settings saved.', 'stream' ), 'updated' );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$go_back = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $go_back );
		exit;
	}

	/**
	 * Uses network options when on the network settings page
	 *
	 * @param $options
	 *
	 * @return array
	 */
	function get_network_options( $options ) {
		if ( ! is_network_admin() ) {
			return $options;
		}

		$network_options = wp_parse_args(
			(array) get_site_option( WP_Stream_Settings::SETTINGS_KEY, array() ),
			WP_Stream_Settings::get_defaults()
		);

		if ( $network_options ) {
			return $network_options;
		}

		return $options;
	}

	/**
	 * Add the Site toggle to screen options when in network settings
	 *
	 * @param $options
	 *
	 * @return array
	 */
	function toggle_filters( $filters ) {
		if ( ! is_network_admin() ) {
			return $filters;
		}
		$filters['blog_id'] = esc_html__( 'Site', 'stream' );
		return $filters;
	}

}
