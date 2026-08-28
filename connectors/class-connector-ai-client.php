<?php
/**
 * Connector for WordPress AI Client.
 *
 * Logs every AI generation call (prompt, response, tokens, model, provider, duration)
 * as a Stream activity record. Integrates with the WordPress AI Client that ships
 * with WordPress 7.0+, listening to the wp_ai_client_before/after_generate_result
 * hooks fired by WP_AI_Client_Event_Dispatcher.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_AI_Client
 */
class Connector_AI_Client extends Connector {

	/**
	 * Cap on in-flight rows so a WP-CLI batch of failed generations cannot grow
	 * without bound. Oldest entry is dropped first.
	 *
	 * @var int
	 */
	const MAX_PENDING_GENERATIONS = 10;

	/**
	 * Option name for the prompt and response text logging setting.
	 *
	 * @var string
	 */
	const LOG_PROMPT_AND_RESPONSE_TEXT_OPTION_NAME = 'log_prompt_and_response_text';

	/**
	 * Connector slug — must be unique across all Stream connectors.
	 * Used as the key in load_connectors() and in Stream settings option keys.
	 *
	 * @var string
	 */
	public $name = 'ai-client';

	/**
	 * WordPress actions this connector listens to.
	 *
	 * Stream's Connector::register() maps each action to a callback_<action>() method
	 * by replacing non-alphanumeric characters with underscores.
	 *
	 * @var string[]
	 */
	public $actions = array(
		'wp_ai_client_before_generate_result',
		'wp_ai_client_after_generate_result',
	);

	/**
	 * In-flight generations keyed by the model object itself.
	 *
	 * WP AI Client fires before/after hooks with the *same* model instance and does
	 * not expose a request ID. SplObjectStorage uses object identity (O(1)), keeps
	 * the model alive so PHP cannot recycle spl_object_id, and avoids pairing a
	 * later generation with a stale pending row when the after-hook never fires.
	 *
	 * @var \SplObjectStorage<object, array{provider: string, model: string, operation: string, prompt_text: string, start: float}>|null
	 */
	private $pending;

	/**
	 * Registers action hooks and adds this connector's settings fields.
	 *
	 * @return void
	 */
	public function register() {
		parent::register();
		add_filter( 'wp_stream_settings_option_fields', array( $this, 'add_settings_fields' ) );
		add_filter( 'wp_stream_record_array', array( $this, 'filter_wp_stream_record_array' ), 10, 1 );
	}

	/**
	 * Returns the connector's human-readable label.
	 *
	 * @return string
	 */
	public function get_label() {
		return esc_html__( 'AI Client', 'stream' );
	}

	/**
	 * Returns translated context labels used to categorise log entries.
	 *
	 * @return array<string, string>
	 */
	public function get_context_labels() {
		return array(
			'prompts' => esc_html__( 'Prompts', 'stream' ),
		);
	}

	/**
	 * Returns translated action labels used to describe what happened.
	 *
	 * @return array<string, string>
	 */
	public function get_action_labels() {
		return array(
			'generated' => esc_html__( 'Generated', 'stream' ),
		);
	}

	/**
	 * True when something dispatches AI Client events onto WordPress actions.
	 *
	 * WP_AI_Client_Event_Dispatcher is the core adapter that calls
	 * do_action( 'wp_ai_client_{event}' ). The SDK class WordPress\AiClient\AiClient
	 * can exist without that wiring (e.g. a Composer copy of php-ai-client on
	 * WP 6.x). Core loads the adapter in wp-settings.php before Stream's
	 * init priority 9, so this check is valid at gate time on WP 7.0+.
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		// The SDK class alone does not fire WordPress actions. The adapter
		// WP_AI_Client_Event_Dispatcher::dispatch() is what calls
		// do_action( 'wp_ai_client_{event}' ). Core wires it in wp-settings.php
		// before init, so this is true at connector load time on WP 7.0+.
		return class_exists( 'WP_AI_Client_Event_Dispatcher' );
	}

	/**
	 * Injects the AI Client settings section into Stream's settings fields array.
	 *
	 * Adds opt-in checkbox — log_prompt_and_response_text — under
	 * a dedicated "AI Client" section in Stream → Settings. Both default to off.
	 *
	 * @param array<string, array{title: string, fields: list<array<string, mixed>>}>|mixed $fields Stream settings fields.
	 * @return array<string, array{title: string, fields: list<array<string, mixed>>}>|mixed
	 */
	public function add_settings_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		$pii_warning = sprintf(
			'<strong>%s</strong> %s %s',
			esc_html__( 'Privacy Warning:', 'stream' ),
			esc_html__( 'This content may include personally identifiable information (PII). Ensure your privacy policy covers AI data collection before enabling.', 'stream' ),
			esc_html__( 'When enabled, prompt and response text are stored in the record summary and may be forwarded verbatim to any configured Stream alerts or webhooks (e.g. Slack, IFTTT). Use the wp_stream_ai_client_log_prompt and wp_stream_ai_client_log_response filters to redact or omit text before it is stored.', 'stream' )
		);

