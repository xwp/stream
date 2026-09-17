<?php
// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Anonymous-class DTO doubles intentionally use camelCase to mirror the WP AI Client (PSR-style) interface.
/**
 * WP Integration Test: AI Client Connector
 *
 * Tests for Connector_AI_Client callbacks, settings, and Stream record routing.
 * Uses anonymous-class DTO doubles so tests run independently of WP 7.0+ core
 * (i.e., the real wordpress/wp-includes/php-ai-client/ is not required).
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Test_WP_Stream_Connector_AI_Client
 */
class Test_WP_Stream_Connector_AI_Client extends WP_StreamTestCase {

	/**
	 * Message captured from the most recent Connector::log() call.
	 *
	 * @var string|null
	 */
	private static $captured_log_message;

	/**
	 * Args captured from the most recent Connector::log() call in real-options tests.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $captured_log_args;

	/**
	 * Stores log() args for later assertions. Named callable so tests do not
	 * register closures with PHPUnit.
	 *
	 * @param string               $message Log message template.
	 * @param array<string, mixed> $args    Log meta args.
	 * @return void
	 */
	public static function capture_log_args( $message, $args ) {
		unset( $message );
		self::$captured_log_args = $args;
	}

	/**
	 * Stores log() message and args for summary-template assertions.
	 *
	 * @param string               $message Log message template.
	 * @param array<string, mixed> $args    Log meta args.
	 * @return void
	 */
	public static function capture_log_call( $message, $args ) {
		self::$captured_log_message = $message;
		self::$captured_log_args    = $args;
	}

	/**
	 * Enables prompt and response text logging when used as an is_prompt_and_response_logging_enabled stub.
	 *
	 * @return bool
	 */
	public static function enable_prompt_and_response_logging() {
		return true;
	}

	/**
	 * Replaces logged AI text so filter tests can assert the hook ran.
	 *
	 * @param string $text  Original text.
	 * @param object $event AI Client event instance.
	 * @return string
	 */
	public static function prefix_redacted_log_text( $text, $event ) {
		unset( $event );
		return 'REDACTED:' . $text;
	}

	/**
	 * Set up a mocked connector instance before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->plugin->connectors->unload_connectors();

		// Partial mock: override log() so we can assert calls without DB writes.
		$this->mock = $this->getMockBuilder( Connector_AI_Client::class )
			->onlyMethods( array( 'log', 'is_prompt_and_response_logging_enabled' ) )
			->getMock();

		// NOTE: Do NOT stub is_prompt_and_response_logging_enabled() here. PHPUnit keeps the first
		// stub configured for a method with no argument matcher, so a per-test
		// willReturnCallback() added later would be silently ignored. Each test
		// owns its own option state instead (disabled tests stub false explicitly;
		// un-stubbed returns null which is falsy, keeping text gated off by default).
		$this->mock->register();
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Registration is gated on WP_AI_Client_Event_Dispatcher, not the SDK class.
	 *
	 * Asserts the absent branch first when the class is missing, then loads the
	 * stub so the present branch is covered on WP 6.x CI. The require_once leaks
	 * the stub into the rest of this PHPUnit process; no other test asserts the
	 * connector is absent.
	 */
	public function test_connector_registration_is_gated_on_event_dispatcher() {
		$connector = new Connector_AI_Client();

		if ( ! class_exists( 'WP_AI_Client_Event_Dispatcher' ) ) {
			$this->assertFalse( $connector->is_dependency_satisfied() );

			$this->plugin->connectors->unload_connectors();
			$this->plugin->connectors->load_connectors();
			$this->assertArrayNotHasKey( 'ai-client', $this->plugin->connectors->connectors );

			require_once __DIR__ . '/stubs/class-wp-ai-client-event-dispatcher.php';
		}

		$this->assertTrue( $connector->is_dependency_satisfied() );

		$this->plugin->connectors->unload_connectors();
		$this->plugin->connectors->load_connectors();
		$this->assertArrayHasKey( 'ai-client', $this->plugin->connectors->connectors );
	}

	/**
	 * Connector slug, label, context, and action are declared correctly.
	 */
	public function test_connector_metadata() {
		$connector = new Connector_AI_Client();

		$this->assertSame( 'ai-client', $connector->name );
		$this->assertNotEmpty( $connector->get_label() );
		$this->assertArrayHasKey( 'prompts', $connector->get_context_labels() );
		$this->assertArrayHasKey( 'generated', $connector->get_action_labels() );
	}

