<?php
/**
 * Tests for Jetpack Connector class callbacks.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Test_WP_Stream_Connector_Jetpack extends WP_StreamTestCase {

	/**
	 * Rest server instance.
	 *
	 * @var WP_REST_Server $wp_rest_server
	 */
	protected $server;

	protected $namespaced_route = '/jetpack/v4';

	/**
	 * Runs before each test
	 */
	public function setUp(): void {
		parent::setUp();

		global $wp_rest_server;

		$wp_rest_server = new \WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );

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
		// Get blog details and create user for later use.
		$user_id = self::factory()->user->create( array( 'display_name' => 'testuser' ) );
		$user    = new \WP_User( $user_id );

		// Expected log calls.
		$this->mock->expects( $this->exactly( 3 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					'Comments module activated',
					array( 'module_slug' => 'comments' ),
					null,
					'modules',
					'activated',
				),
				array(
					'testuser\'s account linked to Jetpack',
					array(
						'user_id'    => $user_id,
						'user_email' => $user->user_email,
						'user_login' => $user->user_login,
					),
					null,
					'users',
					'authorize',
				),
				array(
					'Site connected to Jetpack',
					array(),
					null,
					'blogs',
					'register',
				)
			);

		// Run Jetpack log function to trigger callback.
		\Jetpack::log( 'activate', 'comments' );
		\Jetpack::log( 'authorize', $user_id );
		\Jetpack::log( 'register' );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_jetpack_log_entry' ) );
	}

	public function test_callback_sharing_get_services_state() {
		// Create sharing service instance and services object for later use.
		require_once JETPACK__PLUGIN_DIR . 'modules/sharedaddy/sharing-service.php';
		$sharer   = new \Sharing_Service();
		$services = $sharer->get_all_services();

		// Expected log calls.
		$this->mock->expects( $this->exactly( 1 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( __( 'Sharing services updated', 'stream' ) ),
					$this->equalTo(
						array(
							'services'          => $services,
							'available'         => array_keys( $services ),
							'hidden'            => array(),
							'visible'           => array(),
							'currently_enabled' => $sharer->get_blog_services(),
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'sharedaddy' ),
					$this->equalTo( 'updated' ),
				)
			);

		// Update sharing services to trigger callback.
		$sharer->set_blog_services(
			array( 'facebook' => 'Share_Facebook' ),
			array( 'reddit' => 'Share_Reddit' )
		);

		// Check callback test action.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_sharing_get_services_state' ) );
	}

	public function test_callback_jetpack_module_configuration_load_monitor() {
		// Prepare scenario

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( 'Monitor notifications %s', 'stream' ) ),
				$this->equalTo(
					array(
						'status'    => esc_html__( 'activated', 'stream' ),
						'option'    => 'receive_jetpack_monitor_notification',
						'old_value' => false,
						'value'     => true,
					)
				),
				$this->equalTo( null ),
				$this->equalTo( 'monitor' ),
				$this->equalTo( 'updated' )
			);

		// Simulate "receive_jetpack_monitor_notification" option change to trigger callback.
		$_POST['receive_jetpack_monitor_notification'] = true;
		do_action( 'jetpack_module_configuration_load_monitor' );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_jetpack_module_configuration_load_monitor' ) );
	}

	public function test_check() {
		// Prepare scenario
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// Expected log calls.
		$this->mock->expects( $this->exactly( 2 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( __( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => esc_html__( 'Sharing options', 'stream' ),
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
							),
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'sharedaddy' ),
					$this->equalTo( 'updated' ),
				),
				array(
					$this->equalTo( __( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => esc_html__( 'Sharing options', 'stream' ),
							'option'       => 'sharing-options',
							'old_value'    => array(
								'global' => array(
									'button_style'  => 'icon-text',
									'sharing_label' => 'Share this:',
									'open_links'    => 'same',
									'show'          => array( 'post', 'page' ),
									'custom'        => array(),
								),
							),
							'new_value'    => array(
								'global' => array(
									'button_style'  => 'icon-text',
									'sharing_label' => 'Share what',
									'open_links'    => 'same',
									'show'          => array( 'post', 'page' ),
									'custom'        => array(),
								),
							),
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'sharedaddy' ),
					$this->equalTo( 'updated' ),
				)
			);

		// Test Jetpack REST route.
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( $this->namespaced_route, $routes );

		// Execute REST requests and trigger callbacks.
		$request = new \WP_REST_Request( 'POST', "{$this->namespaced_route}/settings" );
		$request->set_body_params(
			array(
				'carousel'                  => true,
				'carousel_background_color' => 'white',
				'sharedaddy'                => true,
				'sharing_label'             => 'Share what',
			)
		);
		$response = $this->server->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_add_option' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_update_option' ) );
	}

	public function test_track_post_by_email() {
		// Prepare scenario
		$admin_id = self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'TestGuy',
			)
		);
		wp_set_current_user( $admin_id );

		// Expected log calls.
		$this->mock->expects( $this->exactly( 3 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( __( '%1$s %2$s Post by Email', 'stream' ) ),
					$this->equalTo(
						array(
							'user_displayname' => 'TestGuy',
							'action'           => esc_html__( 'enabled', 'stream' ),
							'status'           => true,
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'post-by-email' ),
					$this->equalTo( 'updated' ),
				),
				array(
					$this->equalTo( __( '%1$s %2$s Post by Email', 'stream' ) ),
					$this->equalTo(
						array(
							'user_displayname' => 'TestGuy',
							'action'           => esc_html__( 'disabled', 'stream' ),
							'status'           => false,
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'post-by-email' ),
					$this->equalTo( 'updated' ),
				),
				array(
					$this->equalTo( __( '%1$s %2$s Post by Email', 'stream' ) ),
					$this->equalTo(
						array(
							'user_displayname' => 'TestGuy',
							'action'           => esc_html__( 'regenerated', 'stream' ),
							'status'           => null,
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'post-by-email' ),
					$this->equalTo( 'updated' ),
				)
			);

		// Test Jetpack REST route.
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( $this->namespaced_route, $routes );

		// Manually trigger callbacks.
		do_action( 'wp_ajax_jetpack_post_by_email_enable' );
		do_action( 'wp_ajax_jetpack_post_by_email_disable' );
		do_action( 'wp_ajax_jetpack_post_by_email_regenerate' );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_wp_ajax_jetpack_post_by_email_enable' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_wp_ajax_jetpack_post_by_email_regenerate' ) );
		$this->assertFalse( 0 === did_action( $this->action_prefix . 'callback_wp_ajax_jetpack_post_by_email_disable' ) );
	}

	/**
	 * `jetpack_options` holds all Jetpack credentials in one blob. A record
	 * about one changed setting must carry only that field, and no token from
	 * the other fields.
	 */
	public function test_check_jetpack_options_logs_only_the_changed_field() {
		$logged = array();

		$connector = $this->getMockBuilder( Connector_Jetpack::class )
			->setMethods( array( 'log' ) )
			->getMock();

		$connector->method( 'log' )->willReturnCallback(
			function ( $message, $meta ) use ( &$logged ) {
				$logged[] = $meta;
			}
		);

		$old_value = array(
			'videopress'            => array(
				'access' => 'edit_posts',
			),
			'user_tokens'           => array(
				'1' => 'user.token.one',
			),
			'publicize_connections' => array(
				'facebook' => array(
					'id_1' => array(
						'access_token' => 'facebook.access.token',
					),
				),
			),
		);

		$new_value                         = $old_value;
		$new_value['videopress']['access'] = 'read';

		$connector->check_jetpack_options( $old_value, $new_value );

		$this->assertNotEmpty( $logged, 'A VideoPress setting change must be logged.' );

		$serialized = maybe_serialize( $logged );

		$this->assertStringNotContainsString( 'user.token.one', $serialized, 'A user token must not reach record metadata.' );
		$this->assertStringNotContainsString( 'facebook.access.token', $serialized, 'A Publicize token must not reach record metadata.' );

		$this->assertSame( 'edit_posts', $logged[0]['old_value'] );
		$this->assertSame( 'read', $logged[0]['value'] );
	}

	/**
	 * A Publicize connection holds only credentials, thus the value payload is
	 * withheld. The service label still says what changed.
	 */
	public function test_check_jetpack_options_withholds_publicize_connection_payload() {
		$logged = array();

		$connector = $this->getMockBuilder( Connector_Jetpack::class )
			->setMethods( array( 'log' ) )
			->getMock();

		$connector->method( 'log' )->willReturnCallback(
			function ( $message, $meta ) use ( &$logged ) {
				$logged[] = $meta;
			}
		);

		// The connector reads the service label from Publicize's UI global,
		// which only exists when the module is active.
		global $publicize_ui;

		$original_publicize_ui = $publicize_ui;
		$publicize_ui          = new class() { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			/**
			 * Publicize stub.
			 *
			 * @var object
			 */
			public $publicize;

			/**
			 * Give the label lookup the connector needs.
			 */
			public function __construct() {
				$this->publicize = new class() {
					/**
					 * Service label for a connection name.
					 *
					 * @param string $name Service name.
					 * @return string
					 */
					public function get_service_label( $name ) {
						return ucfirst( $name );
					}
				};
			}
		};

		$old_value = array(
			'publicize_connections' => array(),
		);

		$new_value = array(
			'publicize_connections' => array(
				'facebook' => array(
					'id_1' => array(
						'access_token'        => 'facebook.access.token',
						'access_token_secret' => 'facebook.token.secret',
					),
				),
			),
		);

		try {
			$connector->check_jetpack_options( $old_value, $new_value );
		} finally {
			$publicize_ui = $original_publicize_ui; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$serialized = maybe_serialize( $logged );

		$this->assertStringNotContainsString( 'facebook.access.token', $serialized );
		$this->assertStringNotContainsString( 'facebook.token.secret', $serialized );

		$this->assertNotEmpty( $logged, 'A new Publicize connection must be logged.' );

		foreach ( $logged as $meta ) {
			$this->assertSame( Connector::REDACTED_PLACEHOLDER, $meta['value'] );
		}
	}
}
