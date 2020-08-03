<?php
/**
 * Tests for Jetpack Connector class callbacks.
 */

namespace WP_Stream;

class Test_WP_Stream_Connector_Jetpack extends WP_StreamTestCase {

	/**
	 * Runs before each test
	 */
	public function setUp() {
		parent::setUp();

		// Make partial of Connector_Installer class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_Jetpack::class )
			->setMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();
	}

	public function test_jetpack_installed_and_activated() {
		$this->assertTrue( class_exists( 'Jetpack', 'Jetpack is inactive' ) );
	}

	public function test_callback_jetpack_log_entry() {
		// Prepare scenario

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(

			);

		// Do stuff.

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_jetpack_log_entry' ) );
	}

	public function test_callback_sharing_get_services_state() {
		// Prepare scenario.
		require_once JETPACK__PLUGIN_DIR. 'modules/sharedaddy/sharing-service.php';
		$sharer = new \Sharing_Service();

		// Expected log calls.
		$this->mock->expects( $this->exactly( 2 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( __( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => 'Sharing options',
							'option'       => 'sharing-options',
							'old_value'    => null,
							'new_value'    => array(
								'global' => array(
									'button_style'  => 'icon-text',
									'sharing_label' => 'Share this:',
									'open_links'    => 'same',
									'show'          => array( 'post', 'page' ),
									'custom'        => array(),
								),
							)
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'sharedaddy' ),
					$this->equalTo( 'updated' )
				),
				array(
					$this->equalTo( __( 'Sharing services updated', 'stream' ) ),
					$this->equalTo(
						array(
							'services'          => array(
								'print'            => 'Share_Print',
								'facebook'         => 'Share_Facebook',
								'linkedin'         => 'Share_LinkedIn',
								'reddit'           => 'Share_Reddit',
								'twitter'          => 'Share_Twitter',
								'tumblr'           => 'Share_Tumblr',
								'pinterest'        => 'Share_Pinterest',
								'pocket'           => 'Share_Pocket',
								'telegram'         => 'Share_Telegram',
								'jetpack-whatsapp' => 'Jetpack_Share_WhatsApp',
								'skype'            => 'Share_Skype',
							),
							'available'         => array(
								'print',
								'facebook',
								'linkedin',
								'reddit',
								'twitter',
								'tumblr',
								'pinterest',
								'pocket',
								'telegram',
								'jetpack-whatsapp',
								'skype',
							),
							'hidden'            => array(),
							'visible'           => array(),
							'currently_enabled' => array(
								'visible' => array(
									'twitter'  => new \Share_Twitter(),
									'facebook' => new \Share_Facebook(),
								),
								'hidden'  => array(),
								'all'     => array( 'twitter', 'facebook' ),
							),
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'sharedaddy' ),
					$this->equalTo( 'updated' )
				)
			);

		// Do stuff.
		$sharer->set_blog_services(
			array( 'facebook' => 'Share_Facebook' ),
			array( 'reddit' => 'Share_Reddit' )
		);

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_sharing_get_services_state' ) );
	}

	public function test_callback_jetpack_module_configuration_load_monitor() {
		// Prepare scenario

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(

			);

		// Do stuff.

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_jetpack_module_configuration_load_monitor' ) );
	}

	public function test_check_function() {
		// Prepare scenario

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(

			);

		// Do stuff.

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_add_option' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_update_option' ) );
	}

	public function test_track_post_by_email_function() {
		// Prepare scenario

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(

			);

		// Do stuff.

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_wp_ajax_jetpack_post_by_email_enable' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_wp_ajax_jetpack_post_by_email_regenerate' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_wp_ajax_jetpack_post_by_email_disable' ) );
	}
}
