<?php
/**
 * Enqueues Stream admin assets and screen chrome.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Admin_Assets
 */
class Admin_Assets {

	/**
	 * Class constructor.
	 *
	 * @param Admin $admin Admin façade.
	 */
	public function __construct( private Admin $admin ) {
		$this->register_hooks();
	}

	/**
	 * Register WordPress actions and filters for admin assets.
	 */
	private function register_hooks(): void {
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_menu_css' ) );
	}

	/**
	 * Enqueue scripts/styles for admin screen
	 *
	 * @action admin_enqueue_scripts
	 *
	 * @param string $hook  Current hook.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( in_array( $hook, $this->admin->menu->screen_id, true ) ) {
			$this->admin->plugin->enqueue_asset(
				'admin',
				array(
					$this->admin->plugin->with_select2(),
					$this->admin->plugin->with_jquery_timeago(),
				),
				array(
					'i18n'       => array(
						'confirm_purge'    => __( 'Are you sure you want to delete all Stream activity records from the database? This cannot be undone.', 'stream' ),
						'confirm_defaults' => __( 'Are you sure you want to reset all site settings to default? This cannot be undone.', 'stream' ),
					),
					'locale'     => strtolower( substr( get_locale(), 0, 2 ) ),
					'gmt_offset' => get_option( 'gmt_offset' ),
				)
			);

			$this->admin->plugin->enqueue_asset(
				'admin-exclude',
				array(
					$this->admin->plugin->with_select2(),
				),
				array(
					'getActionsNonce' => wp_create_nonce( 'stream_get_actions' ),
				)
			);

			$current_order = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! in_array( $current_order, array( 'asc', 'desc' ), true ) ) {
				$current_order = 'desc';
			}
			$current_query = map_deep( wp_unslash( $_GET ), 'sanitize_text_field' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			$this->admin->plugin->enqueue_asset(
				'live-updates',
				array( 'heartbeat' ),
				array(
					'current_screen'      => $hook,
					'current_page'        => isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : '1', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					'current_order'       => $current_order,
					'current_query'       => wp_json_encode( $current_query ),
					'current_query_count' => count( $current_query ),
				)
			);
		}

		/**
		 * The maximum number of items that can be updated in bulk without receiving a warning.
		 *
		 * Stream watches for bulk actions performed in the WordPress Admin (such as updating
		 * many posts at once) and warns the user before proceeding if the number of items they
		 * are attempting to update exceeds this threshold value. Since Stream will try to save
		 * a log for each item, it will take longer than usual to complete the operation.
		 *
		 * The default threshold is 100 items.
		 *
		 * @return int
		 */
		$bulk_actions_threshold = apply_filters( 'wp_stream_bulk_actions_threshold', 100 );

