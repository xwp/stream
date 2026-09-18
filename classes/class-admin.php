<?php
/**
 * Centralized manager for WordPress backend functionality.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

use WP_CLI;
use WP_Roles;

/**
 * Class - Admin
 */
class Admin {

	/**
	 * The async deletion action for large sites.
	 *
	 * @const string
	 */
	const ASYNC_DELETION_ACTION = 'stream_erase_large_records_action';

	/**
	 * Recurring Action Scheduler action that drives the TTL-based auto-purge.
	 *
	 * @const string
	 */
	const AUTO_PURGE_ACTION = 'stream_auto_purge_action';

	/**
	 * Async batch worker scheduled by the recurring auto-purge action.
	 *
	 * @const string
	 */
	const AUTO_PURGE_BATCH_ACTION = 'stream_auto_purge_batch_action';

	/**
	 * Terminal action that runs the orphan-meta reaper once per chain.
	 *
	 * @const string
	 */
	const AUTO_PURGE_REAPER_ACTION = 'stream_auto_purge_reaper_action';

	/**
	 * Action Scheduler group string for all auto-purge actions.
	 *
	 * @const string
	 */
	const AUTO_PURGE_GROUP = 'stream-auto-purge';

	/**
	 * Option storing which scheduler backend last registered the recurring
	 * auto-purge action ('action_scheduler' | 'wp_cron'), or 'disabled' when
	 * the `wp_stream_enable_auto_purge` filter has torn scheduling down. Used
	 * to detect a backend switch (or a disable/re-enable cycle) so the stale
	 * recurring action is cleared exactly once, instead of probing for it on
	 * every page load.
	 *
	 * @const string
	 */
	const SCHEDULER_BACKEND_OPTION = 'wp_stream_scheduler_backend';

	/**
	 * Option persisting the "large batched operation queued to WP-Cron"
	 * warning between requests. The contexts that queue the warning (the
	 * recurring purge under DOING_CRON, the reset handler just before its
	 * redirect) never render their own output, so the message is stored here
	 * and displayed on the next admin page load instead. Deleted on render.
	 *
	 * @const string
	 */
	const LARGE_TABLE_CRON_NOTICE_OPTION = 'wp_stream_large_table_cron_notice';

	/**
	 * Holds Network class
	 */
	public ?Network $network = null;

	/**
	 * Holds Live Update class
	 */
	public ?Live_Update $live_update = null;

	/**
	 * Holds Export class
	 */
	public ?Export $export = null;

	/**
	 * List table object
	 */
	public ?List_Table $list_table = null;

	/**
	 * Class applied to the body of the admin screen
	 */
	public string $admin_body_class = 'wp_stream_screen';

	/**
	 * Slug of the records page
	 */
	public string $records_page_slug = 'wp_stream';

	/**
	 * Slug of the settings page
	 */
	public string $settings_page_slug = 'wp_stream_settings';

	/**
	 * Parent page of the records and settings pages
	 */
	public string $admin_parent_page = 'admin.php';

	/**
	 * Capability name for viewing records
	 */
	public string $view_cap = 'view_stream';

	/**
	 * Capability name for managing settings
	 */
	public string $settings_cap = WP_STREAM_SETTINGS_CAPABILITY;

	/**
	 * Total amount of authors to pre-load
	 */
	public int $preload_users_max = 50;

	/**
	 * Admin notices, collected and displayed on proper action
	 */
	public array $notices = array();

	/**
	 * Menu collaborator.
	 *
	 * Public so Network can register `network_admin_menu` on the collaborator
	 * without an Admin façade.
	 */
	public Admin_Menu $menu;

	/**
	 * Assets collaborator.
	 */
	public Admin_Assets $assets;

	/**
	 * Records screen collaborator.
	 *
	 * Public so Export and menu callbacks can target the screen class directly.
	 */
	public Admin_Screen_Records $records;

	/**
	 * Settings screen collaborator.
	 *
	 * Public so Network can register the network settings submenu callback.
	 */
	public Admin_Screen_Settings $settings;

	/**
	 * Ajax collaborator.
	 */
	public Admin_Ajax $ajax;

