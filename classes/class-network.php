<?php
namespace WP_Stream;

class Network {
	/**
	 * Hold Plugin class
	 * @var Plugin
	 */
	public $plugin;

	public $network_settings_page_slug = 'wp_stream_network_settings';

	public $default_settings_page_slug = 'wp_stream_default_settings';

	function __construct( $plugin ) {
		$this->plugin = $plugin;

		if ( ! $this->is_network_activated() ) {
			return;
		}

		// Actions
		add_action( 'init', array( $this, 'ajax_network_admin' ) );
		add_action( 'network_admin_menu', array( 'WP_Stream_Admin', 'register_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'admin_menu_screens' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu_screens' ) );
		add_action( 'admin_bar_menu', array( $this, 'network_admin_bar_menu' ), 99 );
		add_action( 'network_admin_notices', array( 'WP_Stream_Admin', 'admin_notices' ) );
		add_action( 'wpmuadminedit', array( $this, 'network_options_action' ) );
		add_action( 'update_site_option_' . $this->plugin->settings->network_option_key, array( $this, 'updated_option_ttl_remove_records' ), 10, 3 );

		// Filters
		add_filter( 'wp_stream_blog_id_logged', array( $this, 'blog_id_logged' ) );
		add_filter( 'wp_stream_query_args', array( $this, 'network_query_args' ) );
		add_filter( 'wp_stream_admin_page_title', array( $this, 'network_admin_page_title' ) );
		add_filter( 'wp_stream_list_table_screen_id', array( $this, 'list_table_screen_id' ) );
		add_filter( 'wp_stream_list_table_filters', array( $this, 'list_table_filters' ) );
		add_filter( 'wp_stream_list_table_columns', array( $this, 'network_admin_columns' ) );
		add_filter( 'wp_stream_disable_admin_access', array( $this, 'disable_admin_access' ) );
		add_filter( 'wp_stream_settings_form_action', array( $this, 'settings_form_action' ) );
		add_filter( 'wp_stream_settings_form_description', array( $this, 'settings_form_description' ) );
		add_filter( 'wp_stream_settings_options_fields', array( $this, 'get_network_admin_fields' ) );
		add_filter( 'wp_stream_settings_options', array( $this, 'get_network_options' ), 10, 2 );
		add_filter( 'wp_stream_serialized_labels', array( $this, 'get_settings_translations' ) );
		add_filter( 'wp_stream_connectors', array( $this, 'hide_blogs_connector' ) );
	}

	/**
	 * Workaround to get admin-ajax.php to know when the request is from the Network Admin
	 *
	 * @action init
	 *
	 * @see https://core.trac.wordpress.org/ticket/22589
	 */
	public function ajax_network_admin() {
		if (
			defined( 'DOING_AJAX' )
			&&
			DOING_AJAX
			&&
			preg_match( '#^' . network_admin_url() . '#i', $_SERVER['HTTP_REFERER'] )
		) {
			define( 'WP_NETWORK_ADMIN', true );
		}
	}

	/**
	 * Builds a stdClass object used when displaying actions done in network administration
	 *
	 * @return object
	 */
	public function get_network_blog() {
		$blog           = new stdClass;
		$blog->blog_id  = 0;
		$blog->blogname = esc_html__( 'Network Admin', 'stream' );

		return $blog;
	}

	/**
	 * Returns true if Stream is network activated, otherwise false
	 *
	 * @return bool
	 */
	public function is_network_activated() {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		}