		$this->admin->plugin->enqueue_asset(
			'global',
			array(),
			array(
				'bulk_actions'       => array(
					'i18n'      => array(
						/* translators: %s: a number of items (e.g. "1,742") */
						'confirm_action' => sprintf( __( 'Are you sure you want to perform bulk actions on over %s items? This process could take a while to complete.', 'stream' ), number_format( absint( $bulk_actions_threshold ) ) ),
					),
					'threshold' => absint( $bulk_actions_threshold ),
				),
				'plugins_screen_url' => self_admin_url( 'plugins.php#stream' ),
			)
		);
	}

	/**
	 * Check whether or not the current admin screen belongs to Stream
	 *
	 * @return bool
	 */
	public function is_stream_screen() {
		if ( ! is_admin() ) {
			return false;
		}

		$page = wp_stream_filter_input( INPUT_GET, 'page' );
		if ( is_string( $page ) && false !== strpos( $page, $this->admin->records_page_slug ) ) {
			return true;
		}

		if ( is_admin() && function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();

			return ( Alerts::POST_TYPE === $screen->post_type );
		}

		return false;
	}

	/**
	 * Add a specific body class to all Stream admin screens
	 *
	 * @param string $classes  CSS classes to output to body.
	 *
	 * @filter admin_body_class
	 *
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		$stream_classes = array();

		if ( $this->is_stream_screen() ) {
			$stream_classes[] = $this->admin->admin_body_class;

			if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$stream_classes[] = sanitize_key( $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}

		/**
		 * Filter the Stream admin body classes
		 *
		 * @return array
		 */
		$stream_classes = apply_filters( 'wp_stream_admin_body_classes', $stream_classes );
		$stream_classes = implode( ' ', array_map( 'trim', $stream_classes ) );

		return sprintf( '%s %s ', $classes, $stream_classes );
	}

	/**
	 * Add menu styles for various WP Admin skins.
	 *
	 * @action admin_enqueue_scripts
	 */
	public function admin_menu_css() {
		// Make sure we're working off a clean version.
		if ( ! file_exists( ABSPATH . WPINC . '/version.php' ) ) {
			return;
		}
		include ABSPATH . WPINC . '/version.php';

		if ( ! isset( $wp_version ) ) {
			return;
		}

		$css = "
			body.{$this->admin->admin_body_class} #wpbody-content .wrap h1:nth-child(1):before {
				content: '';
				display: inline-block;
				width: 24px;
				height: 24px;
				margin-right: 8px;
				vertical-align: text-bottom;
				background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDI0IDEwMjQiIGZpbGw9ImN1cnJlbnRjb2xvciI+Cgk8cGF0aCBkPSJNOTAzLjExNSA1MTUuNDEzYy00OS4zOTIgMC05MS40NzQgMzEuMzM3LTEwNy40NiA3NS4yMDNsLTEyNC40MTEtMS41MzJjLTExLjM3Ny0uMzQ2LTIyLjc1MS0uNjg5LTM0LjEyOS0uOTk4bC0uMjQxLjU3NC0yMi40MzYtLjI3OC0uMTUzLS45Mi0xNS4wNTYtODIuOTMzLTIwLjE0Ni0xMDguNDA1LTIwLjU0NC0xMDguMzM3TDUwMy45ODIgMGwtNTMuMTQxIDQyOS4wMTMtMTYuMjE0IDEzNy45MzUtMTIuMDE2IDEwNi45MjQtMTE3LjI4Ni0yODUuMjItMTguMzUzIDIwMi44MWMtNDIuNTYyIDEuNDU0LTg1LjEyNyAyLjkzNC0xMjcuNjg4IDQuNzM4LTUzLjA5NyAyLjI5Mi0xMDYuMTg3IDQuNDczLTE1OS4yODQgNy41MzZ2NDIuMDQyYzUzLjA5NyAzLjA2IDEwNi4xODcgNS4yNDcgMTU5LjI4NCA3LjUzMyA1My4wOTMgMi4yNDUgMTA2LjE4IDQuMTk0IDE1OS4yNzMgNS45MDNsMTQuMjQuNDY1IDE3LjM1MSA0OC4zOWMxOC44NDIgNTEuODc0IDM3LjU0MiAxMDMuODA2IDU2Ljc2NSAxNTUuNTQxTDQ2Ni41MiAxMDI0bDQxLjUxMi0zMDguMjkzIDE3LjYzMy0xMzYuNjg1IDEwLjc3NiA1MC4zMjkgNTQuODE1IDI0OC41NDQgNzIuNTE2LTIxNy4yMTdoMTI5LjI2MWMxMy40OTMgNDguMTIxIDU3LjY1NSA4My40MjkgMTEwLjA3NSA4My40MjkgNjMuMTYgMCAxMTQuMzUyLTUxLjIwNSAxMTQuMzUyLTExNC4zNDggMC02My4xMzktNTEuMTg5LTExNC4zNDUtMTE0LjM0OS0xMTQuMzQ1bC4wMDQtLjAwMVoiIC8+Cjwvc3ZnPgo=');
			}
			#menu-posts-feedback .wp-menu-image:before {
				font-family: dashicons !important;
				content: '\\f175';
			}
			#adminmenu #menu-posts-feedback div.wp-menu-image {
				background: none !important;
				background-repeat: no-repeat;
			}
		";

		wp_add_inline_style( 'wp-admin', $css );
	}
}