	/**
	 * Purge / erase collaborator.
	 *
	 * Public so Settings can trigger an immediate purge without an Admin façade.
	 */
	public Admin_Purge $purge;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( public $plugin ) {
		$this->menu     = new Admin_Menu( $this );
		$this->assets   = new Admin_Assets( $this );
		$this->records  = new Admin_Screen_Records( $this );
		$this->settings = new Admin_Screen_Settings( $this );
		$this->purge    = new Admin_Purge( $this );
		$this->ajax     = new Admin_Ajax( $this, $this->purge );

		$this->register_hooks();
	}

	/**
	 * Register WordPress actions and filters for the admin façade.
	 *
	 * Hook identities for cross-cutting admin behaviour remain on this instance
	 * (`array( $this, 'method' )`). Screen-specific, asset, Ajax, and purge
	 * hooks are registered on their collaborator classes.
	 */
	private function register_hooks(): void {
		add_action( 'init', array( $this, 'init' ) );

		// User and role caps.
		add_filter( 'user_has_cap', array( $this, 'filter_user_caps' ), 10, 4 );
		add_filter( 'role_has_cap', array( $this, 'filter_role_caps' ), 10, 3 );

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'prepare_admin_notices' ) );
		add_action( 'shutdown', array( $this, 'admin_notices' ) );

		// Plugin action links.
		add_filter(
			'plugin_action_links',
			array(
				$this,
				'plugin_action_links',
			),
			10,
			2
		);
	}

	/**
	 * Load admin classes
	 *
	 * @action init
	 */
	public function init() {
		$this->network     = new Network( $this->plugin );
		$this->live_update = new Live_Update( $this->plugin );
		$this->export      = new Export( $this->plugin );

		// Check if the host has configured the `REMOTE_ADDR` correctly.
		$client_ip = $this->plugin->get_client_ip_address();
		if ( empty( $client_ip ) && $this->assets->is_stream_screen() ) {
			$this->notice( __( 'Stream plugin can\'t determine a reliable client IP address! Please update the hosting environment to set the $_SERVER[\'REMOTE_ADDR\'] variable or use the wp_stream_client_ip_address filter to specify the verified client IP address!', 'stream' ) );
		}
	}

	/**
	 * Output specific updates passed as URL parameters.
	 *
	 * @action admin_notices
	 *
	 * @return void
	 */
	public function prepare_admin_notices() {
		$message = wp_stream_filter_input( INPUT_GET, 'message' );

		switch ( $message ) {
			case 'settings_reset':
				$this->notice( esc_html__( 'All site settings have been successfully reset.', 'stream' ) );
				break;
		}
	}

	/**
	 * Handle notice messages according to the appropriate context (WP-CLI or the WP Admin)
	 *
	 * @param string $message Message to output.
	 * @param bool   $is_error If the message is error_level (true) or warning (false).
	 */
	public function notice( $message, $is_error = true ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$message = wp_strip_all_tags( $message );

			if ( $is_error ) {
				WP_CLI::warning( $message );
			} else {
				WP_CLI::success( $message );
			}
		} else {
			// Trigger admin notices late, so that any notices which occur during page load are displayed.
			add_action( 'shutdown', array( $this, 'admin_notices' ) );

			$notice = compact( 'message', 'is_error' );

			if ( ! in_array( $notice, $this->notices, true ) ) {
				$this->notices[] = $notice;
			}
		}
	}

	/**
	 * Show an error or other message in the WP Admin
	 *
	 * @action shutdown
	 */
	public function admin_notices() {
		global $allowedposttags;

		$custom = array(
			'progress' => array(
				'class' => true,
				'id'    => true,
				'max'   => true,
				'style' => true,
				'value' => true,
			),
		);

		$allowed_html = array_merge( $allowedposttags, $custom );

		ksort( $allowed_html );

		foreach ( $this->notices as $notice ) {
			$class_name   = empty( $notice['is_error'] ) ? 'updated' : 'error';
			$html_message = sprintf( '<div class="%s">%s</div>', esc_attr( $class_name ), wpautop( $notice['message'] ) );

			echo wp_kses( $html_message, $allowed_html );
		}
	}


	/**
	 * Retrieves the size of the blog record table for a specific blog.
	 *
	 * @param int|null $blog_id The ID of the blog. If not provided, the current blog ID will be used.
	 * @return int The size of the blog record table.
	 */
	public function get_blog_record_table_size( $blog_id = null ): int {
		global $wpdb;

		$blog_id = empty( $blog_id ) ? get_current_blog_id() : $blog_id;

		$blog_size = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->stream} WHERE `blog_id`=%d",
				$blog_id
			)
		);

		return (int) $blog_size;
	}

	/**
	 * Returns the admin action links.
	 *
	 * @filter plugin_action_links
	 *
	 * @param array  $links Action links.
	 * @param string $file  Plugin file.
	 *
	 * @return array
	 */
	public function plugin_action_links( $links, $file ) {
		if ( plugin_basename( $this->plugin->locations['dir'] . 'stream.php' ) !== $file ) {
			return $links;
		}

		// Also don't show links in Network Admin if Stream isn't network enabled.
		if ( is_network_admin() && $this->plugin->is_multisite_not_network_activated() ) {
			return $links;
		}

		if ( is_network_admin() ) {
			$admin_page_url = add_query_arg(
				array(
					'page' => $this->network->network_settings_page_slug,
				),
				network_admin_url( $this->admin_parent_page )
			);
		} else {
			$admin_page_url = add_query_arg(
				array(
					'page' => $this->settings_page_slug,
				),
				admin_url( $this->admin_parent_page )
			);
		}

		$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $admin_page_url ), esc_html__( 'Settings', 'stream' ) );

		return $links;
	}

	/**
	 * Check if a particular role has access
	 *
	 * The user_has_cap/role_has_cap filters that call this are registered in the
	 * constructor, but the Settings object is not constructed until init priority 9.
	 * A capability check fired before then (e.g. by a security plugin evaluating
	 * firewall rules on plugins_loaded) must be denied rather than fatal on the
	 * null options chain.
	 *
	 * @param string $role  User role.
	 *
	 * @return bool
	 */
	private function role_can_view( $role ) {
		$allowed_roles = $this->plugin->settings->options['general_role_access'] ?? array();

		return in_array( $role, (array) $allowed_roles, true );
	}

	/**
	 * Filter user caps to dynamically grant our view cap based on allowed roles
	 *
	 * @param array   $allcaps  All capabilities.
	 * @param array   $caps     Required caps.
	 * @param array   $args     Unused.
	 * @param WP_User $user     User.
	 *
	 * @filter user_has_cap
	 *
	 * @return array
	 */
	public function filter_user_caps( $allcaps, $caps, $args, $user = null ) {
		global $wp_roles;

		$_wp_roles = isset( $wp_roles ) ? $wp_roles : new WP_Roles();

		$user = is_a( $user, 'WP_User' ) ? $user : wp_get_current_user();

		// @see
		// https://github.com/WordPress/WordPress/blob/c67c9565f1495255807069fdb39dac914046b1a0/wp-includes/capabilities.php#L758
		$roles = array_unique(
			array_merge(
				$user->roles,
				array_filter(
					array_keys( $user->caps ),
					array( $_wp_roles, 'is_role' )
				)
			)
		);

		$stream_view_caps = array( $this->view_cap );

		foreach ( $caps as $cap ) {
			if ( in_array( $cap, $stream_view_caps, true ) ) {
				foreach ( $roles as $role ) {
					if ( $this->role_can_view( $role ) ) {
						$allcaps[ $cap ] = true;

						break 2;
					}
				}
			}
		}

		return $allcaps;
	}

	/**
	 * Filter role caps to dynamically grant our view cap based on allowed roles
	 *
	 * @filter role_has_cap
	 *
	 * @param array  $allcaps  All capabilities.
	 * @param string $cap      Require cap.
	 * @param string $role     User role.
	 *
	 * @return array
	 */
	public function filter_role_caps( $allcaps, $cap, $role ) {
		$stream_view_caps = array( $this->view_cap );

		if ( in_array( $cap, $stream_view_caps, true ) && $this->role_can_view( $role ) ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}
}