	/**
	 * Action hooks are attached after register().
	 */
	public function test_action_hooks_are_registered() {
		$this->assertNotFalse(
			has_action( 'wp_ai_client_before_generate_result', array( $this->mock, 'callback' ) ),
			'Before hook should be registered'
		);
		$this->assertNotFalse(
			has_action( 'wp_ai_client_after_generate_result', array( $this->mock, 'callback' ) ),
			'After hook should be registered'
		);
	}

	// -------------------------------------------------------------------------
	// Core logging
	// -------------------------------------------------------------------------

	/**
	 * One log() call per before/after pair with correct context and action.
	 */
	public function test_log_is_called_once_per_generation() {
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->with(
				$this->anything(),
				$this->anything(),
				null,
				'prompts',
				'generated'
			);

		$pair = $this->make_event_pair();
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );
	}

	/**
	 * log() args contain provider, model, operation, token counts, duration.
	 */
	public function test_log_args_contain_core_fields() {
		$captured_args = null;

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args ) use ( &$captured_args ) {
					$captured_args = $args;
				}
			);

		$pair = $this->make_event_pair(
			array(
				'provider'  => 'openai',
				'model'     => 'gpt-4o',
				'operation' => 'text_generation',
			)
		);
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		$this->assertIsArray( $captured_args );
		$this->assertSame( 'text_generation', $captured_args['operation'] );
		$this->assertSame( 'openai', $captured_args['provider'] );
		$this->assertSame( 'gpt-4o', $captured_args['model'] );
		$this->assertIsInt( $captured_args['input_tokens'] );
		$this->assertIsInt( $captured_args['output_tokens'] );
		$this->assertIsInt( $captured_args['thought_tokens'] );
		$this->assertIsInt( $captured_args['duration_ms'] );
		$this->assertSame( 'stop', $captured_args['finish_reason'] );
	}

	/**
	 * After-hook without a matching before-hook does not log anything.
	 */
	public function test_after_without_before_does_not_log() {
		$this->mock->expects( $this->never() )->method( 'log' );

		$pair = $this->make_event_pair();
		// Fire only the after hook — no before.
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );
	}

	/**
	 * Two concurrent generations on different model instances are correlated correctly.
	 */
	public function test_two_concurrent_generations_are_correlated_independently() {
		$log_count = 0;
		$this->mock->method( 'log' )
			->willReturnCallback(
				function () use ( &$log_count ) {
					$log_count++;
				}
			);
		$this->mock->expects( $this->exactly( 2 ) )->method( 'log' );

		$pair_a = $this->make_event_pair( array( 'model' => 'gpt-4o' ) );
		$pair_b = $this->make_event_pair( array( 'model' => 'claude-3' ) );

		do_action( 'wp_ai_client_before_generate_result', $pair_a['before'] );
		do_action( 'wp_ai_client_before_generate_result', $pair_b['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair_a['after'] );
		do_action( 'wp_ai_client_after_generate_result', $pair_b['after'] );
	}

	/**
	 * An unmatched before-hook must not attach its prompt to a later generation
	 * on a different model instance (the old spl_object_id reuse failure mode).
	 */
	public function test_orphaned_before_does_not_attach_to_later_generation() {
		$this->mock->method( 'is_prompt_and_response_logging_enabled' )
			->willReturnCallback( array( self::class, 'enable_prompt_and_response_logging' ) );

		self::$captured_log_args = null;
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback( array( self::class, 'capture_log_args' ) );

		$pair_a = $this->make_event_pair(
			array(
				'model'        => 'model-a',
				'user_message' => 'Prompt A',
			)
		);
		do_action( 'wp_ai_client_before_generate_result', $pair_a['before'] );
		unset( $pair_a );

		$pair_b = $this->make_event_pair(
			array(
				'model'        => 'model-b',
				'user_message' => 'Prompt B',
			)
		);
		do_action( 'wp_ai_client_before_generate_result', $pair_b['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair_b['after'] );

		$this->assertStringContainsString( 'Prompt B', self::$captured_log_args['prompt_text'] );
		$this->assertStringNotContainsString( 'Prompt A', self::$captured_log_args['prompt_text'] );
	}

	// -------------------------------------------------------------------------
	// Text logging gating (prompt + response)
	// -------------------------------------------------------------------------

	/**
	 * prompt_text and response_text are empty when both options are off (default).
	 */
	public function test_text_fields_empty_when_options_disabled() {
		$captured_args = null;

		$this->mock->method( 'is_prompt_and_response_logging_enabled' )->willReturn( false );
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args ) use ( &$captured_args ) {
					$captured_args = $args;
				}
			);

		$pair = $this->make_event_pair( array( 'user_message' => 'Hello world' ) );
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		$this->assertSame( '', $captured_args['prompt_text'] );
		$this->assertSame( '', $captured_args['response_text'] );
	}

	/**
	 * prompt_text and response_text are populated when log_prompt_and_response_text is enabled.
	 */
	public function test_prompt_text_captured_when_option_enabled() {
		$captured_args = null;

		$this->mock->method( 'is_prompt_and_response_logging_enabled' )
			->willReturnCallback( array( self::class, 'enable_prompt_and_response_logging' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args ) use ( &$captured_args ) {
					$captured_args = $args;
				}
			);

		$pair = $this->make_event_pair(
			array(
				'user_message'  => 'What is the capital of France?',
				'response_text' => 'The capital of France is Paris.',
			)
		);
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		$this->assertStringContainsString( 'What is the capital of France?', $captured_args['prompt_text'] );
		$this->assertStringContainsString( 'The capital of France is Paris.', $captured_args['response_text'] );
	}

	/**
	 * response_text is populated when log_prompt_and_response_text is enabled.
	 */
	public function test_response_text_captured_when_option_enabled() {
		$captured_args = null;

		$this->mock->method( 'is_prompt_and_response_logging_enabled' )
			->willReturnCallback( array( self::class, 'enable_prompt_and_response_logging' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args ) use ( &$captured_args ) {
					$captured_args = $args;
				}
			);

		$pair = $this->make_event_pair(
			array(
				'user_message'  => 'Hidden prompt',
				'response_text' => 'The capital of France is Paris.',
			)
		);
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		$this->assertStringContainsString( 'Hidden prompt', $captured_args['prompt_text'] );
		$this->assertStringContainsString( 'The capital of France is Paris.', $captured_args['response_text'] );
	}

	/**
	 * Default-off: the real settings accessor leaves prompt/response empty
	 * when ai-client_log_prompt_and_response_text is absent or zero. Does not stub
	 * is_prompt_and_response_logging_enabled(), so a wrong option key would fail this test.
	 */
	public function test_text_fields_empty_when_real_settings_option_disabled() {
		$original_options = $this->plugin->settings->options;

		$this->plugin->settings->options['ai-client_log_prompt_and_response_text'] = 0;

		try {
			$this->register_settings_backed_connector();
			$args = $this->fire_generation_and_get_log_args(
				array(
					'user_message'  => 'Secret prompt',
					'response_text' => 'Secret response',
				)
			);

			$this->assertSame( '', $args['prompt_text'] );
			$this->assertSame( '', $args['response_text'] );
		} finally {
			$this->plugin->settings->options = $original_options;
		}
	}

	/**
	 * Enabling the real Stream setting populates prompt_text and response_text.
	 */
	public function test_prompt_and_response_text_follows_real_settings_option() {
		$original_options = $this->plugin->settings->options;

		try {
			$this->plugin->settings->options['ai-client_log_prompt_and_response_text'] = 1;
			$this->register_settings_backed_connector();
			$args = $this->fire_generation_and_get_log_args(
				array(
					'user_message'  => 'Visible prompt',
					'response_text' => 'Visible response',
				)
			);
			$this->assertStringContainsString( 'Visible prompt', $args['prompt_text'] );
			$this->assertStringContainsString( 'Visible response', $args['response_text'] );
		} finally {
			$this->plugin->settings->options = $original_options;
		}
	}

	/**
	 * On network-activated multisite, the toggle lives in wp_stream_network.
	 * In-memory per-site options must not win.
	 *
	 * @group ms-required
	 */
	public function test_log_option_reads_network_setting_when_network_activated() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$network_key      = $this->plugin->settings->network_options_key;
		$original_network = get_site_option( $network_key, false );
		$original_options = $this->plugin->settings->options;

		add_filter( 'wp_stream_is_network_activated', '__return_true' );
		$this->plugin->settings->options['ai-client_log_prompt_and_response_text'] = 0;

		try {
			update_site_option(
				$network_key,
				array( 'ai-client_log_prompt_and_response_text' => 1 )
			);
			$this->register_settings_backed_connector();
			$args = $this->fire_generation_and_get_log_args(
				array(
					'user_message'  => 'Network prompt',
					'response_text' => 'Network response',
				)
			);
			$this->assertStringContainsString(
				'Network prompt',
				$args['prompt_text'],
				'Network option enabled must win over empty per-site options.'
			);
			$this->assertStringContainsString(
				'Network response',
				$args['response_text'],
				'Network option enabled must win over empty per-site options.'
			);
		} finally {
			remove_filter( 'wp_stream_is_network_activated', '__return_true' );
			$this->plugin->settings->options = $original_options;
			if ( false === $original_network ) {
				delete_site_option( $network_key );
			} else {
				update_site_option( $network_key, $original_network );
			}
		}//end try
	}

	// -------------------------------------------------------------------------
	// Prompt extraction edge cases
	// -------------------------------------------------------------------------

	/**
	 * A single user message is extracted as plain text (no heading).
	 */
	public function test_single_user_message_extracted_as_plain_text() {
		$captured_args = null;

		$this->mock->method( 'is_prompt_and_response_logging_enabled' )
			->willReturnCallback( array( self::class, 'enable_prompt_and_response_logging' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args ) use ( &$captured_args ) {
					$captured_args = $args;
				}
			);

		$pair = $this->make_event_pair( array( 'user_message' => 'Single user turn' ) );
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		// Plain text, no "[User]" heading.
		$this->assertSame( 'Single user turn', $captured_args['prompt_text'] );
	}

	/**
	 * Multiple messages receive section headings.
	 */
	public function test_multi_turn_messages_receive_section_headings() {
		$captured_args = null;

		$this->mock->method( 'is_prompt_and_response_logging_enabled' )
			->willReturnCallback( array( self::class, 'enable_prompt_and_response_logging' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args ) use ( &$captured_args ) {
					$captured_args = $args;
				}
			);

		$pair = $this->make_event_pair(
			array(
				'messages' => array(
					$this->make_message( 'user', 'Hello' ),
					$this->make_message( 'model', 'Hi there!' ),
					$this->make_message( 'user', 'How are you?' ),
				),
			)
		);
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		$prompt = $captured_args['prompt_text'];
		$this->assertStringContainsString( '[User]', $prompt );
		$this->assertStringContainsString( '[Assistant]', $prompt );
		$this->assertStringContainsString( 'Hello', $prompt );
		$this->assertStringContainsString( 'Hi there!', $prompt );
	}

	/**
	 * Model-level system instruction is prepended before message-derived prompt text.
	 */
	public function test_model_system_instruction_prepended_to_prompt() {
		$captured_args = null;

		$this->mock->method( 'is_prompt_and_response_logging_enabled' )
			->willReturnCallback( array( self::class, 'enable_prompt_and_response_logging' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args ) use ( &$captured_args ) {
					$captured_args = $args;
				}
			);

		$pair = $this->make_event_pair(
			array(
				'user_message'       => 'Hello',
				'system_instruction' => 'You are a helpful assistant.',
			)
		);
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		$prompt = $captured_args['prompt_text'];
		$this->assertStringContainsString( '[System]', $prompt );
		$this->assertStringContainsString( 'You are a helpful assistant.', $prompt );
		$this->assertStringContainsString( '[User]', $prompt );
		$this->assertStringContainsString( 'Hello', $prompt );
		// System block must come before user text.
		$this->assertLessThan(
			strpos( $prompt, 'Hello' ),
			strpos( $prompt, 'You are a helpful assistant.' )
		);
	}

	/**
	 * Messages with unrecognised roles are skipped (no fatal, no partial output).
	 */
	public function test_unknown_role_message_skipped_gracefully() {
		$captured_args = null;

		$this->mock->method( 'is_prompt_and_response_logging_enabled' )
			->willReturnCallback( array( self::class, 'enable_prompt_and_response_logging' ) );

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback(
				function ( $message, $args ) use ( &$captured_args ) {
					$captured_args = $args;
				}
			);

		// One good user message + one message with an unrecognised role.
		$unknown_role_message = new class() {
			public function getRole() {
				return new class() {
					// No value, no string map, no is*() methods.
				};
			}
			public function getParts() {
				return array();
			}
		};

		$pair = $this->make_event_pair(
			array(
				'messages' => array(
					$this->make_message( 'user', 'Hello' ),
					$unknown_role_message,
				),
			)
		);
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		// Still logs — only the valid user message appears in the prompt.
		$this->assertSame( 'Hello', $captured_args['prompt_text'] );
	}

	// -------------------------------------------------------------------------
	// Summary routing (message placeholders + meta stripping)
	// -------------------------------------------------------------------------

	/**
	 * Builds a connector mock for filter_wp_stream_record_array tests.
	 *
	 * @param bool $logging_enabled Whether prompt/response logging is enabled.
	 * @return Connector_AI_Client
	 */
	private function make_filter_connector_mock( $logging_enabled ) {
		$connector = $this->getMockBuilder( Connector_AI_Client::class )
			->onlyMethods( array( 'is_prompt_and_response_logging_enabled' ) )
			->getMock();

		$connector->method( 'is_prompt_and_response_logging_enabled' )
			->willReturn( $logging_enabled );

		return $connector;
	}

	/**
	 * Log message includes prompt/response placeholders when text logging is enabled.
	 */
	public function test_log_message_includes_prompt_and_response_placeholders_when_enabled() {
		self::$captured_log_message = null;
		self::$captured_log_args    = null;

		$this->mock->method( 'is_prompt_and_response_logging_enabled' )
			->willReturnCallback( array( self::class, 'enable_prompt_and_response_logging' ) );
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback( array( self::class, 'capture_log_call' ) );

		$pair = $this->make_event_pair(
			array(
				'user_message'  => 'Hello prompt',
				'response_text' => 'Hello response',
			)
		);
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		$this->assertStringContainsString( 'Prompt', self::$captured_log_message );
		$this->assertStringContainsString( 'Response', self::$captured_log_message );
		$this->assertStringContainsString( '%8$s', self::$captured_log_message );
		$this->assertStringContainsString( '%9$s', self::$captured_log_message );
		$this->assertSame( 'Hello prompt', self::$captured_log_args['prompt_text'] );
		$this->assertSame( 'Hello response', self::$captured_log_args['response_text'] );
	}

	/**
	 * Summary token placeholders render as input/thought/output (%4$d/%6$d/%5$d).
	 */
	public function test_log_message_token_order_is_input_thought_output() {
		self::$captured_log_message = null;
		self::$captured_log_args    = null;

		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback( array( self::class, 'capture_log_call' ) );

		$pair = $this->make_event_pair(
			array(
				'provider'      => 'openai',
				'model'         => 'gpt-4o',
				'operation'     => 'text_generation',
				'input_tokens'  => 10,
				'output_tokens' => 5,
			)
		);
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		$this->assertIsString( self::$captured_log_message );
		$this->assertIsArray( self::$captured_log_args );
		$this->assertSame( 10, self::$captured_log_args['input_tokens'] );
		$this->assertSame( 5, self::$captured_log_args['output_tokens'] );
		$this->assertSame( 0, self::$captured_log_args['thought_tokens'] );

		$rendered = vsprintf( self::$captured_log_message, array_values( self::$captured_log_args ) );
		$this->assertStringContainsString( '(tokens: 10/0/5)', $rendered );
		$this->assertStringNotContainsString( '(tokens: 10/5/0)', $rendered );
	}

	/**
	 * filter_wp_stream_record_array passes through non-array values unchanged.
	 */
	public function test_filter_record_array_passes_through_non_array() {
		$connector = $this->make_filter_connector_mock( true );

		$this->assertFalse( $connector->filter_wp_stream_record_array( false ) );
		$this->assertNull( $connector->filter_wp_stream_record_array( null ) );
	}

	/**
	 * filter_wp_stream_record_array removes prompt/response meta when logging is enabled.
	 */
	public function test_filter_record_array_removes_text_meta_when_logging_enabled() {
		$connector = $this->make_filter_connector_mock( true );
		$record    = array(
			'connector' => 'ai-client',
			'meta'      => array(
				'operation'     => 'chat',
				'provider'      => 'openai',
				'prompt_text'   => 'The user prompt.',
				'response_text' => 'The AI response.',
			),
		);

		$result = $connector->filter_wp_stream_record_array( $record );

		$this->assertArrayHasKey( 'operation', $result['meta'] );
		$this->assertArrayHasKey( 'provider', $result['meta'] );
		$this->assertArrayNotHasKey( 'prompt_text', $result['meta'] );
		$this->assertArrayNotHasKey( 'response_text', $result['meta'] );
	}

	/**
	 * filter_wp_stream_record_array keeps prompt/response meta when logging is disabled.
	 */
	public function test_filter_record_array_keeps_text_meta_when_logging_disabled() {
		$connector = $this->make_filter_connector_mock( false );
		$record    = array(
			'connector' => 'ai-client',
			'meta'      => array(
				'operation'     => 'chat',
				'prompt_text'   => 'The user prompt.',
				'response_text' => 'The AI response.',
			),
		);

		$result = $connector->filter_wp_stream_record_array( $record );

		$this->assertSame( $record, $result );
	}

	/**
	 * filter_wp_stream_record_array ignores records for other connectors.
	 */
	public function test_filter_record_array_ignores_other_connectors() {
		$connector = $this->make_filter_connector_mock( true );
		$record    = array(
			'connector' => 'posts',
			'meta'      => array(
				'prompt_text'   => 'Should stay',
				'response_text' => 'Should stay',
				'post_id'       => 42,
			),
		);

		$result = $connector->filter_wp_stream_record_array( $record );

		$this->assertSame( $record, $result );
	}

	// -------------------------------------------------------------------------
	// Settings: add_settings_fields
	// -------------------------------------------------------------------------

	/**
	 * add_settings_fields injects one checkbox under the 'ai-client' key.
	 */
	public function test_add_settings_fields_injects_checkboxes() {
		$connector = new Connector_AI_Client();
		$fields    = $connector->add_settings_fields( array() );

		$this->assertArrayHasKey( 'ai-client', $fields );
		$this->assertCount( 1, $fields['ai-client']['fields'] );

		$field_names = array_column( $fields['ai-client']['fields'], 'name' );
		$this->assertContains( Connector_AI_Client::LOG_PROMPT_AND_RESPONSE_TEXT_OPTION_NAME, $field_names );

		// Defaults to off.
		foreach ( $fields['ai-client']['fields'] as $field ) {
			$this->assertSame( 0, $field['default'] );
		}

		$desc = $fields['ai-client']['fields'][0]['desc'];
		$this->assertStringContainsString( 'Privacy Warning:', $desc );
		$this->assertStringContainsString( 'alerts', $desc );
		$this->assertStringContainsString( 'wp_stream_ai_client_log_prompt', $desc );
	}

	// -------------------------------------------------------------------------
	// Log-text filter
	// -------------------------------------------------------------------------

	/**
	 * wp_stream_ai_client_log_prompt can replace stored prompt text.
	 */
	public function test_log_prompt_filter_can_redact_prompt() {
		// Arrange
		$this->mock->method( 'is_prompt_and_response_logging_enabled' )
			->willReturnCallback( array( self::class, 'enable_prompt_and_response_logging' ) );
		self::$captured_log_args = null;
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback( array( self::class, 'capture_log_args' ) );
		add_filter( 'wp_stream_ai_client_log_prompt', array( self::class, 'prefix_redacted_log_text' ), 10, 2 );

		try {
			// Act
			$pair = $this->make_event_pair( array( 'user_message' => 'Secret' ) );
			do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
			do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

			// Assert
			$this->assertStringStartsWith( 'REDACTED:', self::$captured_log_args['prompt_text'] );
			$this->assertStringContainsString( 'Secret', self::$captured_log_args['prompt_text'] );
		} finally {
			remove_filter( 'wp_stream_ai_client_log_prompt', array( self::class, 'prefix_redacted_log_text' ), 10 );
		}
	}

	/**
	 * wp_stream_ai_client_log_response can replace stored response text.
	 */
	public function test_log_response_filter_can_redact_response() {
		// Arrange
		$this->mock->method( 'is_prompt_and_response_logging_enabled' )
			->willReturnCallback( array( self::class, 'enable_prompt_and_response_logging' ) );
		self::$captured_log_args = null;
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback( array( self::class, 'capture_log_args' ) );
		add_filter( 'wp_stream_ai_client_log_response', array( self::class, 'prefix_redacted_log_text' ), 10, 2 );

		try {
			// Act
			$pair = $this->make_event_pair(
				array(
					'user_message'  => 'Visible prompt',
					'response_text' => 'Secret response',
				)
			);
			do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
			do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

			// Assert
			$this->assertStringContainsString( 'Visible prompt', self::$captured_log_args['prompt_text'] );
			$this->assertStringStartsWith( 'REDACTED:', self::$captured_log_args['response_text'] );
			$this->assertStringContainsString( 'Secret response', self::$captured_log_args['response_text'] );
		} finally {
			remove_filter( 'wp_stream_ai_client_log_response', array( self::class, 'prefix_redacted_log_text' ), 10 );
		}
	}

	/**
	 * Real WP AI Client MessageRoleEnum extracts as a user prompt.
	 */
	public function test_real_message_role_enum_extracts_user_prompt() {
		if ( ! class_exists( '\WordPress\AiClient\Messages\Enums\MessageRoleEnum' ) ) {
			$this->markTestSkipped( 'WP AI Client MessageRoleEnum is not available.' );
		}

		// Arrange
		$role    = \WordPress\AiClient\Messages\Enums\MessageRoleEnum::user();
		$message = new class( $role ) {
			private $role;

			public function __construct( $role ) {
				$this->role = $role;
			}

			public function getRole() {
				return $this->role;
			}

			public function getParts() {
				$part = new class() {
					public function getText() {
						return 'Hello from core enum';
					}
				};
				return array( $part );
			}
		};

		$this->mock->method( 'is_prompt_and_response_logging_enabled' )
			->willReturnCallback( array( self::class, 'enable_prompt_and_response_logging' ) );
		self::$captured_log_args = null;
		$this->mock->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback( array( self::class, 'capture_log_args' ) );

		// Act
		$pair = $this->make_event_pair( array( 'messages' => array( $message ) ) );
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		// Assert
		$this->assertSame( 'Hello from core enum', self::$captured_log_args['prompt_text'] );
	}

	// -------------------------------------------------------------------------
	// DTO double helpers
	// -------------------------------------------------------------------------

	/**
	 * Replaces the setUp() mock with one that uses the real is_prompt_and_response_logging_enabled().
	 *
	 * @return void
	 */
	private function register_settings_backed_connector() {
		$this->mock->unregister();

		self::$captured_log_args = null;

		$connector = $this->getMockBuilder( Connector_AI_Client::class )
			->onlyMethods( array( 'log' ) )
			->getMock();

		$connector->expects( $this->once() )
			->method( 'log' )
			->willReturnCallback( array( self::class, 'capture_log_args' ) );

		$connector->register();
		$this->mock = $connector;
	}

	/**
	 * Fires a matched before/after generation and returns the captured log args.
	 *
	 * @param array<string, mixed> $opts Options for make_event_pair().
	 * @return array<string, mixed>|null
	 */
	private function fire_generation_and_get_log_args( array $opts ) {
		$pair = $this->make_event_pair( $opts );
		do_action( 'wp_ai_client_before_generate_result', $pair['before'] );
		do_action( 'wp_ai_client_after_generate_result', $pair['after'] );

		return self::$captured_log_args;
	}

	/**
	 * Builds a matched before/after event pair sharing the same model instance.
	 *
	 * @param array<string, mixed> $opts Options:
	 *   - provider (string)
	 *   - model (string)
	 *   - operation (string) raw capability value
	 *   - user_message (string) shortcut for single user message
	 *   - messages (object[]) explicit message list (overrides user_message)
	 *   - system_instruction (string) model-level system instruction
	 *   - response_text (string) text returned by result->toText()
	 *   - input_tokens (int)
	 *   - output_tokens (int)
	 *   - finish_reason (string)
	 * @return array{before: object, after: object}
	 */
	private function make_event_pair( array $opts = array() ) {
		$provider         = isset( $opts['provider'] ) ? $opts['provider'] : 'test-provider';
		$model_id         = isset( $opts['model'] ) ? $opts['model'] : 'test-model';
		$capability_value = isset( $opts['operation'] ) ? $opts['operation'] : 'text_generation';
		$response_text    = isset( $opts['response_text'] ) ? $opts['response_text'] : 'Test response.';
		$input_tokens     = isset( $opts['input_tokens'] ) ? $opts['input_tokens'] : 10;
		$output_tokens    = isset( $opts['output_tokens'] ) ? $opts['output_tokens'] : 5;
		$finish_reason    = isset( $opts['finish_reason'] ) ? $opts['finish_reason'] : 'stop';
		$system_instr     = isset( $opts['system_instruction'] ) ? $opts['system_instruction'] : '';

		if ( isset( $opts['messages'] ) ) {
			$messages = $opts['messages'];
		} elseif ( isset( $opts['user_message'] ) ) {
			$messages = array( $this->make_message( 'user', $opts['user_message'] ) );
		} else {
			$messages = array( $this->make_message( 'user', 'Test prompt.' ) );
		}

		$model      = $this->make_model_double( $provider, $model_id, $system_instr );
		$capability = $this->make_capability_double( $capability_value );
		$result     = $this->make_result_double( $model_id, $input_tokens, $output_tokens, $finish_reason, $response_text );

		$before = new class( $messages, $model, $capability ) {
			private $messages;
			private $model;
			private $capability;

			public function __construct( $messages, $model, $capability ) {
				$this->messages   = $messages;
				$this->model      = $model;
				$this->capability = $capability;
			}

			public function getMessages() {
				return $this->messages;
			}

			public function getModel() {
				return $this->model;
			}

			public function getCapability() {
				return $this->capability;
			}
		};

		$after = new class( $messages, $model, $capability, $result ) {
			private $messages;
			private $model;
			private $capability;
			private $result;

			public function __construct( $messages, $model, $capability, $result ) {
				$this->messages   = $messages;
				$this->model      = $model;
				$this->capability = $capability;
				$this->result     = $result;
			}

			public function getMessages() {
				return $this->messages;
			}

			public function getModel() {
				return $this->model;
			}

			public function getCapability() {
				return $this->capability;
			}

			public function getResult() {
				return $this->result;
			}
		};

		return array(
			'before' => $before,
			'after'  => $after,
		);
	}

	/**
	 * Builds a Message DTO double with the given role string and text content.
	 *
	 * @param string $role_value  'user' or 'model'.
	 * @param string $text        Message text content.
	 * @return object
	 */
	private function make_message( $role_value, $text ) {
		$role = new class( $role_value ) {
			private $value;

			public function __construct( $value ) {
				$this->value = $value;
			}

			public function __get( $name ) {
				return 'value' === $name ? $this->value : null;
			}

			public function __toString() {
				return $this->value;
			}
		};

		$part = new class( $text ) {
			private $text;

			public function __construct( $text ) {
				$this->text = $text;
			}

			public function getText() {
				return $this->text;
			}
		};

		return new class( $role, array( $part ) ) {
			private $role;
			private $parts;

			public function __construct( $role, $parts ) {
				$this->role  = $role;
				$this->parts = $parts;
			}

			public function getRole() {
				return $this->role;
			}

			public function getParts() {
				return $this->parts;
			}
		};
	}

	/**
	 * Builds a ModelInterface DTO double.
	 *
	 * @param string $provider_id Provider ID string.
	 * @param string $model_id    Model ID string.
	 * @param string $system_instr Optional system instruction.
	 * @return object
	 */
	private function make_model_double( $provider_id, $model_id, $system_instr = '' ) {
		$provider_meta = new class( $provider_id ) {
			private $id;

			public function __construct( $id ) {
				$this->id = $id;
			}

			public function getId() {
				return $this->id;
			}
		};

		$model_meta = new class( $model_id ) {
			private $id;

			public function __construct( $id ) {
				$this->id = $id;
			}

			public function getId() {
				return $this->id;
			}

			public function getName() {
				return $this->id . '-display';
			}

			public function getSupportedCapabilities() {
				return array();
			}
		};

		return new class( $provider_meta, $model_meta, $system_instr ) {
			private $provider_meta;
			private $model_meta;
			private $system_instr;

			public function __construct( $provider_meta, $model_meta, $system_instr ) {
				$this->provider_meta = $provider_meta;
				$this->model_meta    = $model_meta;
				$this->system_instr  = $system_instr;
			}

			public function providerMetadata() {
				return $this->provider_meta;
			}

			public function metadata() {
				return $this->model_meta;
			}

			public function getSystemInstruction() {
				return $this->system_instr;
			}
		};
	}

	/**
	 * Builds a CapabilityEnum DTO double with ->value.
	 *
	 * @param string $value Raw capability value (e.g. 'text_generation').
	 * @return object
	 */
	private function make_capability_double( $value ) {
		return new class( $value ) {
			public $value;

			public function __construct( $value ) {
				$this->value = $value;
			}

			public function __toString() {
				return $this->value;
			}
		};
	}

	/**
	 * Builds a GenerativeAiResult DTO double.
	 *
	 * @param string $model_id      Model ID for response metadata.
	 * @param int    $input_tokens  Prompt tokens.
	 * @param int    $output_tokens Completion tokens.
	 * @param string $finish_reason Finish reason string (e.g. 'stop').
	 * @param string $response_text Text to return from toText().
	 * @return object
	 */
	private function make_result_double( $model_id, $input_tokens, $output_tokens, $finish_reason, $response_text ) {
		$token_usage = new class( $input_tokens, $output_tokens ) {
			private $input;
			private $output;

			public function __construct( $input, $output ) {
				$this->input  = $input;
				$this->output = $output;
			}

			public function getPromptTokens() {
				return $this->input;
			}

			public function getCompletionTokens() {
				return $this->output;
			}

			public function getTotalTokens() {
				return $this->input + $this->output;
			}

			public function getThoughtTokens() {
				return null;
			}
		};

		$finish_reason_obj = new class( $finish_reason ) {
			private $value;

			public function __construct( $value ) {
				$this->value = $value;
			}

			public function __toString() {
				return $this->value;
			}
		};

		$candidate = new class( $finish_reason_obj ) {
			private $finish_reason;

			public function __construct( $finish_reason ) {
				$this->finish_reason = $finish_reason;
			}

			public function getFinishReason() {
				return $this->finish_reason;
			}
		};

		$model_meta = new class( $model_id ) {
			private $id;

			public function __construct( $id ) {
				$this->id = $id;
			}

			public function getId() {
				return $this->id;
			}

			public function getName() {
				return $this->id . '-display';
			}

			public function getSupportedCapabilities() {
				return array();
			}
		};

		return new class( $token_usage, $candidate, $model_meta, $response_text ) {
			private $token_usage;
			private $candidate;
			private $model_meta;
			private $response_text;

			public function __construct( $token_usage, $candidate, $model_meta, $response_text ) {
				$this->token_usage   = $token_usage;
				$this->candidate     = $candidate;
				$this->model_meta    = $model_meta;
				$this->response_text = $response_text;
			}

			public function getTokenUsage() {
				return $this->token_usage;
			}

			public function getCandidates() {
				return array( $this->candidate );
			}

			public function getCandidateCount() {
				return 1;
			}

			public function getModelMetadata() {
				return $this->model_meta;
			}

			public function getId() {
				return 'result-001';
			}

			public function toText() {
				return $this->response_text;
			}

			public function getAdditionalData() {
				return array();
			}
		};
	}
}
