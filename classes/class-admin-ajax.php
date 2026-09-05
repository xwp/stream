<?php
/**
 * Handles Stream admin Ajax endpoints.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Admin_Ajax
 */
class Admin_Ajax {

	/**
	 * Class constructor.
	 *
	 * @param Admin       $admin Admin façade.
	 * @param Admin_Purge $purge Purge / erase collaborator.
	 */
	public function __construct( private Admin $admin, private Admin_Purge $purge ) {
		$this->register_hooks();
	}

	/**
	 * Register WordPress actions and filters for admin Ajax handlers.
	 */
	private function register_hooks(): void {
		add_action( 'wp_ajax_wp_stream_reset', array( $this, 'wp_ajax_reset' ) );
		add_action( 'wp_ajax_wp_stream_clean_orphan_meta', array( $this, 'wp_ajax_clean_orphan_meta' ) );
		add_action( 'wp_ajax_wp_stream_filters', array( $this, 'ajax_filters' ) );
		add_action( 'admin_notices', array( $this, 'maybe_display_message' ) );
		add_action( 'network_admin_notices', array( $this, 'maybe_display_message' ) );
	}

	/**
	 * Handle the reset AJAX request to reset logs.
	 *
	 * @return bool
	 */
	public function wp_ajax_reset() {
		check_ajax_referer( 'stream_nonce_reset', 'wp_stream_nonce_reset' );

		if ( ! current_user_can( $this->admin->settings_cap ) ) {
			wp_die(
				esc_html__( "You don't have sufficient privileges to do this action.", 'stream' )
			);
		}

		// Ensure the database tables exist before attempting to clear records.
		// Install::check() short-circuits on DOING_AJAX, so call install()
		// directly. dbDelta is idempotent and safe to run when tables already
		// exist.
		$this->admin->plugin->install->install( $this->admin->plugin->get_version() );

		$this->purge->erase_stream_records();

		if ( defined( 'WP_STREAM_TESTS' ) && WP_STREAM_TESTS ) {
			return true;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => is_network_admin() ? $this->admin->network->network_settings_page_slug : $this->admin->settings_page_slug,
					'message' => 'data_erased',
				),
				self_admin_url( $this->admin->admin_parent_page )
			)
		);

		exit;
	}

	/**
	 * Ajax handler for the "Clean orphaned meta now" button on
	 * Settings → Advanced.
	 *
	 * Schedules an immediate async run of the orphan reaper. Idempotent:
	 * if a reaper is already scheduled, returns without enqueuing a second.
	 *
	 * Returns true under WP_STREAM_TESTS so PHPUnit can call this directly
	 * without exiting the worker.
	 *
	 * @return bool|void True under tests; otherwise redirects and exits.
	 */
	public function wp_ajax_clean_orphan_meta() {
		if ( ! current_user_can( $this->admin->settings_cap ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'stream' ), 403 );
		}

		check_ajax_referer( 'stream_nonce_clean_orphan_meta', 'wp_stream_nonce_clean_orphan_meta' );

		if ( empty( $this->admin->plugin->scheduler ) ) {
			wp_die( esc_html__( 'No scheduler is available.', 'stream' ), 500 );
		}

		// Idempotency: skip enqueue when any auto-purge action is already
		// pending or running. is_running_auto_purge() checks PENDING + RUNNING
		// across the batch worker and the reaper, so a chain that will run its
		// own terminal reaper is not duplicated by a manual click landing in
		// the small CSRF/stale-URL window where the UI link is hidden.
		if ( ! $this->purge->is_running_auto_purge() ) {
			$this->admin->plugin->scheduler->enqueue_async( Admin::AUTO_PURGE_REAPER_ACTION, array(), Admin::AUTO_PURGE_GROUP );
		}

		if ( defined( 'WP_STREAM_TESTS' ) && WP_STREAM_TESTS ) {
			return true;
		}

		$is_network = $this->admin->plugin->is_multisite_network_activated();
		$page_slug  = $is_network ? $this->admin->network->network_settings_page_slug : $this->admin->settings_page_slug;
		$base_url   = $is_network ? network_admin_url( $this->admin->admin_parent_page ) : admin_url( $this->admin->admin_parent_page );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => $page_slug,
					'wp_stream_message' => 'orphan_meta_cleanup_scheduled',
				),
				$base_url
			)
		);
		exit;
	}

	/**
	 * Ajax callback for return a user list.
	 *
	 * @action wp_ajax_wp_stream_filters
	 */
	public function ajax_filters() {
		if ( ! defined( 'DOING_AJAX' ) || ! current_user_can( $this->admin->settings_cap ) ) {
			wp_die( '-1' );
		}

		check_ajax_referer( 'stream_filters_user_search_nonce', 'nonce' );

		switch ( wp_stream_filter_input( INPUT_GET, 'filter' ) ) {
			case 'user_id':
				$users = array_merge(
					array(
						0 => (object) array(
							'display_name' => 'WP-CLI',
						),
					),
					get_users()
				);

				$search = wp_stream_filter_input( INPUT_GET, 'q' );
				if ( is_string( $search ) && '' !== $search ) {
					// `search` arg for get_users() is not enough.
					$filtered = array();
					foreach ( $users as $key => $user ) {
						if ( self::user_display_name_contains( $user, $search ) ) {
							$filtered[ $key ] = $user;
						}
					}
					$users = $filtered;
				}

				if ( count( $users ) > $this->admin->preload_users_max ) {
					$users = array_slice( $users, 0, $this->admin->preload_users_max );
				}

				// Get gravatar / roles for final result set.
				$results = $this->get_users_record_meta( $users );

				break;
		}

		if ( isset( $results ) ) {
			echo wp_json_encode( $results );
		}

		die();
	}

	/**
	 * Return relevant user meta data for Ajax filter results.
	 *
	 * @param array $authors Author data keyed by user ID.
	 * @return array
	 */
	public function get_users_record_meta( $authors ) {
		$authors_records = array();

		foreach ( $authors as $user_id => $args ) {
			$author = new Author( $args->ID );

			$authors_records[ $user_id ] = array(
				'text'  => $author->get_display_name(),
				'id'    => $author->id,
				'label' => $author->get_display_name(),
				'icon'  => $author->get_avatar_src( 32 ),
				'title' => '',
			);
		}

		return $authors_records;
	}

	/**
	 * Render confirmation notices keyed by the wp_stream_message query arg.
	 *
	 * @action admin_notices
	 * @action network_admin_notices
	 *
	 * @return void
	 */
	public function maybe_display_message() {
		$message = wp_stream_filter_input( INPUT_GET, 'wp_stream_message' );
		if ( empty( $message ) ) {
			return;
		}

		$notices = array(
			'orphan_meta_cleanup_scheduled' => __(
				'Orphaned meta cleanup scheduled. Progress is visible under Tools → Scheduled Actions.',
				'stream'
			),
		);

		if ( ! isset( $notices[ $message ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $notices[ $message ] )
		);
	}

	/**
	 * Whether a user display name contains the search needle.
	 *
	 * @param object $user   User-like object with display_name.
	 * @param string $search Search needle.
	 * @return bool
	 */
	private static function user_display_name_contains( $user, string $search ): bool {
		return false !== mb_strpos( mb_strtolower( $user->display_name ), mb_strtolower( $search ) );
	}
}