		$fields[ $this->name ] = array(
			'title'  => esc_html__( 'AI Client', 'stream' ),
			'fields' => array(
				array(
					'name'        => self::LOG_PROMPT_AND_RESPONSE_TEXT_OPTION_NAME,
					'title'       => esc_html__( 'Log Prompt and Response text', 'stream' ),
					'type'        => 'checkbox',
					'desc'        => $pii_warning,
					'after_field' => esc_html__( 'Enabled', 'stream' ),
					'default'     => 0,
				),
			),
		);

		return $fields;
	}

	/**
	 * Returns true when the prompt and response text logging option is enabled in Stream's settings.
	 *
	 * @return bool
	 */
	protected function is_prompt_and_response_logging_enabled() {
		$plugin = wp_stream_get_instance();
		if ( ! isset( $plugin->settings ) || ! ( $plugin->settings instanceof Settings ) ) {
			return false;
		}

		return ! empty(
			$plugin->settings->get_setting_value( $this->name . '_' . self::LOG_PROMPT_AND_RESPONSE_TEXT_OPTION_NAME, false )
		);
	}

	/**
	 * Removes unnecessary meta data from the record array.
	 *
	 * @param array<string, mixed>|mixed $record Record about to be inserted.
	 * @return array<string, mixed>|mixed
	 */
	public function filter_wp_stream_record_array( $record ) {
		if ( ! is_array( $record ) ) {
			return $record;
		}

		if ( ( isset( $record['connector'] ) ? $record['connector'] : '' ) !== $this->name || ! is_array( isset( $record['meta'] ) ? $record['meta'] : null ) ) {
			return $record;
		}

		// Remove the prompt and response text from the meta array if enabled, they're too long to be stored there (meta value is VARCHAR(200)).
		if ( $this->is_prompt_and_response_logging_enabled() ) {
			unset( $record['meta']['prompt_text'] );
			unset( $record['meta']['response_text'] );
		}

		return $record;
	}

	// -------------------------------------------------------------------------
	// AI Client hooks
	// -------------------------------------------------------------------------

	/**
	 * Captures prompt context before the AI HTTP call is made.
	 *
	 * Stores provider, model, operation, optional prompt text, and a high-resolution
	 * start timestamp in the pending array, keyed by the model object's identity.
	 * The matching after-hook reads and clears this entry.
	 *
	 * @action wp_ai_client_before_generate_result
	 *
	 * @param object $event BeforeGenerateResultEvent instance (WordPress\AiClient\Events).
	 * @return void
	 */
	public function callback_wp_ai_client_before_generate_result( $event ) {
		try {
			$model = $event->getModel();
			if ( ! is_object( $model ) ) {
				return;
			}

			$provider  = (string) $model->providerMetadata()->getId();
			$model_id  = (string) $model->metadata()->getId();
			$operation = $this->normalize_capability( $event->getCapability() );

			$prompt_text = '';
			// If prompt logged is enabled, extract the prompt text from the event.
			if ( $this->is_prompt_and_response_logging_enabled() ) {
				$extracted   = $this->extract_prompt_text( $event->getMessages() );
				$prompt_text = $this->merge_model_system_instruction_into_prompt(
					$extracted['text'],
					$this->extract_model_system_instruction( $model ),
					$extracted['has_sections']
				);

				/**
				 * Filters the AI prompt text before Stream stores it.
				 *
				 * Return an empty string to omit the text.
				 *
				 * @param string $prompt_text The prompt text about to be logged.
				 * @param object $event       The BeforeGenerateResultEvent instance.
				 */
				$prompt_text = (string) apply_filters( 'wp_stream_ai_client_log_prompt', $prompt_text, $event );
			}

			// Store the pending data in the pending array, so we can match the after-hook.
			$pending = $this->get_pending_storage();
			$this->evict_oldest_pending_if_full( $pending );
			$pending[ $model ] = array(
				'provider'    => $provider,
				'model'       => $model_id,
				'operation'   => $operation,
				'prompt_text' => $prompt_text,
				'start'       => microtime( true ),
			);
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Stream AI Connector: before_generate failed — ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Finalises the Stream log entry after the AI HTTP call completes.
	 *
	 * Reads and clears the pending entry created by the before-hook, enriches it
	 * with response metadata (tokens, duration, finish reason, optional response
	 * text, extended metadata), and writes the Stream activity record.
	 *
	 * Returns without logging when no matching before-hook entry is found (e.g.
	 * when the connector was registered after the before-hook already fired).
	 *
	 * @action wp_ai_client_after_generate_result
	 *
	 * @param object $event AfterGenerateResultEvent instance (WordPress\AiClient\Events).
	 * @return void
	 */
	public function callback_wp_ai_client_after_generate_result( $event ) {
		try {
			$model   = $event->getModel();
			$storage = $this->get_pending_storage();

			if ( ! is_object( $model ) || ! isset( $storage[ $model ] ) ) {
				return;
			}

			$pending = $storage[ $model ];
			unset( $storage[ $model ] );

			$result      = $event->getResult();
			$token_usage = $result->getTokenUsage();
			$duration_ms = (int) round( ( microtime( true ) - $pending['start'] ) * 1000 );

			$response_text = '';
			if ( $this->is_prompt_and_response_logging_enabled() ) {
				try {
					$response_text = (string) $result->toText();

					/**
					 * Filters the AI response text before Stream stores it.
					 *
					 * Return an empty string to omit the text.
					 *
					 * @param string $response_text The response text about to be logged.
					 * @param object $event         The AfterGenerateResultEvent instance.
					 */
					$response_text = (string) apply_filters( 'wp_stream_ai_client_log_response', $response_text, $event );
				} catch ( \Throwable $e ) {
					unset( $e );
				}
			}

			$input_tokens   = (int) $token_usage->getPromptTokens();
			$output_tokens  = (int) $token_usage->getCompletionTokens();
			$thought_tokens = (int) $token_usage->getThoughtTokens();
			$total_tokens   = (int) $token_usage->getTotalTokens();
			$finish_reason  = $this->extract_finish_reason( $result );
			$response_model = (string) $result->getModelMetadata()->getId();

			$log_args = array(
				'operation'      => $pending['operation'],
				'provider'       => $pending['provider'],
				'model'          => $response_model ? $response_model : $pending['model'],
				'input_tokens'   => $input_tokens,
				'output_tokens'  => $output_tokens,
				'thought_tokens' => $thought_tokens,
				'duration_ms'    => $duration_ms,
				'prompt_text'    => $pending['prompt_text'],
				'response_text'  => $response_text,
				'finish_reason'  => $finish_reason,
				'total_tokens'   => $total_tokens,
			);

			$log_args = $this->append_result_context_to_log_args( $log_args, $event, $model, $result );
			/* translators: 1: AI operation (e.g. "chat"), 2: provider slug, 3: model ID, 4: input token count, 5: output token count, 6: thought token count, 7: duration in milliseconds */
			$message = __( '%1$s via %2$s/%3$s (tokens: %4$d/%6$d/%5$d) in %7$dms', 'stream' );

			// Add the prompt and response text to the message if enabled.
			if ( $this->is_prompt_and_response_logging_enabled() ) {
				/* translators: Placeholders %8$s and %9$s are the prompt and response text bodies. */
				$message .= sprintf( "\n\n[%s]\n%s\n\n[%s]\n%s", __( 'Prompt', 'stream' ), '%8$s', __( 'Response', 'stream' ), '%9$s' );
			}

			$this->log(
				$message,
				$log_args,
				null,
				'prompts',
				'generated'
			);
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Stream AI Connector: after_generate failed — ' . $e->getMessage() );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Prompt extraction
	// -------------------------------------------------------------------------

	/**
	 * Builds logged prompt text from the full message list in order.
	 *
	 * Each message with a recognised role becomes a labeled block. A single
	 * user-only message is returned as plain text (no heading) for readability;
	 * two or more messages get section headings (e.g. "[User]").
	 *
	 * Primary path: WordPress AI Client (WP 7.0+) provides MessageRoleEnum with
	 * ->value = 'user' or 'model'. Third-party callers may pass string roles or
	 * other enum-like objects — narrow fallbacks are kept for those.
	 *
	 * @param object[] $messages Array of Message DTOs from BeforeGenerateResultEvent.
	 * @return array{text: string, has_sections: bool} Assembled prompt text and whether section headings were used.
	 */
	private function extract_prompt_text( array $messages ) {
		$sections = array();

		foreach ( $messages as $message_index => $message ) {
			if ( ! is_object( $message ) ) {
				continue;
			}

			$key = $this->prompt_section_key_for_message( $message );
			if ( null === $key ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'Stream AI Connector: skipping message with unknown role (index ' . $message_index . ')' );
				}
				continue;
			}

			$body = $this->extract_all_text_from_message( $message );
			if ( '' === $body ) {
				continue;
			}

			$sections[] = array(
				'key'  => $key,
				'body' => $body,
			);
		}

		if ( array() === $sections ) {
			return array(
				'text'         => '',
				'has_sections' => false,
			);
		}

		// Single user message — plain text, no heading.
		if ( 1 === count( $sections ) && 'user' === $sections[0]['key'] ) {
			return array(
				'text'         => $sections[0]['body'],
				'has_sections' => false,
			);
		}

		$label_map = array(
			'user'      => __( 'User', 'stream' ),
			'assistant' => __( 'Assistant', 'stream' ),
			'system'    => __( 'System', 'stream' ),
			'developer' => __( 'Developer', 'stream' ),
		);

		$blocks = array();
		foreach ( $sections as $section ) {
			$label = isset( $label_map[ $section['key'] ] ) ? $label_map[ $section['key'] ] : '';
			if ( '' === $label ) {
				continue;
			}
			$blocks[] = '[' . $label . ']' . "\n" . $section['body'];
		}

		return array(
			'text'         => implode( "\n\n", $blocks ),
			'has_sections' => true,
		);
	}

	/**
	 * Returns the canonical role key for a message, or null if unrecognised.
	 *
	 * @param object $message Message DTO.
	 * @return string|null 'user', 'assistant', 'system', 'developer', or null.
	 */
	private function prompt_section_key_for_message( $message ) {
		try {
			$role = $message->getRole();
		} catch ( \Throwable $e ) {
			unset( $e );
			return null;
		}

		$scalar = $this->enum_like_to_string( $role );
		if ( '' !== $scalar ) {
			$key = $this->prompt_section_key_for_scalar_role( $scalar );
			if ( null !== $key ) {
				return $key;
			}
		}

		if ( ! is_object( $role ) ) {
			return null;
		}

		try {
			if ( method_exists( $role, 'isUser' ) && $role->isUser() ) {
				return 'user';
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		try {
			if ( method_exists( $role, 'isSystem' ) && $role->isSystem() ) {
				return 'system';
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		try {
			if ( method_exists( $role, 'isDeveloper' ) && $role->isDeveloper() ) {
				return 'developer';
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		try {
			if ( method_exists( $role, 'isModel' ) && $role->isModel() ) {
				return 'assistant';
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		return null;
	}

	/**
	 * Maps a role string or enum value/name to a canonical section key.
	 *
	 * @param string $role Raw role value.
	 * @return string|null 'user', 'assistant', 'system', 'developer', or null.
	 */
	private function prompt_section_key_for_scalar_role( $role ) {
		$role = strtolower( trim( $role ) );
		if ( 'user' === $role || 'human' === $role || 'input' === $role ) {
			return 'user';
		}
		if ( 'model' === $role || 'assistant' === $role ) {
			return 'assistant';
		}
		if ( 'system' === $role ) {
			return 'system';
		}
		if ( 'developer' === $role ) {
			return 'developer';
		}
		return null;
	}

	/**
	 * Concatenates non-empty text from every text part of a message.
	 *
	 * Calls MessagePart::getText() (returns string|null in WP AI Client core).
	 * Parts of other types (file, function call/response) are skipped.
	 * Multiple parts are joined with newlines.
	 *
	 * @param object $message Message DTO.
	 * @return string
	 */
	private function extract_all_text_from_message( $message ) {
		try {
			$parts = $message->getParts();
		} catch ( \Throwable $e ) {
			unset( $e );
			return '';
		}

		$chunks = array();
		foreach ( $parts as $part ) {
			if ( ! is_object( $part ) ) {
				continue;
			}
			$text = $this->extract_text_from_content_part( $part );
			if ( '' !== $text ) {
				$chunks[] = $text;
			}
		}

		return implode( "\n", $chunks );
	}

	/**
	 * Extracts text from a single message part.
	 *
	 * Calls getText() (primary API in WP AI Client). Returns empty string for
	 * non-text part types (file, function call/response) or when getText() is
	 * null.
	 *
	 * @param object $part Message part DTO.
	 * @return string
	 */
	private function extract_text_from_content_part( $part ) {
		try {
			if ( method_exists( $part, 'getText' ) ) {
				$text = $part->getText();
				if ( is_string( $text ) && '' !== $text ) {
					return $text;
				}
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		return '';
	}

	/**
	 * Reads a model-level system instruction (separate from the message list).
	 *
	 * Tries model->getSystemInstruction() first, then model->getConfig()->getSystemInstruction().
	 * Returns empty string when neither is available.
	 *
	 * @param object $model ModelInterface instance.
	 * @return string
	 */
	private function extract_model_system_instruction( $model ) {
		try {
			if ( method_exists( $model, 'getSystemInstruction' ) ) {
				$instr = $model->getSystemInstruction();
				if ( is_string( $instr ) && '' !== $instr ) {
					return $instr;
				}
			}

			if ( method_exists( $model, 'getConfig' ) ) {
				$config = $model->getConfig();
				if ( is_object( $config ) && method_exists( $config, 'getSystemInstruction' ) ) {
					$instr = $config->getSystemInstruction();
					if ( is_string( $instr ) && '' !== $instr ) {
						return $instr;
					}
				}
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		return '';
	}

	/**
	 * Prepends a model-level system instruction before message-derived prompt text.
	 *
	 * When a system instruction is present and the existing prompt is plain user
	 * text (no section headings), the user text is wrapped in a User heading so
	 * both blocks are visually distinct in Stream's UI.
	 *
	 * @param string $message_prompt    Text assembled from getMessages().
	 * @param string $model_instruction Text from model/config getSystemInstruction().
	 * @param bool   $has_sections      Whether $message_prompt already uses [Label] section headings.
	 * @return string
	 */
	private function merge_model_system_instruction_into_prompt( $message_prompt, $model_instruction, $has_sections = false ) {
		if ( '' === $model_instruction ) {
			return $message_prompt;
		}

		$system_label = __( 'System', 'stream' );
		$user_label   = __( 'User', 'stream' );
		$system_block = '[' . $system_label . ']' . "\n" . $model_instruction;

		if ( '' === $message_prompt ) {
			return $system_block;
		}

		// Already has section headings — prepend system block directly.
		if ( $has_sections ) {
			return $system_block . "\n\n" . $message_prompt;
		}

		// Plain user text — add a User heading for visual consistency.
		return $system_block . sprintf( "\n[%s]\n", $user_label ) . $message_prompt;
	}

	// -------------------------------------------------------------------------
	// Result metadata helpers
	// -------------------------------------------------------------------------

	/**
	 * Extracts the finish reason string from the first result candidate.
	 *
	 * FinishReasonEnum (WP AI Client) extends AbstractEnum; casting to string
	 * returns the enum value (e.g. 'stop').
	 *
	 * @param object $result GenerativeAiResult instance.
	 * @return string
	 */
	private function extract_finish_reason( $result ) {
		try {
			$candidates = $result->getCandidates();
			if ( ! empty( $candidates ) ) {
				$finish_reason = $candidates[0]->getFinishReason();
				// AbstractEnum and BackedEnum both cast cleanly to string.
				return (string) $finish_reason;
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		return '';
	}

	/**
	 * Maps a WP AI Client capability to a Stream operation label.
	 *
	 * Supports AbstractEnum (WP AI Client), BackedEnum, UnitEnum, and objects
	 * with a ->value property. Unknown values pass through as the operation label;
	 * null or unresolvable capabilities fall back to 'unknown'.
	 *
	 * @param object|null $capability CapabilityEnum instance or null.
	 * @return string
	 */
	private function normalize_capability( $capability ) {
		if ( null === $capability ) {
			return 'unknown';
		}

		$raw = strtolower( $this->enum_like_to_string( $capability ) );

		if ( '' === $raw ) {
			return 'unknown';
		}

		return $raw;
	}

	/**
	 * Normalises a role or capability value to a string.
	 *
	 * @param mixed $value Role or capability value.
	 * @return string Empty string when nothing usable is found.
	 */
	private function enum_like_to_string( $value ) {
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( ! is_object( $value ) ) {
			return is_scalar( $value ) ? (string) $value : '';
		}

		if ( method_exists( $value, '__toString' ) ) {
			try {
				$cast = (string) $value;
				if ( '' !== $cast ) {
					return $cast;
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		try {
			$raw = $value->value;
			if ( is_scalar( $raw ) ) {
				return (string) $raw;
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		return is_object( $value ) ? get_class( $value ) : '';
	}

	/**
	 * Appends extended metadata from the result, model, and event to the log args.
	 *
	 * All reads are guarded by method_exists / try-catch so the core logging path
	 * succeeds even when provider-specific fields are absent.
	 *
	 * @param array<string, mixed> $log_args Base log args.
	 * @param object               $event    AfterGenerateResultEvent.
	 * @param object               $model    ModelInterface instance.
	 * @param object               $result   GenerativeAiResult instance.
	 * @return array<string, mixed>
	 */
	private function append_result_context_to_log_args( array $log_args, $event, $model, $result ) {
		$log_args['generator_model_class'] = get_class( $model );

		// Message count.
		if ( method_exists( $event, 'getMessages' ) ) {
			try {
				$log_args['message_count'] = count( $event->getMessages() );
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		// Result ID.
		try {
			if ( method_exists( $result, 'getId' ) ) {
				$log_args['result_id'] = (string) $result->getId();
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		// Candidate count.
		try {
			if ( method_exists( $result, 'getCandidateCount' ) ) {
				$log_args['candidate_count'] = (int) $result->getCandidateCount();
			} elseif ( method_exists( $result, 'getCandidates' ) ) {
				$log_args['candidate_count'] = count( $result->getCandidates() );
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		// Provider metadata from result.
		try {
			if ( method_exists( $result, 'getProviderMetadata' ) ) {
				$pm = $result->getProviderMetadata();
				if ( is_object( $pm ) ) {
					if ( method_exists( $pm, 'getName' ) ) {
						$log_args['provider_name'] = (string) $pm->getName();
					}
					if ( method_exists( $pm, 'getType' ) ) {
						$log_args['provider_type'] = $this->enum_like_to_string( $pm->getType() );
					}
				}
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		// Model metadata from result.
		try {
			$mm = $result->getModelMetadata();
			if ( is_object( $mm ) ) {
				if ( method_exists( $mm, 'getName' ) ) {
					$log_args['model_name'] = (string) $mm->getName();
				}
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		// Additional data keys (sorted for deterministic meta).
		try {
			if ( method_exists( $result, 'getAdditionalData' ) ) {
				$extra = $result->getAdditionalData();
				if ( is_array( $extra ) ) {
					$keys = array_keys( $extra );
					sort( $keys, SORT_STRING );
					$log_args['additional_data_keys'] = array_map( 'strval', $keys );
				}
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}

		return $log_args;
	}

	/**
	 * Returns the pending-generation map, creating it on first use.
	 *
	 * @return \SplObjectStorage<object, array{provider: string, model: string, operation: string, prompt_text: string, start: float}>
	 */
	private function get_pending_storage() {
		if ( ! $this->pending instanceof \SplObjectStorage ) {
			$this->pending = new \SplObjectStorage();
		}

		return $this->pending;
	}

	/**
	 * Drops the oldest in-flight row when the cap is reached.
	 *
	 * @param \SplObjectStorage<object, array> $pending Pending map.
	 * @return void
	 */
	private function evict_oldest_pending_if_full( $pending ) {
		if ( $pending->count() < self::MAX_PENDING_GENERATIONS ) {
			return;
		}

		$pending->rewind();
		if ( $pending->valid() ) {
			$pending->detach( $pending->current() );
		}
	}
}
