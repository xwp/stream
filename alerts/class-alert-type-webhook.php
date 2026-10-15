<?php
/**
 * Outgoing Webhook Alerts.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Type_Webhook
 *
 * @package WP_Stream
 */
class Alert_Type_Webhook extends Alert_Type {

	/**
	 * Record keys that may appear as {{field}} placeholders in the body template.
	 */
	const PLACEHOLDER_FIELDS = array(
		'summary',
		'connector',
		'context',
		'action',
		'user_id',
		'user_role',
		'ip',
		'object_id',
		'blog_id',
		'created',
	);

	/**
	 * Allowed Content-Type header values.
	 */
	const ALLOWED_CONTENT_TYPES = array(
		'application/json',
		'application/json; charset=utf-8',
		'text/plain',
		'application/x-www-form-urlencoded',
	);

	/**
	 * HTTP header names that must not be overridden by configuration.
	 */
	const BLOCKED_HEADER_NAMES = array(
		'host',
		'content-length',
		'transfer-encoding',
	);

	/**
	 * Alert type name
	 *
	 * @var string
	 */
	public string $name = 'Outgoing Webhook';

	/**
	 * Alert type slug
	 *
	 * @var string
	 */
	public string $slug = 'webhook';

	/**
	 * Class Constructor
	 *
	 * @param Plugin $plugin Plugin object.
	 */
	public function __construct( public Plugin $plugin ) {
		parent::__construct( $plugin );

		$this->register_hooks();
	}

