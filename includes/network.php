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
		add_action( 'network_admin_menu', array( 'WP_Stream_Admin', 'register_menu' ) );
		add_action( 'network_admin_notices', array( 'WP_Stream_Admin', 'admin_notices' ) );
		add_action( 'wpmuadminedit', array( $this, 'network_options_action' ) );
		add_action( 'wp_network_dashboard_setup', array( 'WP_Stream_Admin', 'dashboard_stream_activity' ) );
		add_action( 'wp_stream_admin_menu_screens', array( $this, 'admin_menu_screens' ) );
	}

	function filters() {
		add_filter( 'wp_stream_disable_admin_access', array( $this, 'disable_admin_access' ) );
		add_filter( 'wp_stream_settings_form_action', array( $this, 'settings_form_action' ) );
		add_filter( 'wp_stream_settings_form_description', array( $this, 'settings_form_description' ) );
		add_filter( 'wp_stream_settings_option_key', array( $this, 'get_option_key' ) );
		add_filter( 'wp_stream_options_fields', array( $this, 'get_fields' ) );
		add_filter( 'wp_stream_options', array( $this, 'get_network_options' ), 10, 2 );
		add_filter( 'wp_stream_serialized_labels', array( $this, 'get_settings_translations' ) );
		add_filter( 'wp_stream_list_table_filters', array( $this, 'list_table_filters' ) );
		add_filter( 'stream_toggle_filters', array( $this, 'toggle_filters' ) );
		add_filter( 'wp_stream_db_tables_prefix', array( $this, 'db_tables_prefix' ) );
		add_filter( 'wp_stream_list_table_screen_id', array( $this, 'list_table_screen_id' ) );
		add_filter( 'stream_query_args', array( $this, 'set_network_option_value' ) );
		add_filter( 'wp_stream_list_table_columns', array( $this, 'network_admin_columns' ) );
	}

	/**
	 * Builds a stdClass object used when displaying actions done in network administration
	 * @return stdClass
	 */
	public static function get_network_blog() {
		$blog           = new stdClass;
		$blog->blog_id  = 0;
		$blog->blogname = __( 'Network Admin', 'stream' );

		return $blog;
	}

	/**
	 * If site access has been disabled from the network admin, disallow access
	 *
	 * @param $disable_access
	 *
	 * @return boolean
	 */
	function disable_admin_access( $disable_access ) {
		if ( ! is_network_admin() ) {
			$settings = wp_parse_args(
				(array) get_site_option( WP_Stream_Settings::NETWORK_KEY, array() ),
				WP_Stream_Settings::get_defaults()
			);

			if ( isset( $settings['general_enable_site_access'] ) && false == $settings['general_enable_site_access'] ) {
				return true;
			}
		}

		return $disable_access;
	}

	/**
	 * Add Network Settings and Default Settings menu items
	 *
	 * @param $screen_id
	 *
	 * @return array
	 */
	function admin_menu_screens() {
		if ( ! is_network_admin() ) {
			return;
		}

		WP_Stream_Admin::$screen_id['network_settings'] = add_submenu_page(
			WP_Stream_Admin::RECORDS_PAGE_SLUG,
			__( 'Stream Network Settings', 'stream' ),
			__( 'Network Settings', 'stream' ),
			WP_Stream_Admin::SETTINGS_CAP,
			'wp_stream_network_settings',
			array( 'WP_Stream_Admin', 'render_page' )
		);

		if ( ! WP_Stream_Admin::$disable_access ) {
			WP_Stream_Admin::$screen_id['default_settings'] = add_submenu_page(
				WP_Stream_Admin::RECORDS_PAGE_SLUG,
				__( 'New Site Settings', 'stream' ),
				__( 'Site Defaults', 'stream' ),
				WP_Stream_Admin::SETTINGS_CAP,
				'wp_stream_default_settings',
				array( 'WP_Stream_Admin', 'render_page' )
			);
		}
	}

	/**
	 * Adjust the action of the settings form when in the Network Admin
	 *
	 * @param $action
	 *
	 * @return string
	 */
	function settings_form_action( $action ) {
		if ( is_network_admin() ) {
			$current_page = wp_stream_filter_input( INPUT_GET, 'page' );
			$action       = add_query_arg( array( 'action' => $current_page ), 'edit.php' );
		}

		return $action;
	}

	/**
	 * Add a description to each of the Settings pages in the Network Admin
	 *
	 * @param $description
	 *
	 * @return string
	 */
	function settings_form_description( $description ) {
		if ( ! is_network_admin() ) {
			return;
		}

		$current_page = wp_stream_filter_input( INPUT_GET, 'page' );

		switch ( $current_page ) {
			case 'wp_stream_settings' :
				$description = __( 'These settings only apply to the Network Admin Stream.', 'stream' );
				break;
			case 'wp_stream_network_settings' :
				$description = __( 'These settings apply to all sites on the network.', 'stream' );
				break;
			case 'wp_stream_default_settings' :
				$description = __( 'These default settings will apply to new sites created on the network. These settings do not alter existing sites.', 'stream' );
				break;
		}

		return $description;
	}

	/**
	 * Changes the option key depending on which settings page is being viewed
	 *
	 * @param $fields
	 *
	 * @return mixed
	 */
	function get_option_key( $option_key ) {
		$current_page = wp_stream_filter_input( INPUT_GET, 'page' );
		if ( ! $current_page ) {
			$current_page = wp_stream_filter_input( INPUT_GET, 'action' );
		}

		if ( 'wp_stream_default_settings' === $current_page ) {
			$option_key = WP_Stream_Settings::DEFAULTS_KEY;
		}

		if ( 'wp_stream_network_settings' === $current_page ) {
			$option_key = WP_Stream_Settings::NETWORK_KEY;
		}

		return $option_key;
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

		$stream_hidden_options = apply_filters(
			'wp_stream_hidden_option_fields',
			array(
				'general' => array(
					'delete_all_records',
					'records_ttl',
				),
			)
		);

		$network_stream_hidden_options = apply_filters(
			'wp_stream_network_stream_option_fields',
			array(
				'general' => array(
					'delete_all_records',
					'records_ttl',
					'role_access',
				),
				'exclude' => array(
					'authors_and_roles',
					'connectors',
					'contexts',
					'actions',
					'ip_addresses',
					'hide_previous_records',
				),
			)
		);

		$network_hidden_options = apply_filters(
			'wp_stream_network_option_fields',
			array(
				'general' => array(
					'role_access',
					'private_feeds',
				),
				'exclude' => array(
					'authors_and_roles',
					'connectors',
					'contexts',
					'actions',
					'ip_addresses',
					'hide_previous_records',
				),
			)
		);

		$defaults_hidden_options = apply_filters(
			'wp_stream_defaults_option_fields',
			array(
				'general' => array(
					'delete_all_records',
					'records_ttl',
				),
			)
		);

		// Remove settings based on context
		if ( WP_Stream_Settings::KEY === $option_key && is_network_admin() ) {
			$hidden_options = $network_stream_hidden_options;
		} elseif ( WP_Stream_Settings::NETWORK_KEY === $option_key && is_network_admin() ) {
			$hidden_options = $network_hidden_options;
		} elseif ( WP_Stream_Settings::DEFAULTS_KEY === $option_key && is_network_admin() ) {
			$hidden_options = $defaults_hidden_options;
		} else {
			$hidden_options = $stream_hidden_options;
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

		foreach ( $fields as $section_key => $section ) {
			if ( empty( $section['fields'] ) ) {
				unset( $fields[ $section_key ] );
			}
		}

		// Add and change settings, based on context
		if ( WP_Stream_Settings::NETWORK_KEY === $option_key && is_network_admin() ) {
			$new_fields['general']['fields'][] = array(
				'name'        => 'enable_site_access',
				'title'       => __( 'Enable Site Access', 'stream' ),
				'after_field' => __( 'Enabled' ),
				'default'     => 1,
				'desc'        => __( 'When site access is disabled Stream can only be accessed from the network administration.', 'stream' ),
				'type'        => 'checkbox',
			);

			$fields = array_merge_recursive( $new_fields, $fields );

			$reset_site_settings_href = add_query_arg(
				array(
					'action'          => 'wp_stream_defaults',
					'wp_stream_nonce' => wp_create_nonce( 'stream_nonce' ),
				),
				admin_url( 'admin-ajax.php' )
			);

			$fields['general']['fields'][] = array(
				'name'    => 'reset_site_settings',
				'title'   => __( 'Reset Site Settings', 'stream' ),
				'type'    => 'link',
				'href'    => $reset_site_settings_href,
				'desc'    => __( 'Warning: Clicking this will override all site settings with defaults.', 'stream' ),
				'default' => 0,
			);
		}

		if ( WP_Stream_Settings::KEY === $option_key && is_network_admin() ) {
			foreach ( $fields['general']['fields'] as $key => $field ) {
				if ( 'private_feeds' === $field['name'] ) {
					$fields['general']['fields'][ $key ]['desc'] = sprintf(
						__( 'Allows Super Admins to see actvity from the Network Admin (<code>blog_id = 0</code>) inside their Private Feeds. A Private Feed key can be found in their %suser profile%s.', 'stream' ),
						sprintf(
							'<a href="%s" title="%s">',
							admin_url( sprintf( 'profile.php#wp-stream-highlight:%s', WP_Stream_Feeds::USER_FEED_KEY ) ),
							esc_attr__( 'View Profile', 'stream' )
						),
						'</a>'
					);
					break;
				}
			}
		}

		return $fields;
	}

	/**
	 * Get translations of serialized Stream Network and Stream Default settings
	 *
	 * @filter wp_stream_serialized_labels
	 * @return array Multidimensional array of fields
	 */
	function get_settings_translations( $labels ) {
		$network_key  = WP_Stream_Settings::NETWORK_KEY;
		$defaults_key = WP_Stream_Settings::DEFAULTS_KEY;

		if ( ! isset( $labels[ $network_key ] ) ) {
			$labels[ $network_key ] = array();
		}

		if ( ! isset( $labels[ $defaults_key ] ) ) {
			$labels[ $defaults_key ] = array();
		}

		foreach ( WP_Stream_Settings::get_fields() as $section_slug => $section ) {
			foreach ( $section['fields'] as $field ) {
				$labels[ $network_key ][ sprintf( '%s_%s', $section_slug, $field['name'] ) ]  = $field['title'];
				$labels[ $defaults_key ][ sprintf( '%s_%s', $section_slug, $field['name'] ) ] = $field['title'];
			}
		}

		return $labels;
	}

	/**
	 * Wrapper for the settings API to work on the network settings page
	 */
	function network_options_action() {
		$allowed_referers = array(
			'wp_stream_settings',
			'wp_stream_network_settings',
			'wp_stream_default_settings',
		);
		if ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], $allowed_referers ) ) {
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
	function get_network_options( $options, $option_key ) {
		if ( is_network_admin() ) {
			$options = wp_parse_args(
				(array) get_site_option( $option_key, array() ),
				WP_Stream_Settings::get_defaults()
			);
		}

		return $options;
	}

	/**
	 * Add the Site filter to the stream activity in network admin
	 *
	 * @param $filters
	 *
	 * @return array
	 */
	function list_table_filters( $filters ) {
		if ( is_network_admin() ) {
			$blogs = array();

			// display network blog as the first option
			$network_blog = self::get_network_blog();

			$blogs['network'] = array(
				'label'    => $network_blog->blogname,
				'disabled' => '',
			);

			// add all sites
			foreach ( (array) wp_get_sites() as $blog ) {
				$blog_data = get_blog_details( $blog );

				$blogs[ $blog['blog_id'] ] = array(
					'label'    => $blog_data->blogname,
					'disabled' => '',
				);
			}

			$filters['blog_id'] = array(
				'title' => __( 'sites', 'stream' ),
				'items' => $blogs,
			);
		}

		return $filters;
	}

	/**
	 * Add the Site toggle to screen options in network admin
	 *
	 * @param $filters
	 *
	 * @return array
	 */
	function toggle_filters( $filters ) {
		if ( is_network_admin() ) {
			$filters['blog_id'] = esc_html__( 'Site', 'stream' );
		}

		return $filters;
	}

	/**
	 * Use a single primary database table for multisite installs
	 *
	 * @param $prefix
	 *
	 * @return string
	 */
	function db_tables_prefix( $prefix ) {
		global $wpdb;
		return $wpdb->base_prefix;
	}

	/**
	 * Add the network suffix to the $screen_id when in the network admin
	 *
	 * @param $screen_id
	 *
	 * @return string
	 */
	function list_table_screen_id( $screen_id ) {
		if ( $screen_id && is_network_admin() ) {
			if ( '-network' !== substr( $screen_id, -8 ) ) {
				$screen_id .= '-network';
			}
		}

		return $screen_id;
	}

	/**
	 * Adjust the stream query to work with changes on the network level
	 *
	 * @param $args
	 *
	 * @return mixed
	 */
	static function set_network_option_value( $args ) {
		if ( isset( $args['blog_id'] ) && 'network' === $args['blog_id'] ) {
			$args['blog_id'] = 0;
		}

		return $args;
	}

	/**
	 * Add the Site column to the network stream records
	 *
	 * @param $args
	 *
	 * @return mixed
	 */
	function network_admin_columns( $columns ) {
		if ( is_network_admin() ) {
			$columns = array_merge(
				array_slice( $columns, 0, -1 ),
				array(
					'blog_id' => esc_html__( 'Site', 'stream' ),
				),
				array_slice( $columns, -1 )
			);
		}

		return $columns;
	}

}
