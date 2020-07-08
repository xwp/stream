<?php
namespace WP_Stream;

class Test_WP_Stream_Connector_BuddyPress extends WP_StreamTestCase {

	public function setUp() {
		parent::setUp();

		// Make partial of Connector_ACF class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_BuddyPress::class )
			->setMethods( [ 'log' ] )
			->getMock();

		$this->mock->register();
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function test_bbpress_installed_and_activated() {
		$this->assertTrue( is_callable( 'buddypress' ) );
		buddypress();
	}

	public function test_option_callbacks() {
		// Expected log actions.
		$this->mock->expects( $this->atLeastOnce() )
			->method( 'log' )
			->withConsecutive(
				[
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
				],
				[
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
				],
				[
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
				],
				[
					sprintf(
						// translators: Placeholder refers to component title (e.g. "Members")
						__( '"%1$s" component %2$s', 'stream' ),
						'Extended Profiles',
						'activated'
					),
					array(
						'option'     => 'xprofile',
						'option_key' => 'bp-active-components',
						'old_value'  => null,
						'value'      => array(
							'title'       => __( 'Extended Profiles', 'buddypress' ),
							'description' => __( 'Customize your community with fully editable profile fields that allow your users to describe themselves.', 'buddypress' )
						),
					),
					null,
					'components',
					'activated'
				]
			);

		// Do stuff.
		add_option( 'bp_restrict_group_creation', false );
		update_option( 'bp_restrict_group_creation', true );
		delete_option( 'bp_restrict_group_creation' );

		bp_update_option( 'bp-active-components', bp_core_get_components( 'all' ) );

		// Check that all callback test actions were executed.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_add_option' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_update_option' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_delete_option' ) );
	}

	public function test_activity_callbacks() {
		// Expected log actions.
		$this->mock->expects( $this->atLeastOnce() )
			->method( 'log' );

		// Do stuff.
		$activity_id = \bp_activity_add(
			array(
				'component' => 'testComponent',
				'content'   => 'Testing testing 123'
			)
		);
		\bp_activity_delete_by_activity_id( $activity_id );

		// Check that all callback test actions were executed.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_' ) );
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
