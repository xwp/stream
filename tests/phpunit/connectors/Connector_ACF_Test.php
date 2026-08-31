<?php
/**
 * WP Integration Test w/ Advanced Custom Fields
 *
 * Tests for ACF connector class callbacks.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

class Connector_ACF_Test extends WP_StreamTestCase {
	/**
	 * Holds ACF group key used throughout test.
	 *
	 * @var string
	 */
	protected $group_key = 'test_group';

	/**
	 * Recorded log() arguments from mocked connector callbacks.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private static $recorded_log_calls = array();

	/**
	 * Records log() arguments for post-hoc assertions.
	 *
	 * @param mixed ...$args Log method arguments.
	 * @return void
	 */
	public static function record_log_call( ...$args ) {
		self::$recorded_log_calls[] = $args;
	}

	/**
	 * Runs before each test
	 */
	public function setUp(): void {
		parent::setUp();

		self::$recorded_log_calls = array();

		$this->plugin->connectors->unload_connectors();

		// Make partial of Connector_ACF class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_ACF::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		// Register connector.
		$this->mock->register();
	}

	/**
	 * Create/Update ACF field group
	 *
	 * @param array $config  ACF field group configuration.
	 */
	private function update_acf_field_group( $config = array() ) {
		$defaults = array(
			'key'                   => $this->group_key,
			'title'                 => 'Test Group',
			'fields'                => array(),
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => '',
		);

		return \acf_update_field_group( array_merge( $defaults, $config ) );
	}

	/**
	 * Create/Update ACF field.
	 *
	 * @param array $config  ACF field configuration.
	 */
	private function update_acf_field( $config = array() ) {
		$defaults = array(
			'parent'            => $this->group_key,
			'key'               => uniqid(),
			'label'             => 'Test Field',
			'name'              => 'test_field',
			'type'              => 'text',
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => array(
				'width' => '',
				'class' => '',
				'id'    => '',
			),
			'default_value'     => 'Yes sir!',
			'placeholder'       => '',
			'prepend'           => '',
			'append'            => '',
			'maxlength'         => '',
		);

		return \acf_update_field( array_merge( $defaults, $config ) );
	}

	/**
	 * Confirm that ACF is installed and active.
	 */
	public function test_acf_installed_and_activated() {
		$this->assertTrue( is_callable( 'acf' ) );
	}

	/**
	 * Tests the "callback_save_post" callback for new field groups.
	 */
	public function test_callback_save_post() {
		$this->mock->expects( $this->exactly( 2 ) )
			->method( 'log' )
			->willReturnCallback( array( self::class, 'record_log_call' ) );

		$this->update_acf_field_group();
		$this->update_acf_field();

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_save_post' ) );
		$this->assertSame( 1, did_action( 'acf/update_field_group' ) );

		$this->assertSame(
			esc_html_x( 'Position of "%1$s" updated to "%2$s"', 'acf', 'stream' ),
			self::$recorded_log_calls[0][0]
		);
		$this->assertSame(
			array(
				'title'        => 'Test Group',
				'option_label' => esc_html_x( 'Normal (after content)', 'acf', 'stream' ),
				'option'       => 'position',
				'option_value' => 'normal',
			),
			self::$recorded_log_calls[0][1]
		);
		$this->assertSame( 'options', self::$recorded_log_calls[0][3] );
		$this->assertSame( 'updated', self::$recorded_log_calls[0][4] );

		$this->assertSame(
			esc_html_x( '"%1$s" set to display on "%2$s"', 'acf', 'stream' ),
			self::$recorded_log_calls[1][0]
		);
		$this->assertSame(
			array(
				'title'        => 'Test Group',
				'option_label' => esc_html_x( 'No screens', 'acf', 'stream' ),
				'option'       => 'hide_on_screen',
				'option_value' => '',
			),
			self::$recorded_log_calls[1][1]
		);
		$this->assertSame( 'options', self::$recorded_log_calls[1][3] );
		$this->assertSame( 'updated', self::$recorded_log_calls[1][4] );
	}

	/**
	 * Tests the "callback_post_updated" callback.
	 */
	public function test_callback_post_updated() {
		// Register test ACF field group and field for later use.
		$field_group = $this->update_acf_field_group();
		$field       = $this->update_acf_field();

		$this->mock->expects( $this->exactly( 1 ) )
			->method( 'log' )
			->with(
				$this->equalTo( esc_html_x( 'Position of "%1$s" updated to "%2$s"', 'acf', 'stream' ) ),
				$this->equalTo(
					array(
						'title'        => $field_group['title'],
						'option_label' => esc_html_x( 'High (after title)', 'acf', 'stream' ),
						'option'       => 'position',
						'option_value' => 'acf_after_title',
					)
				),
				$this->equalTo( $field_group['ID'] ),
				$this->equalTo( 'options' ),
				$this->equalTo( 'updated' )
			);

		// Update field group.
		$field_group['position'] = 'acf_after_title';
		$this->update_acf_field_group( $field_group );

		// Check callback test action.
		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_post_updated' ) );

		// 'acf/update_field_group' is called at the end of "acf_update_field()".
		$this->assertSame( 2, did_action( 'acf/update_field_group' ) );
	}

	/**
	 * Tests the "check_meta_values" function for adding post meta.
	 */
	public function test_check_meta_values_add_post_meta() {
		$field_group = $this->update_acf_field_group(
			array(
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);
		$field       = $this->update_acf_field();
		$post_id     = self::factory()->post->create( array( 'post_title' => 'Test post' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( esc_html_x( '"%1$s" of "%2$s" %3$s updated', 'acf', 'stream' ) ),
				$this->equalTo(
					array(
						'field_label'   => $field['label'],
						'title'         => 'Test post',
						'singular_name' => 'post',
						'meta_value'    => 'Yes sir!',
						'meta_key'      => 'test_field',
						'meta_type'     => 'post',
					)
				),
				$this->equalTo( $post_id ),
				$this->equalTo( 'values' ),
				$this->equalTo( 'updated' )
			);

		update_field( 'test_field', 'Yes sir!', $post_id );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_added_post_meta' ) );
	}

	/**
	 * Tests the "check_meta_values" function for updating post meta.
	 */
	public function test_check_meta_values_update_post_meta() {
		$field_group = $this->update_acf_field_group(
			array(
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);
		$field       = $this->update_acf_field();
		$post_id     = self::factory()->post->create( array( 'post_title' => 'Test post' ) );

		update_field( 'test_field', 'Yes sir!', $post_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( esc_html_x( '"%1$s" of "%2$s" %3$s updated', 'acf', 'stream' ) ),
				$this->equalTo(
					array(
						'field_label'   => $field['label'],
						'title'         => 'Test post',
						'singular_name' => 'post',
						'meta_value'    => '',
						'meta_key'      => 'test_field',
						'meta_type'     => 'post',
					)
				),
				$this->equalTo( $post_id ),
				$this->equalTo( 'values' ),
				$this->equalTo( 'updated' )
			);

		update_field( 'test_field', '', $post_id );

		$this->assertGreaterThan( 0, did_action( $this->action_prefix . 'callback_updated_post_meta' ) );
	}

	/**
	 * Tests the "check_meta_values" function for user meta.
	 */
	public function test_check_meta_values_user_meta() {
		$field_group = $this->update_acf_field_group(
			array(
				'location' => array(
					array(
						array(
							'param'    => 'current_user',
							'operator' => '==',
							'value'    => 'logged_in',
						),
					),
				),
			)
		);
		$field       = $this->update_acf_field();
		$user_id     = self::factory()->user->create(
			array(
				'username'     => 'testuser',
				'display_name' => 'testuser',
			)
		);

		\wp_set_current_user( $user_id );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( esc_html_x( '"%1$s" of "%2$s" %3$s updated', 'acf', 'stream' ) ),
				$this->equalTo(
					array(
						'field_label'   => $field['label'],
						'title'         => 'testuser',
						'singular_name' => 'user',
						'meta_value'    => 'Yes sir!',
						'meta_key'      => 'test_field',
						'meta_type'     => 'user',
					)
				),
				$this->equalTo( $user_id ),
				$this->equalTo( 'values' ),
				$this->equalTo( 'updated' )
			);

		update_field( 'test_field', 'Yes sir!', "user_{$user_id}" );
	}
}
