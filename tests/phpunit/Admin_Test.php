<?php
namespace WP_Stream;

class Admin_Test extends WP_StreamTestCase {
	/**
	 * Holds the admin base class
	 *
	 * @var Admin
	 */
	protected $admin;

	/**
	 * Holds the administrator id.
	 *
	 * @var int
	 */
	protected $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->plugin->admin;
		$this->assertNotEmpty( $this->admin );

		// Add admin user to test caps
		// We need to change user to verify editing option as admin or editor
		$this->admin_user_id = \WP_UnitTestCase_Base::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'test_admin',
				'email'      => 'test@land.com',
			)
		);
		wp_set_current_user( $this->admin_user_id );
	}

	/**
	 * Tear down after each test. Delete the admin user and start afresh.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tear_down();

		if ( is_multisite() ) {
			wpmu_delete_user( $this->admin_user_id );
		} else {
			wp_delete_user( $this->admin_user_id );
		}
	}

	public function test_construct() {
		$this->assertNotEmpty( $this->admin->plugin );
		$this->assertInstanceOf( '\WP_Stream\Plugin', $this->admin->plugin );

		$this->assertTrue( function_exists( 'is_plugin_active_for_network' ) );

		$site_access_disabled = false;
		if ( is_multisite() && is_plugin_active_for_network( $this->plugin->locations['plugin'] ) && ! is_network_admin() ) {
			$options              = (array) get_site_option( 'wp_stream_network', array() );
			$site_access          = isset( $options['general_site_access'] ) ? absint( $options['general_site_access'] ) : 1;
			$site_access_disabled = ! $site_access;
		}

		$has_admin_menu = has_action( 'admin_menu', array( $this->admin->menu, 'register_menu' ) );
		if ( $site_access_disabled ) {
			$this->assertFalse( $has_admin_menu );
		} else {
			$this->assertNotFalse( $has_admin_menu );
		}
	}

	public function test_init() {
		$this->admin->init();
		$this->assertNotEmpty( $this->admin->network );
		$this->assertNotEmpty( $this->admin->live_update );
		$this->assertNotEmpty( $this->admin->export );

		$this->assertInstanceOf( '\WP_Stream\Network', $this->admin->network );
		$this->assertInstanceOf( '\WP_Stream\Live_Update', $this->admin->live_update );
		$this->assertInstanceOf( '\WP_Stream\Export', $this->admin->export );
	}

	/**
	 * The user_has_cap filter is registered in the Admin constructor, but the
	 * Settings object is only built on init priority 9. A capability check for
	 * the view cap fired before then (e.g. a firewall plugin on plugins_loaded)
	 * must be denied, not fatal on the null options chain.
	 */
	public function test_filter_user_caps_before_settings_initialized() {
		$settings               = $this->plugin->settings;
		$this->plugin->settings = null;

		$user    = get_user_by( 'id', $this->admin_user_id );
		$allcaps = $this->admin->filter_user_caps(
			array(),
			array( $this->admin->view_cap ),
			array( $this->admin->view_cap, $this->admin_user_id ),
			$user
		);

		$this->plugin->settings = $settings;

		$this->assertArrayNotHasKey( $this->admin->view_cap, $allcaps );
	}

	/**
	 * Once Settings exists, the view cap is granted to allowed roles as before.
	 */
	public function test_filter_user_caps_grants_view_cap_to_allowed_role() {
		$user    = get_user_by( 'id', $this->admin_user_id );
		$allcaps = $this->admin->filter_user_caps(
			array(),
			array( $this->admin->view_cap ),
			array( $this->admin->view_cap, $this->admin_user_id ),
			$user
		);

		$this->assertArrayHasKey( $this->admin->view_cap, $allcaps );
		$this->assertTrue( $allcaps[ $this->admin->view_cap ] );
	}

	public function test_prepare_admin_notices() {
		// Test no notices
		$this->admin->notices = array();
		$this->admin->prepare_admin_notices();
		$this->assertEmpty( $this->admin->notices );

		// Test settings reset notice
		$_GET['message'] = 'settings_reset';
		$this->admin->prepare_admin_notices();
		$this->assertNotEmpty( $this->admin->notices );

		// Prevent output
		$this->admin->notices = array();
	}

	public function test_notice() {
		// Start with nothing
		$this->admin->notices = array();
		$this->assertEmpty( $this->admin->notices );

		$message  = 'Affirmative, Dave. I read you.';
		$is_error = false;

		$this->admin->notice( $message, $is_error );
		$this->assertNotEmpty( $this->admin->notices );
		ob_start();
		$this->admin->admin_notices();
		$notice = ob_get_clean();

		$this->assertStringContainsString( $message, $notice );
		$this->assertStringContainsString( 'updated', $notice );
		$this->assertStringNotContainsString( 'error', $notice );

		// Clear notices and start again
		$this->admin->notices = array();
		$this->assertEmpty( $this->admin->notices );

		$is_error = true;

		$this->admin->notice( $message, $is_error );
		$this->assertNotEmpty( $this->admin->notices );
		ob_start();
		$this->admin->admin_notices();
		$notice = ob_get_clean();

		$this->assertStringContainsString( $message, $notice );
		$this->assertStringContainsString( 'error', $notice );
		$this->assertStringNotContainsString( 'updated', $notice );

		// Prevent output
		$this->admin->notices = array();
	}

	public function test_admin_notices() {
		$allowed_html         = '<progress class="migration" max="100"></progress>';
		$disallowed_html      = '<iframe></iframe>';
		$this->admin->notices = array(
			array(
				'message'  => "I'm sorry, Dave. I'm afraid I can't do that. $disallowed_html",
				'is_error' => false,
			),
			array(
				'message'  => "This mission is too important for me to allow you to jeopardize it. $allowed_html",
				'is_error' => false,
			),
		);

		ob_start();
		$this->admin->admin_notices();
		$notices = ob_get_clean();

		$this->assertStringContainsString( $allowed_html, $notices );
		$this->assertStringNotContainsString( $disallowed_html, $notices );
		$this->assertStringContainsString( str_replace( $disallowed_html, '', $this->admin->notices[0]['message'] ), $notices );
		$this->assertStringContainsString( wpautop( $this->admin->notices[1]['message'] ), $notices );

		// Prevent output
		$this->admin->notices = array();
	}
	public function test_plugin_action_links() {
		$links = array( '<a href="javascript:void(0);">Disconnect</a>' );
		$file  = plugin_basename( $this->plugin->locations['dir'] . 'stream.php' );

		$action_links = $this->admin->plugin_action_links( $links, $file );

		$this->assertStringContainsString( 'Disconnect', $action_links[0] );
		$this->assertStringContainsString( 'Settings', $action_links[1] );
	}
	/**
	 * Also tests private method role_can_view
	 */
	public function test_filter_user_caps() {
		$user = new \WP_User( $this->admin_user_id );

		$this->plugin->settings->options['general_role_access'] = array( 'administrator' );
		$this->assertTrue( $user->has_cap( $this->admin->view_cap ) );

		$this->plugin->settings->options['general_role_access'] = array( 'editor' );
		$this->assertFalse( $user->has_cap( $this->admin->view_cap ) );
	}

	/**
	 * Also tests private method role_can_view
	 */
	public function test_filter_role_caps() {
		$role = get_role( 'administrator' );

		$this->plugin->settings->options['general_role_access'] = array( 'administrator' );
		$this->assertTrue( $role->has_cap( $this->admin->view_cap ) );

		$this->plugin->settings->options['general_role_access'] = array( 'editor' );
		$this->assertFalse( $role->has_cap( $this->admin->view_cap ) );
	}

	/**
	 * Integration test for the running-state UI swap. Asserts that the
	 * "Clean Orphaned Meta" field in Settings::get_fields() flips from
	 * type=link to type=none and swaps its description when an auto-purge
	 * chain is active. Admin_Purge::is_running_auto_purge() is covered in
	 * isolation in Admin_Purge_Test; this test closes the loop on the
	 * consumer that drives the UI.
	 *
	 * Replaces the e2e specs removed in b4c8f287 for activation-race
	 * fragility — same assertions, no browser/AS-runner timing surface.
	 */
	public function test_clean_orphan_meta_field_reflects_running_state() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
			as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_REAPER_ACTION );
		}

		$find_field = function () {
			$fields = $this->plugin->settings->get_fields();
			foreach ( $fields['advanced']['fields'] as $field ) {
				if ( isset( $field['name'] ) && 'clean_orphan_meta' === $field['name'] ) {
					return $field;
				}
			}
			return null;
		};

		// Idle: link visible, idle description.
		$idle = $find_field();
		$this->assertNotNull( $idle, 'clean_orphan_meta field must exist on the Advanced tab' );
		$this->assertSame( 'link', $idle['type'], 'Idle state must render as a link' );
		$this->assertStringContainsString(
			'Schedules an immediate background cleanup',
			$idle['desc'],
			'Idle description must explain what the link does'
		);

		// Simulate an active chain by enqueueing a batch action.
		as_enqueue_async_action(
			\WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION,
			array(
				'cutoff'     => '2020-01-01 00:00:00',
				'blog_id'    => 0,
				'last_entry' => 0,
			),
			\WP_Stream\Admin::AUTO_PURGE_GROUP
		);

		$active = $find_field();
		$this->assertNotNull( $active );
		$this->assertSame(
			'none',
			$active['type'],
			'Active state must hide the link by swapping the field type to none'
		);
		$this->assertStringContainsString(
			'Auto-purge is currently running',
			$active['desc'],
			'Active description must communicate why the link is hidden'
		);

		as_unschedule_all_actions( \WP_Stream\Admin::AUTO_PURGE_BATCH_ACTION );
	}

	/**
	 * Integration test for the "Reset Stream Database" running-state UI swap.
	 * Asserts that the delete_all_records field flips from type=link to
	 * type=none and swaps its description when an async-deletion action is
	 * scheduled. Mirrors test_clean_orphan_meta_field_reflects_running_state.
	 */
	public function test_delete_all_records_field_reflects_running_state() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::ASYNC_DELETION_ACTION );
		}

		$find_field = function () {
			$fields = $this->plugin->settings->get_fields();
			foreach ( $fields['advanced']['fields'] as $field ) {
				if ( isset( $field['name'] ) && 'delete_all_records' === $field['name'] ) {
					return $field;
				}
			}
			return null;
		};

		// Idle: link visible, warning description.
		$idle = $find_field();
		$this->assertNotNull( $idle, 'delete_all_records field must exist on the Advanced tab' );
		$this->assertSame( 'link', $idle['type'], 'Idle state must render as a link' );
		$this->assertStringContainsString(
			'Warning',
			$idle['desc'],
			'Idle description must show the deletion warning'
		);

		// Simulate an active deletion by scheduling the action.
		as_enqueue_async_action(
			\WP_Stream\Admin::ASYNC_DELETION_ACTION,
			array(
				'total'      => 1,
				'done'       => 0,
				'last_entry' => 1,
				'blog_id'    => (int) get_current_blog_id(),
			)
		);

		$active = $find_field();
		$this->assertNotNull( $active );
		$this->assertSame(
			'none',
			$active['type'],
			'Active state must hide the link by swapping the field type to none'
		);
		$this->assertStringContainsString(
			'Currently deleting records',
			$active['desc'],
			'Active description must communicate that deletion is in progress'
		);

		as_unschedule_all_actions( \WP_Stream\Admin::ASYNC_DELETION_ACTION );
	}

	/**
	 * Verifies that get_deletion_warning() honours the pre-computed deletion
	 * state passed by build_delete_all_records_field(), avoiding a duplicate
	 * Action Scheduler query inside a single render.
	 */
	public function test_get_deletion_warning_respects_precomputed_state() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( \WP_Stream\Admin::ASYNC_DELETION_ACTION );
		}

		// No deletion scheduled, but caller asserts "running" — message must reflect the argument.
		$this->assertStringContainsString(
			'Currently deleting records',
			$this->plugin->settings->get_deletion_warning( true ),
			'When caller passes true, message must reflect running deletion regardless of AS state'
		);

		// Schedule a real action, but caller asserts "not running" — message must reflect the argument.
		as_enqueue_async_action(
			\WP_Stream\Admin::ASYNC_DELETION_ACTION,
			array(
				'total'      => 1,
				'done'       => 0,
				'last_entry' => 1,
				'blog_id'    => (int) get_current_blog_id(),
			)
		);
		$this->assertStringNotContainsString(
			'Currently deleting records',
			$this->plugin->settings->get_deletion_warning( false ),
			'When caller passes false, message must reflect idle state regardless of AS state'
		);

		as_unschedule_all_actions( \WP_Stream\Admin::ASYNC_DELETION_ACTION );
	}
}
