<?php
/**
 * WP Integration Test w/ GravityForms
 *
 * Tests for GravityForms Connector class callbacks.
 */

namespace WP_Stream;

class Test_WP_Stream_Connector_GravityForms extends WP_StreamTestCase {
	/**
	 * Runs before each test
	 */
	public function setUp() {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		$post_connector = new Connector_Posts();
		$post_connector->register();

		// Make partial of Connector_GravityForms class, with mocked "log" function.
		$this->mock = $this->getMockBuilder( Connector_GravityForms::class )
			->setMethods( array( 'log' ) )
			->getMock();

		$this->mock->register();
	}

	/**
	 * Runs after each test
	 */
	public function tearDown() {
		parent::tearDown();
	}

	public function test_gravityforms_installed_and_activated() {
		$this->assertTrue( class_exists( 'GFForms' ) );
	}

	/**
	 * Creates a new form.
	 *
	 * @param array $form   Form configurations.
	 * @param array $fields Form fields configurations.
	 *
	 * @return int
	 */
	public function create_form( $form = array(), $fields = array() ) {
		$form    = array_merge(
			$form,
			array(
				'title'       => 'Test form',
				'description' => 'Form for Field Tests Description',
				'fields' => $this->create_fields( $fields )
			)
		);
		$form_id = \GFAPI::add_form( $form );

		return $form_id;
	}

	/**
	 * Returns form field object.
	 *
	 * @param array $fields  Form field configurations.
	 *
	 * @return array
	 */
	public function create_fields( $fields = array() ) {
		$fields = ! empty( $fields ) ? $fields : array(
			array(
				'type'                 => 'text',
				'id'                   => 1,
				'label'                => 'Single Line Text',
				'adminLabel'           => '',
				'isRequired'           => false,
				'size'                 => 'medium',
				'errorMessage'         => '',
				'visibility'           => 'visible',
				'inputs'               => null,
				'formId'               => 2,
				'description'          => 'I am a single line text field.',
				'allowsPrepopulate'    => false,
				'inputMask'            => false,
				'inputMaskValue'       => '',
				'inputMaskIsCustom'    => false,
				'maxLength'            => '',
				'inputType'            => '',
				'labelPlacement'       => '',
				'descriptionPlacement' => '',
				'subLabelPlacement'    => '',
				'placeholder'          => '',
				'cssClass'             => '',
				'inputName'            => '',
				'noDuplicates'         => false,
				'defaultValue'         => '',
				'choices'              => '',
				'productField'         => '',
				'enablePasswordInput'  => '',
				'multipleFiles'        => false,
				'maxFiles'             => '',
				'calculationFormula'   => '',
				'calculationRounding'  => '',
				'enableCalculation'    => '',
				'disableQuantity'      => false,
				'displayAllCategories' => false,
				'useRichTextEditor'    => false,
				'checkboxLabel'        => '',
				'pageNumber'           => 1,
				'fields'               => '',
				'displayOnly'          => '',
			),
		);

		return $fields;
	}

