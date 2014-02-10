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
		add_filter( 'stream_get_fields', array( $this, 'stream_get_fields' ) );
		add_filter( 'wp_stream_options', array( $this, 'stream_filter_options' ) );

	}

	/**
	 * Adds a network settings field to stream options in network admin
	 *
	 * @param $fields
	 *
	 * @return mixed
	 */
	function stream_get_fields( $fields ) {
			if ( !function_exists( 'is_plugin_active_for_network' ) )
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

			  if ( is_network_admin() && is_plugin_active_for_network( WP_STREAM_PLUGIN ) ) {
				$fields['general']['fields'][] =
							array(
								'name'    => 'disable_sites_admin',
								'title'   => __( 'Disable Site Access', 'stream' ),
								'default' => 0,
								'desc'    => __( 'When site access is disabled the settings and Stream can only be accessed in network administration', 'stream' ),
								'type'    => 'checkbox',
				);
				return $fields;
			}
		return $fields;
	}

	/**
	 * Wrapper for the settings API to work on the network settings page
	 */
	function network_options_action() {
		if ( ! isset( $_GET['action'] ) || 'stream_settings' != $_GET['action'] )
			return;

		$options = isset( $_POST['option_page'] ) ? explode( ',', stripslashes( $_POST['option_page'] ) ) : null;
		if ( $options ) {
			foreach ( $options as $option ) {
				$option = trim( $option );
				$value = null;
				if ( isset( $_POST[$option] ) )
					$value = $_POST[$option];

				if ( ! is_array( $value ) )
					$value = trim( $value );

				update_site_option( $option, $value );
			}
		}

		if ( ! count( get_settings_errors() ) )
			add_settings_error( 'general', 'settings_updated', __( 'Settings saved', 'stream' ), 'updated' );

		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$go_back = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $go_back );
		exit;
	}

	/**
	 * Filters stream options when on the network settings page
	 *
	 * @param $options
	 *
	 * @return array
	 */
	function stream_filter_options( $options ) {
		if ( ! is_network_admin() )
			return $options;

		$network_options = wp_parse_args(
		  (array) get_site_option( WP_Stream_Settings::KEY, array() ),
			WP_Stream_Settings::get_defaults()
		);

		if ( $network_options )
			return $network_options;

		return $options;
	}

}

new WP_Stream_Network();