<?php
/**
 * Registers the Stream admin menu and submenu pages.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Admin_Menu
 */
class Admin_Menu {

	/**
	 * Menu page screen ids keyed by page (main, settings, network_settings).
	 *
	 * @var array<string, string>
	 */
	public array $screen_id = array();

	/**
	 * Class constructor.
	 *
	 * @param Admin $admin Admin façade.
	 */
	public function __construct( private Admin $admin ) {
		$this->register_hooks();
	}

	/**
	 * Register WordPress actions for the admin menu.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		if ( ! $this->is_site_access_disabled() ) {
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
		}
	}

	/**
	 * Whether site-level Stream admin is disabled by network settings.
	 *
	 * On network-activated multisite, the network admin can turn off per-site
	 * access; only the network admin UI remains available when disabled.
	 *
	 * @return bool
	 */
	private function is_site_access_disabled(): bool {
		if ( ! $this->admin->plugin->is_multisite_network_activated() || is_network_admin() ) {
			return false;
		}

		$options = (array) get_site_option( 'wp_stream_network', array() );
		$option  = isset( $options['general_site_access'] ) ? absint( $options['general_site_access'] ) : 1;

		return ! $option;
	}

	/**
	 * Register menu page
	 *
	 * @action admin_menu
	 *
	 * @return void
	 */
	public function register_menu() {
		/**
		 * Filter the main admin menu title
		 *
		 * @return string
		 */
		$main_menu_title = apply_filters( 'wp_stream_admin_menu_title', esc_html__( 'Stream', 'stream' ) );

		/**
		 * Filter the main admin menu position
		 *
		 * Note: Using longtail decimal string to reduce the chance of position conflicts, see Codex
		 *
		 * @return string
		 */
		$main_menu_position = apply_filters( 'wp_stream_menu_position', '2.999999' );

		/**
		 * Filter the main admin page title
		 *
		 * @return string
		 */
		$main_page_title = apply_filters( 'wp_stream_admin_page_title', esc_html__( 'Stream Records', 'stream' ) );

		$this->screen_id['main'] = add_menu_page(
			$main_page_title,
			$main_menu_title,
			$this->admin->view_cap,
			$this->admin->records_page_slug,
			array( $this->admin->records, 'render_list_table' ),
			'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDI0IDEwMjQiIGZpbGw9IjAwMCI+Cgk8cGF0aCBkPSJNOTAzLjExNSA1MTUuNDEzYy00OS4zOTIgMC05MS40NzQgMzEuMzM3LTEwNy40NiA3NS4yMDNsLTEyNC40MTEtMS41MzJjLTExLjM3Ny0uMzQ2LTIyLjc1MS0uNjg5LTM0LjEyOS0uOTk4bC0uMjQxLjU3NC0yMi40MzYtLjI3OC0uMTUzLS45Mi0xNS4wNTYtODIuOTMzLTIwLjE0Ni0xMDguNDA1LTIwLjU0NC0xMDguMzM3TDUwMy45ODIgMGwtNTMuMTQxIDQyOS4wMTMtMTYuMjE0IDEzNy45MzUtMTIuMDE2IDEwNi45MjQtMTE3LjI4Ni0yODUuMjItMTguMzUzIDIwMi44MWMtNDIuNTYyIDEuNDU0LTg1LjEyNyAyLjkzNC0xMjcuNjg4IDQuNzM4LTUzLjA5NyAyLjI5Mi0xMDYuMTg3IDQuNDczLTE1OS4yODQgNy41MzZ2NDIuMDQyYzUzLjA5NyAzLjA2IDEwNi4xODcgNS4yNDcgMTU5LjI4NCA3LjUzMyA1My4wOTMgMi4yNDUgMTA2LjE4IDQuMTk0IDE1OS4yNzMgNS45MDNsMTQuMjQuNDY1IDE3LjM1MSA0OC4zOWMxOC44NDIgNTEuODc0IDM3LjU0MiAxMDMuODA2IDU2Ljc2NSAxNTUuNTQxTDQ2Ni41MiAxMDI0bDQxLjUxMi0zMDguMjkzIDE3LjYzMy0xMzYuNjg1IDEwLjc3NiA1MC4zMjkgNTQuODE1IDI0OC41NDQgNzIuNTE2LTIxNy4yMTdoMTI5LjI2MWMxMy40OTMgNDguMTIxIDU3LjY1NSA4My40MjkgMTEwLjA3NSA4My40MjkgNjMuMTYgMCAxMTQuMzUyLTUxLjIwNSAxMTQuMzUyLTExNC4zNDggMC02My4xMzktNTEuMTg5LTExNC4zNDUtMTE0LjM0OS0xMTQuMzQ1bC4wMDQtLjAwMVoiIC8+Cjwvc3ZnPgo=',
			$main_menu_position
		);

		/**
		 * Fires before submenu items are added to the Stream menu
		 * allowing plugins to add menu items before Settings
		 *
		 * @return void
		 */
		do_action( 'wp_stream_admin_menu' );

		/**
		 * Filter the Settings admin page title
		 *
		 * @return string
		 */
		$settings_page_title = apply_filters( 'wp_stream_settings_form_title', esc_html__( 'Stream Settings', 'stream' ) );

		$this->screen_id['settings'] = add_submenu_page(
			$this->admin->records_page_slug,
			$settings_page_title,
			esc_html__( 'Settings', 'stream' ),
			$this->admin->settings_cap,
			$this->admin->settings_page_slug,
			array( $this->admin->settings, 'render_settings_page' )
		);

		if ( isset( $this->screen_id['main'] ) ) {
			/**
			 * Fires just before the Stream list table is registered.
			 *
			 * @return void
			 */
			do_action( 'wp_stream_admin_menu_screens' );

			// Register the list table early, so it associates the column headers with 'Screen settings'.
			add_action(
				'load-' . $this->screen_id['main'],
				array(
					$this->admin->records,
					'register_list_table',
				)
			);
		}
	}
}
