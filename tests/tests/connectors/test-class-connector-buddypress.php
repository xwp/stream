<?php
/**
 * WP Integration Test w/ BuddyPress
 *
 * Tests for BuddyPress connector class callbacks.
 *
 * @package WP_Stream
 */
namespace WP_Stream;

class Test_WP_Stream_Connector_BuddyPress extends WP_StreamTestCase {

	/**
	 * Runs before all tests
	 */
	public static function wpSetUpBeforeClass() {
		/**
		 * Ensure all BuddyPress components are loaded.
		 */
		delete_option( 'bp-active-components' );
		delete_option( 'bp-deactivated-components' );
		buddypress();
	}

	/**
	 * Run before each test
	 */
	public function setUp() {
		parent::setUp();

		// Make partial of Connector_BuddyPress class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_BuddyPress::class )
			->setMethods( array( 'log' ) )
			->getMock();

		$this->mock->register();
	}

	/**
	 * Run after each test
	 */
	public function tearDown() {
		parent::tearDown();
	}

	public function test_bbpress_installed_and_activated() {
		$this->assertTrue( is_callable( 'buddypress' ) );
	}

	public function test_option_callbacks() {
		// Expected log actions.
		$this->mock->expects( $this->atLeastOnce() )
			->method( 'log' )
			->withConsecutive(
				array(
					/* translators: %s: setting name (e.g. "Group Creation") */
					$this->equalTo( _( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => esc_html_x( 'Group Creation', 'buddypress', 'stream' ),
							'option'       => 'bp_restrict_group_creation',
							'old_value'    => null,
							'new_value'    => false,
							'page'         => 'bp-settings',
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' )
				),
				array(
					/* translators: %s: setting name (e.g. "Group Creation") */
					$this->equalTo( _( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => esc_html_x( 'Group Creation', 'buddypress', 'stream' ),
							'option'       => 'bp_restrict_group_creation',
							'old_value'    => false,
							'new_value'    => true,
							'page'         => 'bp-settings',
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' )
				),
				array(
					/* translators: %s: setting name (e.g. "Group Creation") */
					$this->equalTo( _( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => esc_html_x( 'Group Creation', 'buddypress', 'stream' ),
							'option'       => 'bp_restrict_group_creation',
							'old_value'    => null,
							'new_value'    => null,
							'page'         => 'bp-settings',
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' )
				),
				array(
					$this->equalTo(
						sprintf(
							// translators: Placeholder refers to component title (e.g. "Members")
							__( '"%1$s" component %2$s', 'stream' ),
							'Extended Profiles',
							'activated'
						)
					),
					$this->equalTo(
						array(
							'option'     => 'xprofile',
							'option_key' => 'bp-active-components',
							'old_value'  => null,
							'value'      => array(
								'title'       => __( 'Extended Profiles', 'buddypress' ),
								'description' => __( 'Customize your community with fully editable profile fields that allow your users to describe themselves.', 'buddypress' )
							),
						)
					),

					$this->equalTo( null ),
					$this->equalTo( 'components' ),
					$this->equalTo( 'activated' ),
				)
			);

		// Modify BuddyPress-related options to trigger callbacks.
		add_option( 'bp_restrict_group_creation', false );
		update_option( 'bp_restrict_group_creation', true );
		delete_option( 'bp_restrict_group_creation' );

		bp_update_option( 'bp-active-components', bp_core_get_components( 'all' ) );

		// Check callback test actions.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_add_option' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_update_option' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_delete_option' ) );
	}

	public function test_activity_callbacks() {
		// Create activity for later use.
		$activity_id = \bp_activity_add(
			array(
				'component' => 'testComponent',
				'content'   => 'Testing testing 123'
			)
		);

		$activity = new \BP_Activity_Activity( $activity_id );

		// Expected log actions.
		$this->mock->expects( $this->atLeastOnce() )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo(
						sprintf(
							/* translators: %s an activity title (e.g. "Update") */
							__( 'Marked activity "%s" as spam', 'stream' ),
							wp_strip_all_tags( $activity->action )
						)
					),
					$this->equalTo(
						array(
							'id'      => $activity->id,
							'item_id' => $activity->item_id,
							'type'    => $activity->type,
							'author'  => $activity->user_id,
						)
					),
					$this->equalTo(	$activity->id ),
					$this->equalTo( $activity->component ),
					$this->equalTo( 'spammed' ),
				),
				array(
					$this->equalTo(
						sprintf(
							/* translators: %s: an activity title (e.g. "Update") */
							__( 'Unmarked activity "%s" as spam', 'stream' ),
							wp_strip_all_tags( $activity->action )
						)
					),
					$this->equalTo(
						array(
							'id'      => $activity->id,
							'item_id' => $activity->item_id,
							'type'    => $activity->type,
							'author'  => $activity->user_id,
						)
					),
					$this->equalTo(	$activity->id ),
					$this->equalTo( $activity->component ),
					$this->equalTo( 'unspammed' ),
				),
				array(
					$this->equalTo(
						sprintf(
							/* translators: %s: an activity title (e.g. "Update") */
							__( '"%s" activity deleted', 'stream' ),
							wp_strip_all_tags( $activity->action )
						)
					),
					$this->equalTo(
						array(
							'id'      => $activity->id,
							'item_id' => $activity->item_id,
							'type'    => $activity->type,
							'author'  => $activity->user_id,
						)
					),
					$this->equalTo( $activity->id ),
					$this->equalTo( $activity->component ),
					$this->equalTo( 'deleted' ),
				)
			);

		// Update activity to trigger callbacks.
		\bp_activity_mark_as_spam( $activity_id );
		\bp_activity_mark_as_ham( $activity_id );
		\bp_activity_delete_by_activity_id( $activity_id );

		// Check callback test actions.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_bp_activity_mark_as_spam' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_bp_activity_mark_as_ham' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_bp_before_activity_delete' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_bp_activity_deleted_activities' ) );
	}

	public function test_group_action_callbacks() {
		// Expected log actions.
		$this->mock->expects( $this->atLeastOnce() )
			->method( 'log' )
			->withConsecutive(

			);

		// Do stuff.

		// Check that all callback test actions were executed.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_' ) );
	}

	public function test_profile_action_callbacks() {
		// Expected log actions.
		$this->mock->expects( $this->atLeastOnce() )
			->method( 'log' )
			->withConsecutive(

			);

		// Do stuff.

		// Check that all callback test actions were executed.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_' ) );
	}
}