	public function test_callback_gform_after_save_form() {
		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '"%1$s" form %2$s', 'stream' ) ),
				$this->equalTo(
					array(
						'title'  => 'Test form',
						'action' => 'created',
					)
				),
				$this->greaterThan( 0 ),
				$this->equalTo( 'forms' ),
				$this->equalTo( 'created' )
			);

		// Create form to trigger save callback.
		$form_id = $this->create_form();

		$form_meta = \GFFormsModel::get_form_meta( $form_id );
		do_action( 'gform_after_save_form', $form_meta, true, array() );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_after_save_form' ) );
	}

	public function test_callback_gform_pre_confirmation_save() {
		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '"%1$s" confirmation %2$s for "%3$s"', 'stream' ) ),
				$this->equalTo(
					array(
						'title'      => 'Test confirmation',
						'action'     => 'created',
						'form_title' => 'Test form',
					)
				),
				$this->greaterThan( 0 ),
				$this->equalTo( 'forms' ),
				$this->equalTo( 'updated' )
			);

		// Create form and confirmation to trigger callback.
		$confirmation = array(
			'id'          => uniqid(),
			'name'        => 'Test confirmation',
			'isDefault'   => true,
			'type'        => 'message',
			'message'     => 'Lorem ipsum dolor...',
			'url'         => '',
			'pageId'      => '',
			'queryString' => '',
		);

		$form_id = $this->create_form(
			array( 'confirmations' => array( $confirmation['id'] => $confirmation ) )
		);
		$form    = \GFAPI::get_form( $form_id );

		\gf_apply_filters( array( 'gform_pre_confirmation_save', $form_id ), $confirmation, $form, true );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_pre_confirmation_save' ) );
	}

	public function test_callback_gform_pre_notification_save() {
		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '"%1$s" notification %2$s for "%3$s"', 'stream' ) ),
				$this->equalTo(
					array(
						'title'      => 'Test notification',
						'action'     => 'created',
						'form_title' => 'Test form',
					)
				),
				$this->greaterThan( 0 ),
				$this->equalTo( 'forms' ),
				$this->equalTo( 'updated' )
			);

		// Create form and notification to trigger callback.
		$notification = array(
			'id'      => uniqid(),
			'to'      => '{admin_email}',
			'name'    => 'Test notification',
            'event'   => 'form_submission',
            'toType'  => 'email',
            'subject' => 'New submission from {form_title}',
            'message' => '{all_fields}',
		);

		$form_id = $this->create_form(
			array( 'notifications' => array( $notification['id'] => $notification ) )
		);
		$form    = \GFAPI::get_form( $form_id );

		\gf_apply_filters( array( 'gform_pre_notification_save', $form_id ), $notification, $form, true );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_pre_notification_save' ) );
	}

	public function test_callback_gform_pre_notification_deleted() {
		// Create form and notification for later use.
		$notification = array(
			'id'      => uniqid(),
			'to'      => '{admin_email}',
			'name'    => 'Test notification',
			'event'   => 'form_submission',
			'toType'  => 'email',
			'subject' => 'New submission from {form_title}',
			'message' => '{all_fields}',
		);

		$form_id      = $this->create_form(
			array( 'notifications' => array( $notification['id'] => $notification ) )
		);
		$form         = \GFAPI::get_form( $form_id );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '"%1$s" notification deleted from "%2$s"', 'stream' ) ),
				$this->equalTo(
					array(
						'title'      => 'Test notification',
						'form_title' => 'Test form',
					)
				),
				$this->greaterThan( 0 ),
				$this->equalTo( 'forms' ),
				$this->equalTo( 'updated' )
			);

		// Delete notification to trigger callback.
		\GFNotification::delete_notification( $notification['id'], $form_id );

		// Check callback test action..
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_pre_notification_deleted' ) );
	}

	public function test_callback_gform_pre_confirmation_deleted() {
		// Create form and notification for later use.
		$confirmation = array(
			'id'          => uniqid(),
			'name'        => 'Test confirmation',
			'isDefault'   => true,
			'type'        => 'message',
			'message'     => 'Lorem ipsum dolor...',
			'url'         => '',
			'pageId'      => '',
			'queryString' => '',
		);

		$form_id      = $this->create_form(
			array( 'confirmations' => array( $confirmation['id'] => $confirmation ) )
		);
		$form         = \GFAPI::get_form( $form_id );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '"%1$s" confirmation deleted from "%2$s"', 'stream' ) ),
				$this->equalTo(
					array(
						'title'      => 'Test confirmation',
						'form_title' => 'Test form',
					)
				),
				$this->greaterThan( 0 ),
				$this->equalTo( 'forms' ),
				$this->equalTo( 'updated' )
			);

		// Delete confirmation to trigger callback.
		\GFFormSettings::delete_confirmation( $confirmation['id'], $form_id );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_pre_confirmation_deleted' ) );
	}

	public function test_check() {
		// Expected log calls.
		$this->mock->expects( $this->exactly( 4 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( __( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => 'Currency',
							'option'       => 'rg_gforms_currency',
							'old_value'    => null,
							'new_value'    => '$',
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' ),
				),
				array(
					$this->equalTo( 'Gravity Forms license key updated' ),
					$this->equalTo(
						array(
							'option'    => 'rg_gforms_key',
							'old_value' => null,
							'new_value' => 'blahblahblah'
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' ),
				),
				array(
					$this->equalTo( __( '"%s" setting updated', 'stream' ) ),
					$this->equalTo(
						array(
							'option_title' => 'Currency',
							'option'       => 'rg_gforms_currency',
							'old_value'    => '$',
							'new_value'    => '£',
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'updated' ),
				),
				array(
					$this->equalTo( 'Gravity Forms license key deleted' ),
					$this->equalTo(
						array(
							'option'    => 'rg_gforms_key',
							'old_value' => 'blahblahblah',
							'new_value' => ''
						)
					),
					$this->equalTo( null ),
					$this->equalTo( 'settings' ),
					$this->equalTo( 'deleted' ),
				)
			);

		// Update options to trigger callbacks.
		update_option( 'rg_gforms_currency', '$' );
		update_option( 'rg_gforms_key', 'blahblahblah' );
		update_option( 'rg_gforms_currency', '£' );
		update_option( 'rg_gforms_key', '' );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_add_option' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_update_option' ) );
	}

	public function test_callback_gform_delete_lead() {
		// Create form and entry for later use.
		$form_id  = $this->create_form();
		$form     = \GFAPI::get_form( $form_id );
		$entry_id = \GFAPI::add_entry(
			array(
				'id'               => '1',
				'form_id'          => $form_id,
				'date_created'     => '2016-03-22 19 => 13 => 19',
				'is_starred'       => 0,
				'is_read'          => 0,
				'ip'               => '192.168.50.1',
				'source_url'       => 'http => \/\/local.wordpress.dev\/?gf_page=preview&id=1',
				'post_id'          => null,
				'currency'         => 'USD',
				'payment_status'   => null,
				'payment_date'     => null,
				'transaction_id'   => null,
				'payment_amount'   => null,
				'payment_method'   => null,
				'is_fulfilled'     => null,
				'created_by'       => '1',
				'transaction_type' => null,
				'user_agent'       => 'Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/48.0.2564.116 Safari\/537.36',
				'status'           => 'active',
			)
		);

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( 'Lead #%1$d from "%2$s" deleted', 'stream' ) ),
				$this->equalTo(
					array(
						'lead_id'    => $entry_id,
						'form_title' => 'Test form',
						'form_id'    => $form_id,
					)
				),
				$this->equalTo( $entry_id ),
				$this->equalTo( 'entries' ),
				$this->equalTo( 'deleted' )
			);

		// Delete entry to trigger callback.
		\GFFormsModel::delete_entry( $entry_id );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_delete_lead' ) );
	}

	public function test_callback_gform_post_note_added() {
		// Create and authenticate user.
		$user_id = self::factory()->user->create(
			array(
				'username'  => 'johndoe',
				'user_role' => 'admin',
			)
		);
		\wp_set_current_user( $user_id );

		// Create form and entry for later use.
		$form_id  = $this->create_form();
		$form     = \GFAPI::get_form( $form_id );
		$entry_id = \GFAPI::add_entry(
			array(
				'id'               => '1',
				'form_id'          => $form_id,
				'date_created'     => '2016-03-22 19 => 13 => 19',
				'is_starred'       => 0,
				'is_read'          => 0,
				'ip'               => '192.168.50.1',
				'source_url'       => 'http => \/\/local.wordpress.dev\/?gf_page=preview&id=1',
				'post_id'          => null,
				'currency'         => 'USD',
				'payment_status'   => null,
				'payment_date'     => null,
				'transaction_id'   => null,
				'payment_amount'   => null,
				'payment_method'   => null,
				'is_fulfilled'     => null,
				'created_by'       => '1',
				'transaction_type' => null,
				'user_agent'       => 'Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/48.0.2564.116 Safari\/537.36',
				'status'           => 'active',
			)
		);

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( 'Note #%1$d added to lead #%2$d on "%3$s" form', 'stream' ) ),
				$this->callback(
					function( $args ) {
						return ! empty( $args['form_title' ] ) && $args['form_title'] === 'Test form';
					}
				),
				$this->greaterThan( 0 ),
				$this->equalTo( 'notes' ),
				$this->equalTo( 'added' )
			);

		// Create note to trigger callback.
		$note    = 'Lorem ipsum dolor...';
		\GFFormsModel::add_note( $entry_id, $user_id, 'johndoe', $note, 'user' );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_post_note_added' ) );
	}

	public function test_callback_gform_pre_note_deleted() {
		global $wpdb;

		// Create and authenticate user.
		$user_id = self::factory()->user->create(
			array(
				'username'  => 'johndoe',
				'user_role' => 'admin',
			)
		);
		\wp_set_current_user( $user_id );

		// Create form, entry, and note for later use.
		$form_id  = $this->create_form();
		$form     = \GFAPI::get_form( $form_id );
		$entry_id = \GFAPI::add_entry(
			array(
				'id'               => '1',
				'form_id'          => $form_id,
				'date_created'     => '2016-03-22 19 => 13 => 19',
				'is_starred'       => 0,
				'is_read'          => 0,
				'ip'               => '192.168.50.1',
				'source_url'       => 'http => \/\/local.wordpress.dev\/?gf_page=preview&id=1',
				'post_id'          => null,
				'currency'         => 'USD',
				'payment_status'   => null,
				'payment_date'     => null,
				'transaction_id'   => null,
				'payment_amount'   => null,
				'payment_method'   => null,
				'is_fulfilled'     => null,
				'created_by'       => '1',
				'transaction_type' => null,
				'user_agent'       => 'Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/48.0.2564.116 Safari\/537.36',
				'status'           => 'active',
			)
		);

		// Wow, I actually had to do this just to get the Note ID.
		$note_id = null;
		add_action(
			'gform_post_note_added',
			function( $id ) use ( &$note_id ) {
				$note_id = $id;
			}
		);
		\GFFormsModel::add_note( $entry_id, $user_id, 'johndoe', 'Lorem ipsum dolor...' );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( 'Note #%1$d deleted from lead #%2$d on "%3$s" form', 'stream' ) ),
				$this->equalTo(
					array(
						'note_id'    => $note_id,
						'lead_id'    => $entry_id,
						'form_title' => $form['title'],
						'form_id'    => $form['id'],
					)
				),
				$this->equalTo( $note_id ),
				$this->equalTo( 'notes' ),
				$this->equalTo( 'deleted' )
			);

		// Delete note and trigger callback.
		\GFFormsModel::delete_note( $note_id );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_pre_note_deleted' ) );
	}

	public function test_callback_gform_update_status() {
		// Create form and entry for later use.
		$form_id  = $this->create_form();
		$form     = \GFAPI::get_form( $form_id );
		$entry_id = \GFAPI::add_entry(
			array(
				'id'               => '1',
				'form_id'          => $form_id,
				'date_created'     => '2016-03-22 19 => 13 => 19',
				'is_starred'       => 0,
				'is_read'          => 0,
				'ip'               => '192.168.50.1',
				'source_url'       => 'http => \/\/local.wordpress.dev\/?gf_page=preview&id=1',
				'post_id'          => null,
				'currency'         => 'USD',
				'payment_status'   => null,
				'payment_date'     => null,
				'transaction_id'   => null,
				'payment_amount'   => null,
				'payment_method'   => null,
				'is_fulfilled'     => null,
				'created_by'       => '1',
				'transaction_type' => null,
				'user_agent'       => 'Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/48.0.2564.116 Safari\/537.36',
				'status'           => 'active',
			)
		);

		// Expected log calls.
		$this->mock->expects( $this->exactly( 2 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( __( 'Lead #%1$d %2$s on "%3$s" form', 'stream' ) ),
					$this->equalTo(
						array(
							'lead_id'    => $entry_id,
							'action'     => 'trashed',
							'form_title' => 'Test form',
							'status'     => 'trash',
							'prev'       => 'active',
						)
					),
					$this->equalTo( $entry_id ),
					$this->equalTo( 'entries' ),
					$this->equalTo( 'trash' ),
				),
				array(
					$this->equalTo( __( 'Lead #%1$d %2$s on "%3$s" form', 'stream' ) ),
					$this->equalTo(
						array(
							'lead_id'    => $entry_id,
							'action'     => 'restored',
							'form_title' => 'Test form',
							'status'     => 'restore',
							'prev'       => 'trash',
						)
					),
					$this->equalTo( $entry_id ),
					$this->equalTo( 'entries' ),
					$this->equalTo( 'restore' )
				)
			);

		// Update form status and manually trigger callback.
		\gform_update_meta( $entry_id, 'status', 'trash' );
		do_action( 'gform_update_status', $entry_id, 'trash', 'active' );
		\gform_update_meta( $entry_id, 'status', 'active' );
		do_action( 'gform_update_status', $entry_id, 'active', 'trash' );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_update_status' ) );
	}

	public function test_callback_gform_update_is_read() {
		// Create form for later use.
		$form_id  = $this->create_form();
		$form     = \GFAPI::get_form( $form_id );
		$entry_id = \GFAPI::add_entry(
			array(
				'id'               => '1',
				'form_id'          => $form_id,
				'date_created'     => '2016-03-22 19 => 13 => 19',
				'is_starred'       => 0,
				'is_read'          => 0,
				'ip'               => '192.168.50.1',
				'source_url'       => 'http => \/\/local.wordpress.dev\/?gf_page=preview&id=1',
				'post_id'          => null,
				'currency'         => 'USD',
				'payment_status'   => null,
				'payment_date'     => null,
				'transaction_id'   => null,
				'payment_amount'   => null,
				'payment_method'   => null,
				'is_fulfilled'     => null,
				'created_by'       => '1',
				'transaction_type' => null,
				'user_agent'       => 'Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/48.0.2564.116 Safari\/537.36',
				'status'           => 'active',
			)
		);

		// Expected log calls.
		$this->mock->expects( $this->exactly( 2 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( __( 'Entry #%1$d marked as %2$s on form #%3$d ("%4$s")', 'stream' ) ),
					$this->equalTo(
						array(
							'lead_id'     => $entry_id,
							'lead_status' => 'read',
							'form_id'     => $form['id'],
							'form_title'  => $form['title'],
						)
					),
					$this->equalTo( $entry_id ),
					$this->equalTo( 'entries' ),
					$this->equalTo( 'updated' )
				),
				array(
					$this->equalTo( __( 'Entry #%1$d marked as %2$s on form #%3$d ("%4$s")', 'stream' ) ),
					$this->equalTo(
						array(
							'lead_id'     => $entry_id,
							'lead_status' => 'unread',
							'form_id'     => $form['id'],
							'form_title'  => $form['title'],
						)
					),
					$this->equalTo( $entry_id ),
					$this->equalTo( 'entries' ),
					$this->equalTo( 'updated' )
				)
			);

		// Update entry "is_read" and trigger callback.
		\GFFormsModel::update_entry_property( $entry_id, 'is_read', 1 );
		\GFFormsModel::update_entry_property( $entry_id, 'is_read', 0 );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_update_is_read' ) );
	}

	public function test_callback_gform_update_is_starred() {
		// Create form and entry for later use.
		$form_id  = $this->create_form();
		$form     = \GFAPI::get_form( $form_id );
		$entry_id = \GFAPI::add_entry(
			array(
				'id'               => '1',
				'form_id'          => $form_id,
				'date_created'     => '2016-03-22 19 => 13 => 19',
				'is_starred'       => 0,
				'is_read'          => 0,
				'ip'               => '192.168.50.1',
				'source_url'       => 'http => \/\/local.wordpress.dev\/?gf_page=preview&id=1',
				'post_id'          => null,
				'currency'         => 'USD',
				'payment_status'   => null,
				'payment_date'     => null,
				'transaction_id'   => null,
				'payment_amount'   => null,
				'payment_method'   => null,
				'is_fulfilled'     => null,
				'created_by'       => '1',
				'transaction_type' => null,
				'user_agent'       => 'Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/48.0.2564.116 Safari\/537.36',
				'status'           => 'active',
			)
		);

		// Expected log calls.
		$this->mock->expects( $this->exactly( 2 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( __( 'Entry #%1$d %2$s on form #%3$d ("%4$s")', 'stream' ) ),
					$this->equalTo(
						array(
							'lead_id'     => $entry_id,
							'lead_status' => 'starred',
							'form_id'     => $form['id'],
							'form_title'  => $form['title'],
						)
					),
					$this->equalTo( $entry_id ),
					$this->equalTo( 'entries' ),
					$this->equalTo( 'starred' )
				),
				array(
					$this->equalTo( __( 'Entry #%1$d %2$s on form #%3$d ("%4$s")', 'stream' ) ),
					$this->equalTo(
						array(
							'lead_id'     => $entry_id,
							'lead_status' => 'unstarred',
							'form_id'     => $form['id'],
							'form_title'  => $form['title'],
						)
					),
					$this->equalTo( $entry_id ),
					$this->equalTo( 'entries' ),
					$this->equalTo( 'unstarred' )
				)
			);

		// Update entry "is_starred" and trigger callback.
		\GFFormsModel::update_entry_property( $entry_id, 'is_starred', 1 );
		\GFFormsModel::update_entry_property( $entry_id, 'is_starred', 0 );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_update_is_starred' ) );
	}

	public function test_log_form_action() {
		// Create form and entry for later use.
		$form_id  = $this->create_form();
		$form     = \GFAPI::get_form( $form_id );
		$entry_id = \GFAPI::add_entry(
			array(
				'id'               => '1',
				'form_id'          => $form_id,
				'date_created'     => '2016-03-22 19 => 13 => 19',
				'is_starred'       => 0,
				'is_read'          => 0,
				'ip'               => '192.168.50.1',
				'source_url'       => 'http => \/\/local.wordpress.dev\/?gf_page=preview&id=1',
				'post_id'          => null,
				'currency'         => 'USD',
				'payment_status'   => null,
				'payment_date'     => null,
				'transaction_id'   => null,
				'payment_amount'   => null,
				'payment_method'   => null,
				'is_fulfilled'     => null,
				'created_by'       => '1',
				'transaction_type' => null,
				'user_agent'       => 'Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/48.0.2564.116 Safari\/537.36',
				'status'           => 'active',
			)
		);

		// Expected log calls.
		$this->mock->expects( $this->exactly( 10 ) )
			->method( 'log' )
			->withConsecutive(
				array(
					$this->equalTo( __( 'Form #%1$d ("%2$s") %3$s', 'stream' ) ),
					$this->equalTo(
						array(
							'form_id'     => $form_id,
							'form_title'  => 'Test form',
							'form_status' => 'deactivated',
						)
					),
					$this->equalTo( $form_id ),
					$this->equalTo( 'forms' ),
					$this->equalTo( 'deactivated' )
				),
				array(
					$this->equalTo( __( 'Form #%1$d ("%2$s") %3$s', 'stream' ) ),
					$this->equalTo(
						array(
							'form_id'     => $form_id,
							'form_title'  => 'Test form',
							'form_status' => 'activated',
						)
					),
					$this->equalTo( $form_id ),
					$this->equalTo( 'forms' ),
					$this->equalTo( 'activated' )
				),
				array(
					$this->equalTo( __( 'Form #%1$d ("%2$s") %3$s', 'stream' ) ),
					$this->equalTo(
						array(
							'form_id'     => $form_id,
							'form_title'  => 'Test form',
							'form_status' => 'trashed',
						)
					),
					$this->equalTo( $form_id ),
					$this->equalTo( 'forms' ),
					$this->equalTo( 'trashed' )
				),
				array(
					$this->equalTo( __( 'Form #%1$d ("%2$s") %3$s', 'stream' ) ),
					$this->equalTo(
						array(
							'form_id'     => $form_id,
							'form_title'  => 'Test form',
							'form_status' => 'restored',
						)
					),
					$this->equalTo( $form_id ),
					$this->equalTo( 'forms' ),
					$this->equalTo( 'untrashed' )
				),
				array(
					$this->equalTo( __( 'Form #%1$d ("%2$s") %3$s', 'stream' ) ),
					$this->equalTo(
						array(
							'form_id'     => $form_id + 1,
							'form_title'  => 'Test form (1)',
							'form_status' => 'activated',
						)
					),
					$this->equalTo( $form_id + 1 ),
					$this->equalTo( 'forms' ),
					$this->equalTo( 'activated' )
				),
				array(
					$this->equalTo( __( 'Form #%1$d ("%2$s") %3$s', 'stream' ) ),
					$this->equalTo(
						array(
							'form_id'     => $form_id,
							'form_title'  => 'Test form',
							'form_status' => 'duplicated',
						)
					),
					$this->equalTo( $form_id ),
					$this->equalTo( 'forms' ),
					$this->equalTo( 'duplicated' )
				),
				array(
					$this->equalTo( __( 'Form #%1$d ("%2$s") %3$s', 'stream' ) ),
					$this->equalTo(
						array(
							'form_id'     => $form_id,
							'form_title'  => 'Test form',
							'form_status' => 'views reset',
						)
					),
					$this->equalTo( $form_id ),
					$this->equalTo( 'forms' ),
					$this->equalTo( 'views_deleted' )
				),
				array(
					$this->equalTo( __( 'Form #%1$d ("%2$s") %3$s', 'stream' ) ),
					$this->equalTo(
						array(
							'form_id'     => $form_id,
							'form_title'  => 'Test form',
							'form_status' => 'deleted',
						)
					),
					$this->equalTo( $form_id ),
					$this->equalTo( 'forms' ),
					$this->equalTo( 'deleted' )
				),
				array(
					$this->equalTo( __( 'Lead #%1$d from "%2$s" deleted', 'stream' ) ),
					$this->equalTo(
						array(
							'lead_id'    => $entry_id,
							'form_title' => 'Test form',
							'form_id'    => $form_id,
						)
					),
					$this->equalTo( $entry_id ),
					$this->equalTo( 'entries' ),
					$this->equalTo( 'deleted' )
				),
				array(
					$this->equalTo( __( 'Form #%1$d ("%2$s") %3$s', 'stream' ) ),
					$this->equalTo(
						array(
							'form_id'     => $form_id,
							'form_title'  => 'Test form',
							'form_status' => 'views reset',
						)
					),
					$this->equalTo( $form_id ),
					$this->equalTo( 'forms' ),
					$this->equalTo( 'views_deleted' )
				)
			);

		// Update form "status" to trigger callback.
		\GFFormsModel::update_form_active( $form_id, 0 );
		\GFFormsModel::update_form_active( $form_id, 1 );
		\RGFormsModel::trash_form( $form_id );
		\RGFormsModel::restore_form( $form_id );
		\RGFormsModel::duplicate_form( $form_id );
		\GFFormsModel::delete_views( $form_id );
		\RGFormsModel::delete_form( $form_id );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_before_delete_form' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_post_form_trashed' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_post_form_restored' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_post_form_activated' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_post_form_deactivated' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_post_form_duplicated' ) );
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_post_form_views_deleted' ) );
	}

	public function test_callback_gform_post_export_entries() {
		// Create form for later use.
		$form_id  = $this->create_form();

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '"%s" form entries exported', 'stream' ) ),
				$this->equalTo(
					array(
						'form_title' => 'Test form',
						'form_id'    => $form_id,
						'start_date' => null,
						'end_date'   => null,
					)
				),
				$this->equalTo( $form_id ),
				$this->equalTo( 'export' ),
				$this->equalTo( 'exported' )
			);

		// Execute form export and trigger callback.
		$form      = \RGFormsModel::get_form_meta( $form_id );
		$export_id = sanitize_key( wp_hash( uniqid( 'export', true ) ) );
		$_POST['export_field'] = array( 'id' );
		\GFExport::start_export( $form, 0, $export_id );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_post_export_entries' ) );
	}

	public function test_callback_gform_forms_post_import() {
		// Create form and export as json string for later use.
		$form_id          = $this->create_form();
		$forms            = \GFFormsModel::get_form_meta_by_id( [ $form_id ] );
		$forms            = \GFExport::prepare_forms_for_export( $forms );
		$forms['version'] = \GFForms::$version;
		$forms_json       = json_encode( $forms );

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( '%d form imported' ),
				$this->equalTo(
					array(
						'count'  => 1,
						'ids'    => [ $form_id + 1 ],
						'titles' => [ 'Test form(2)' ],
					)
				),
				$this->equalTo( null ),
				$this->equalTo( 'export' ),
				$this->equalTo( 'imported' )
			);

		// Import form and trigger callback.
		\GFExport::import_json( $forms_json );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_forms_post_import' ) );
	}

	public function test_callback_gform_export_form() {
		// Create form for later use.
		$form_id  = $this->create_form();

		// Expected log calls.
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->equalTo( __( '"%s" form exported', 'stream' ) ),
				$this->equalTo(
					array(
						'form_title' => 'Test form',
						'form_id'    => $form_id,
					)
				),
				$this->equalTo( $form_id ),
				$this->equalTo( 'export' ),
				$this->equalTo( 'exported' )
			);

		// Export forms to trigger callback.
		$forms = \GFFormsModel::get_form_meta_by_id( array( $form_id ) );
		\GFExport::prepare_forms_for_export( $forms );

		// Check callback test action.
		$this->assertFalse( 0 === did_action( 'wp_stream_test_callback_gform_export_form' ) );
	}
}