	/**
	 * Register hooks for the alert type.
	 *
	 * @return void
	 */
	private function register_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		add_filter(
			'wp_stream_alerts_save_meta',
			array( $this, 'add_alert_meta' ),
			10,
			2
		);
	}

	/**
	 * Send an HTTP request for the matching record.
	 *
	 * @param int   $record_id Record that triggered notification.
	 * @param array $recordarr Record details.
	 * @param Alert $alert     Alert options.
	 * @return void
	 */
	public function alert( $record_id, $recordarr, $alert ) {
		unset( $record_id );

		$options = wp_parse_args(
			is_object( $alert ) ? $alert->alert_meta : array(),
			array(
				'url'           => '',
				'method'        => 'POST',
				'content_type'  => 'application/json',
				'headers'       => array(),
				'body_template' => '',
			)
		);

		$url = is_string( $options['url'] ) ? trim( $options['url'] ) : '';
		if ( '' === $url || false === wp_http_validate_url( $url ) ) {
			return;
		}

		$method = strtoupper( (string) $options['method'] );
		if ( ! in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$method = 'POST';
		}

		$template = is_string( $options['body_template'] ) ? $options['body_template'] : '';
		if ( '' === trim( $template ) ) {
			$template = self::default_body_template();
		}

		$body    = self::substitute_placeholders( $template, (array) $recordarr );
		$headers = self::build_request_headers( $options['content_type'], $options['headers'] );

		$response = wp_safe_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => $headers,
				'body'    => $body,
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Opt-in debug only.
			error_log( sprintf( 'Stream webhook alert request failed: %s', $response->get_error_message() ) );
		}
	}

	/**
	 * Compact JSON object with placeholders for every supported record field.
	 *
	 * @return string
	 */
	public static function default_body_template() {
		$parts = array();
		foreach ( self::PLACEHOLDER_FIELDS as $field ) {
			$parts[] = '"' . $field . '":{{' . $field . '}}';
		}

		return '{' . implode( ',', $parts ) . '}';
	}

	/**
	 * Replace {{field}} tokens with JSON-encoded record values.
	 *
	 * @param string $template  JSON body template.
	 * @param array  $recordarr Record details.
	 * @return string
	 */
	public static function substitute_placeholders( $template, $recordarr ) {
		$replacements = array();
		foreach ( self::PLACEHOLDER_FIELDS as $field ) {
			$value                                = array_key_exists( $field, $recordarr ) ? $recordarr[ $field ] : '';
			$replacements[ '{{' . $field . '}}' ] = wp_json_encode( $value );
		}

		return strtr( (string) $template, $replacements );
	}

	/**
	 * Merge Content-Type with extra header rows; drop blocked names.
	 *
	 * @param string $content_type Requested Content-Type.
	 * @param array  $header_rows  Stored header rows.
	 * @return array
	 */
	public static function build_request_headers( $content_type, $header_rows ) {
		$headers = array(
			'Content-Type' => self::sanitize_content_type( $content_type ),
		);

		foreach ( self::normalize_header_rows( $header_rows ) as $row ) {
			$name  = $row['name'];
			$value = $row['value'];
			$key   = strtolower( $name );
			if ( in_array( $key, self::BLOCKED_HEADER_NAMES, true ) ) {
				continue;
			}
			if ( 'content-type' === $key ) {
				$headers['Content-Type'] = self::sanitize_content_type( $value );
				continue;
			}
			$headers[ $name ] = $value;
		}

		return $headers;
	}

	/**
	 * Restrict Content-Type to the allowlist.
	 *
	 * @param string $content_type Raw Content-Type.
	 * @return string
	 */
	public static function sanitize_content_type( $content_type ) {
		$normalized = strtolower( trim( (string) $content_type ) );
		foreach ( self::ALLOWED_CONTENT_TYPES as $allowed ) {
			if ( strtolower( $allowed ) === $normalized ) {
				return $allowed;
			}
		}

		return 'application/json';
	}

	/**
	 * Normalize stored or posted headers into name/value rows.
	 *
	 * @param mixed $headers Header rows, associative map, or textarea string.
	 * @return array<int, array{name: string, value: string}>
	 */
	public static function normalize_header_rows( $headers ) {
		if ( is_string( $headers ) ) {
			return self::parse_header_textarea( $headers );
		}

		if ( ! is_array( $headers ) ) {
			return array();
		}

		$rows = array();
		foreach ( $headers as $key => $entry ) {
			if ( is_array( $entry ) && isset( $entry['name'] ) ) {
				$name  = (string) $entry['name'];
				$value = isset( $entry['value'] ) ? (string) $entry['value'] : '';
			} elseif ( is_string( $key ) && ! is_numeric( $key ) ) {
				$name  = $key;
				$value = is_scalar( $entry ) ? (string) $entry : '';
			} else {
				continue;
			}

			$sanitized = self::sanitize_header_pair( $name, $value );
			if ( null !== $sanitized ) {
				$rows[] = $sanitized;
			}
		}

		return $rows;
	}

	/**
	 * Parse "Name: value" lines from the additional-headers field.
	 *
	 * @param string $text Textarea contents.
	 * @return array<int, array{name: string, value: string}>
	 */
	public static function parse_header_textarea( $text ) {
		$rows  = array();
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		if ( ! is_array( $lines ) ) {
			return array();
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}
			$parts     = explode( ':', $line, 2 );
			$sanitized = self::sanitize_header_pair( $parts[0], $parts[1] );
			if ( null !== $sanitized ) {
				$rows[] = $sanitized;
			}
		}

		return $rows;
	}

	/**
	 * Sanitize one HTTP header name/value pair.
	 *
	 * @param string $name  Header name.
	 * @param string $value Header value.
	 * @return array{name: string, value: string}|null
	 */
	public static function sanitize_header_pair( $name, $value ) {
		$name  = trim( (string) $name );
		$value = trim( str_replace( array( "\r", "\n" ), '', (string) $value ) );
		if ( '' === $name || ! preg_match( '/^[A-Za-z0-9-]+$/', $name ) ) {
			return null;
		}
		if ( in_array( strtolower( $name ), self::BLOCKED_HEADER_NAMES, true ) ) {
			return null;
		}

		return array(
			'name'  => $name,
			'value' => $value,
		);
	}

	/**
	 * Display settings form for this alert type.
	 *
	 * @param Alert $alert Alert currently being worked on.
	 * @return void
	 */
	public function display_fields( $alert ) {
		$alert_meta = array();
		if ( is_object( $alert ) ) {
			$alert_meta = $alert->alert_meta;
		}
		$options = wp_parse_args(
			$alert_meta,
			array(
				'url'           => '',
				'method'        => 'POST',
				'content_type'  => 'application/json',
				'headers'       => array(),
				'body_template' => self::default_body_template(),
			)
		);

		$header_text = '';
		foreach ( self::normalize_header_rows( $options['headers'] ) as $row ) {
			$header_text .= $row['name'] . ': ' . $row['value'] . "\n";
		}

		$content_type_options = array();
		foreach ( self::ALLOWED_CONTENT_TYPES as $type ) {
			$content_type_options[ $type ] = $type;
		}

		$form = new Form_Generator();
		echo '<span class="wp_stream_alert_type_description">' . esc_html__( 'Send an HTTP POST or PUT request to a URL with a JSON body.', 'stream' ) . '</span>';

		echo '<label for="wp_stream_webhook_url"><span class="title">' . esc_html__( 'URL', 'stream' ) . '</span>';
		echo '<span class="input-text-wrap">';
		$form->render_field(
			'text',
			array(
				'name'  => 'wp_stream_webhook_url',
				'title' => esc_attr( __( 'URL', 'stream' ) ),
				'value' => $options['url'],
			)
		);
		echo '</span>';
		echo '<span class="input-text-wrap">' . esc_html__( 'Must be a valid HTTP or HTTPS URL.', 'stream' ) . '</span>';
		echo '</label>';

		echo '<label for="wp_stream_webhook_method"><span class="title">' . esc_html__( 'Method', 'stream' ) . '</span>';
		echo '<span class="input-text-wrap">';
		$form->render_field(
			'select',
			array(
				'name'    => 'wp_stream_webhook_method',
				'value'   => in_array( $options['method'], array( 'POST', 'PUT' ), true ) ? $options['method'] : 'POST',
				'options' => array(
					'POST' => 'POST',
					'PUT'  => 'PUT',
				),
			)
		);
		echo '</span></label>';

		echo '<label for="wp_stream_webhook_content_type"><span class="title">' . esc_html__( 'Content-Type', 'stream' ) . '</span>';
		echo '<span class="input-text-wrap">';
		$form->render_field(
			'select',
			array(
				'name'    => 'wp_stream_webhook_content_type',
				'value'   => self::sanitize_content_type( $options['content_type'] ),
				'options' => $content_type_options,
			)
		);
		echo '</span></label>';

		echo '<label for="wp_stream_webhook_headers"><span class="title">' . esc_html__( 'Headers', 'stream' ) . '</span>';
		echo '<span class="input-text-wrap">';
		printf(
			'<textarea name="wp_stream_webhook_headers" id="wp_stream_webhook_headers" rows="3" class="large-text">%s</textarea>',
			esc_textarea( $header_text )
		);
		echo '</span>';
		echo '<span class="input-text-wrap">' . esc_html__( 'Optional extra headers, one per line as Name: value. Host cannot be overridden.', 'stream' ) . '</span>';
		echo '</label>';

		echo '<label for="wp_stream_webhook_body_template"><span class="title">' . esc_html__( 'Body template', 'stream' ) . '</span>';
		echo '<span class="input-text-wrap">';
		printf(
			'<textarea name="wp_stream_webhook_body_template" id="wp_stream_webhook_body_template" rows="5" class="large-text code">%s</textarea>',
			esc_textarea( $options['body_template'] )
		);
		echo '</span>';
		echo '<span class="input-text-wrap">' . esc_html__( 'JSON with {{summary}}, {{connector}}, {{context}}, {{action}}, {{user_id}}, {{user_role}}, {{ip}}, {{object_id}}, {{blog_id}}, {{created}}. Values are JSON-encoded.', 'stream' ) . '</span>';
		echo '</label>';
	}

	/**
	 * Add webhook fields when this alert type is saved.
	 *
	 * @param array  $alert_meta The metadata to be inserted for this alert.
	 * @param string $alert_type The type of alert being added or updated.
	 *
	 * @return array
	 */
	public function add_alert_meta( $alert_meta, $alert_type ) {
		if ( 'webhook' !== $alert_type ) {
			return $alert_meta;
		}

		$url = wp_stream_filter_input( INPUT_POST, 'wp_stream_webhook_url' );
		if ( ! empty( $url ) ) {
			$alert_meta['url'] = esc_url_raw( $url );
		}

		$method               = wp_stream_filter_input( INPUT_POST, 'wp_stream_webhook_method' );
		$method               = is_string( $method ) ? strtoupper( $method ) : 'POST';
		$alert_meta['method'] = in_array( $method, array( 'POST', 'PUT' ), true ) ? $method : 'POST';

		$content_type               = wp_stream_filter_input( INPUT_POST, 'wp_stream_webhook_content_type' );
		$alert_meta['content_type'] = self::sanitize_content_type( (string) $content_type );

		$headers_raw           = wp_stream_filter_input( INPUT_POST, 'wp_stream_webhook_headers' );
		$alert_meta['headers'] = self::normalize_header_rows( is_string( $headers_raw ) ? $headers_raw : '' );

		$body_template               = wp_stream_filter_input( INPUT_POST, 'wp_stream_webhook_body_template' );
		$alert_meta['body_template'] = is_string( $body_template ) ? $body_template : '';

		return $alert_meta;
	}
}
