<?php
namespace WP_Stream;

/**
 * Class Test_Live_Update
 *
 * @package WP_Stream
 */
class Test_Live_Update extends WP_StreamTestCase {
	/**
	 * Live update instance under test.
	 *
	 * @var Live_Update
	 */
	protected $live_update;

	public function setUp(): void {
		parent::setUp();

		$this->live_update = new Live_Update( $this->plugin );
	}

	public function tearDown(): void {
		unset( $_POST['action'], $_POST['nonce'], $_POST['checked'], $_POST['heartbeat'], $_POST['user'] );

		parent::tearDown();
	}

	public function test_enable_live_update_only_updates_current_user() {
		$attacker_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$victim_id   = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$this->plugin->settings->options['general_role_access'] = array( 'subscriber' );
		wp_set_current_user( $attacker_id );

		$this->assertTrue( current_user_can( $this->plugin->admin->view_cap ) );

		update_user_meta( $attacker_id, $this->live_update->user_meta_key, 'off' );
		update_user_meta( $victim_id, $this->live_update->user_meta_key, 'off' );

		$_POST['action']    = 'stream_enable_live_update';
		$_POST['nonce']     = wp_create_nonce( $this->live_update->user_meta_key . '_nonce' );
		$_POST['checked']   = 'checked';
		$_POST['heartbeat'] = 'true';
		$_POST['user']      = (string) $victim_id;

		try {
			$this->_handleAjax( 'stream_enable_live_update' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_success() terminates the AJAX request.
		}

		$this->assertSame( 'on', get_user_meta( $attacker_id, $this->live_update->user_meta_key, true ) );
		$this->assertSame( 'off', get_user_meta( $victim_id, $this->live_update->user_meta_key, true ) );
	}

	public function test_enable_live_update_denies_users_without_view_cap() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$this->plugin->settings->options['general_role_access'] = array( 'administrator' );
		wp_set_current_user( $user_id );

		$this->assertFalse( current_user_can( $this->plugin->admin->view_cap ) );

		update_user_meta( $user_id, $this->live_update->user_meta_key, 'off' );

		$_POST['action']    = 'stream_enable_live_update';
		$_POST['nonce']     = wp_create_nonce( $this->live_update->user_meta_key . '_nonce' );
		$_POST['checked']   = 'checked';
		$_POST['heartbeat'] = 'true';

		try {
			$this->_handleAjax( 'stream_enable_live_update' );
		} catch ( \WPAjaxDieContinueException $e ) {
			// Expected: wp_send_json_error() terminates the AJAX request.
		}

		$this->assertSame( 'off', get_user_meta( $user_id, $this->live_update->user_meta_key, true ) );
	}
}