		return is_plugin_active_for_network( $this->plugin->locations['plugin'] );
	}

	/**
	 * Adds Stream to the admin bar under the "My Sites > Network Admin" menu
	 * if Stream has been network-activated.
	 *
	 * @action admin_bar_menu
	 *
	 * @param object $admin_bar
	 *
	 * @return void
	 */
	public function network_admin_bar_menu( $admin_bar ) {
		if ( ! $this->is_network_activated() ) {
			return;
		}

		$href = add_query_arg(
			array(
				'page' => $this->plugin->admin->records_page_slug,
			),
			network_admin_url( $this->plugin->admin->admin_parent_page )
		);

		$admin_bar->add_menu(
			array(
				'id'     => 'network-admin-stream',
				'parent' => 'network-admin',
				'title'  => esc_html__( 'Stream', 'stream' ),
				'href'   => esc_url( $href ),
			)
		);
	}

	/**
	 * If site access has been disabled from the network admin, disallow access
	 *
	 * @param $disable_access
	 *
	 * @return bool
	 */
	public function disable_admin_access( $disable_access ) {
		if ( ! is_network_admin() && $this->is_network_activated() ) {
			$settings = (array) get_site_option( $this->plugin->settings->network_options_key, array() );

			if ( isset( $settings['general_enable_site_access'] ) && false === $settings['general_enable_site_access'] ) {
				return true;
			}
		}

		return $disable_access;
	}

	/**
	 * Add Network Settings and Default Settings menu items
	 *
	 * @return array
	 */
	public function admin_menu_screens() {
		if ( ! is_network_admin() ) {
			return;
		}

		remove_submenu_page( $this->plugin->admin->records_page_slug, 'wp_stream_settings' );

		$this->plugin->admin->screen_id['network_settings'] = add_submenu_page(
			$this->plugin->admin->records_page_slug,
			__( 'Stream Network Settings', 'stream' ),
			__( 'Network Settings', 'stream' ),
			$this->plugin->admin->settings_cap,
			$this->network_settings_page_slug,
			array( 'Admin', 'render_settings_page' )
		);

		if ( ! $this->plugin->admin->disable_access ) {
			$this->plugin->admin->screen_id['default_settings'] = add_submenu_page(
				$this->plugin->admin->records_page_slug,
				__( 'New Site Settings', 'stream' ),
				__( 'Site Defaults', 'stream' ),
				$this->plugin->admin->settings_cap,
				$this->default_settings_page_slug,
				array( 'Admin', 'render_settings_page' )
			);
		}
	}

	/**
	 * Remove records when records TTL is shortened
	 *
	 * @param string $option_key
	 * @param array  $old_value
	 * @param array  $new_value
	 *
	 * @action update_option_wp_stream
	 * @return void
	 */
	public function updated_option_ttl_remove_records( $option_key, $new_value, $old_value ) {
		unset( $option_key );
		$this->plugin->settings->updated_option_ttl_remove_records( $old_value, $new_value );
	}

	/**
	 * Adjust the action of the settings form when in the Network Admin
	 *
	 * @param $action
	 *
	 * @return string
	 */
	public function settings_form_action( $action ) {
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
	public function settings_form_description( $description ) {
		if ( ! is_network_admin() ) {
			return '';
		}

		$current_page = wp_stream_filter_input( INPUT_GET, 'page' );

		switch ( $current_page ) {
			case $this->network_settings_page_slug :
				$description = __( 'These settings apply to all sites on the network.', 'stream' );
				break;
			case $this->default_settings_page_slug :
				$description = __( 'These default settings will apply to new sites created on the network. These settings do not alter existing sites.', 'stream' );
				break;
		}

		return $description;
	}

	/**
	 * Adjusts the settings fields displayed in various network admin screens
	 *
	 * @param $fields
	 *
	 * @return mixed
	 */
	public function get_network_admin_fields( $fields ) {
		if ( ! $this->is_network_activated() ) {
			return $fields;
		}

		$stream_hidden_options = apply_filters(
			'wp_stream_hidden_option_fields',
			array(
				'general' => array(
					'delete_all_records',
					'records_ttl',
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
					'authors',
					'roles',
					'connectors',
					'contexts',
					'actions',
					'ip_addresses',
					'hide_previous_records',
				),
			)
		);

		// Remove settings based on context
		if ( $this->plugin->settings->network_options_key === $this->plugin->settings->option_key ) {
			$hidden_options = $network_hidden_options;
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

		// Add settings based on context
		if ( $this->plugin->settings->network_options_key === $this->plugin->settings->option_key ) {
			$new_fields['general']['fields'][] = array(
				'name'        => 'enable_site_access',
				'title'       => __( 'Enable Site Access', 'stream' ),
				'after_field' => __( 'Enabled', 'stream' ),
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

		// Remove empty settings sections
		foreach ( $fields as $section_key => $section ) {
			if ( empty( $section['fields'] ) ) {
				unset( $fields[ $section_key ] );
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
	public function get_settings_translations( $labels ) {
		$network_key  = $this->plugin->settings->network_options_key;
		$defaults_key = $this->plugin->settings->site_defaults_options_key;

		if ( ! isset( $labels[ $network_key ] ) ) {
			$labels[ $network_key ] = array();
		}

		if ( ! isset( $labels[ $defaults_key ] ) ) {
			$labels[ $defaults_key ] = array();
		}

		foreach ( $this->plugin->settings->get_fields() as $section_slug => $section ) {
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
	public function network_options_action() {
		$allowed_referers = array(
			$this->network_settings_page_slug,
			$this->default_settings_page_slug,
		);

		if ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], $allowed_referers ) ) {
			return;
		}

		$options = isset( $_POST['option_page'] ) ? explode( ',', stripslashes( $_POST['option_page'] ) ) : null;

		if ( $options ) {

			foreach ( $options as $option ) {
				$option   = trim( $option );
				$value    = null;
				$sections = $this->plugin->settings->get_fields();

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
	public function get_network_options( $options, $option_key ) {
		if ( is_network_admin() ) {
			$options = wp_parse_args(
				(array) get_site_option( $option_key, array() ),
				$this->plugin->settings->get_defaults( $option_key )
			);
		}

		return $options;
	}

	/**
	 * Add the Site filter to the Network records screen
	 *
	 * @filter wp_stream_list_table_filters
	 *
	 * @param $filters
	 *
	 * @return array
	 */
	public function list_table_filters( $filters ) {
		if ( ! is_network_admin() || wp_is_large_network() ) {
			return $filters;
		}

		$blogs = array();

		// Display network blog as the first option
		$network_blog = $this->get_network_blog();

		$blogs[ $network_blog->blog_id ] = array(
			'label'    => $network_blog->blogname,
			'disabled' => '',
		);

		// add all sites
		foreach ( wp_get_sites() as $blog ) {
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

		return $filters;
	}

	/**
	 * Add the Site toggle to screen options in network admin
	 *
	 * @param $filters
	 *
	 * @return array
	 */
	public function toggle_filters( $filters ) {
		if ( is_network_admin() ) {
			$filters['blog_id'] = esc_html__( 'Site', 'stream' );
		}

		return $filters;
	}

	/**
	 * Add the network suffix to the $screen_id when in the network admin
	 *
	 * @param $screen_id
	 *
	 * @return string
	 */
	public function list_table_screen_id( $screen_id ) {
		if ( $screen_id && is_network_admin() ) {
			if ( '-network' !== substr( $screen_id, -8 ) ) {
				$screen_id .= '-network';
			}
		}

		return $screen_id;
	}

	/**
	 * Set blog_id for network admin activity
	 *
	 * @return int
	 */
	public function blog_id_logged( $blog_id ) {
		return is_network_admin() ? 0 : $blog_id;
	}

	/**
	 * Customize query args on multisite installs
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function network_query_args( $args ) {
		if ( ! is_multisite() ) {
			return $args;
		}

		$args['site_id'] = is_numeric( $args['site_id'] ) ? $args['site_id'] : get_current_site()->id;
		$args['blog_id'] = is_numeric( $args['blog_id'] ) ? $args['blog_id'] : ( is_network_admin() ? null : get_current_blog_id() );

		return $args;
	}

	/**
	 * Add site count to the page title in the network admin
	 *
	 * @filter wp_stream_admin_page_title
	 *
	 * @param string $page_title
	 *
	 * @return string
	 */
	public function network_admin_page_title( $page_title ) {
		if ( is_network_admin() ) {
			$site_count = sprintf( _n( '1 site', '%s sites', get_blog_count(), 'stream' ), number_format( get_blog_count() ) );
			$page_title = sprintf( '%s (%s)', $page_title, $site_count );
		}

		return $page_title;
	}

	/**
	 * Add the Site column to the network stream records
	 *
	 * @param $columns
	 *
	 * @return mixed
	 */
	public function network_admin_columns( $columns ) {
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

	/**
	 * Prevent the Blogs connector from loading when not in Network Admin
	 *
	 * @param $connectors
	 *
	 * @return mixed
	 */
	public function hide_blogs_connector( $connectors ) {
		if ( ! is_network_admin() ) {
			return array_diff( $connectors, array( 'Connector_Blogs' ) );
		}

		return $connectors;
	}
}
