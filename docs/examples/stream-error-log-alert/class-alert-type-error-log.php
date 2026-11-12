<?php
/**
 * Alert type that writes triggered records to the PHP error log.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Type_Error_Log
 */
class Alert_Type_Error_Log extends Alert_Type {

	/**
	 * Alert type name shown in the Stream Alerts dropdown.
	 *
	 * @var string
	 */
	public string $name = 'Error Log (example)';

	/**
	 * Alert type slug.
	 *
	 * @var string
	 */
	public string $slug = 'error-log';

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Plugin object.
	 */
	public function __construct( Plugin $plugin ) {
		parent::__construct( $plugin );

		add_filter(
			'wp_stream_alerts_save_meta',
			array( self::class, 'add_alert_meta' ),
			10,
			2
		);
	}

	/**
	 * Write the triggered record to the PHP error log.
	 *
	 * @param int   $record_id Record that triggered the alert.
	 * @param array $recordarr Record details.
	 * @param mixed $alert     Alert object (Stream passes Alert, not an options array).
	 * @return void
	 */
	public function alert( $record_id, $recordarr, $alert ) {
		$prefix = 'Stream alert';
		if ( is_object( $alert ) && ! empty( $alert->alert_meta['prefix'] ) ) {
			$prefix = (string) $alert->alert_meta['prefix'];
		}

		$summary = '';
		if ( isset( $recordarr['summary'] ) ) {
			$summary = (string) $recordarr['summary'];
		}

		error_log( sprintf( '%s: record %d %s', $prefix, (int) $record_id, $summary ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- reason: sample alert type is a teaching example that writes triggered records to the PHP error log on purpose.
	}

	/**
	 * Display an optional log-prefix field.
	 *
	 * @param Alert|array $alert Alert currently being worked on.
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
				'prefix' => 'Stream alert',
			)
		);

		$form = new Form_Generator();
		echo '<span class="wp_stream_alert_type_description">' . esc_html__( 'Write each triggered record to the PHP error log.', 'stream-error-log-alert' ) . '</span>';
		echo '<label for="wp_stream_error_log_prefix"><span class="title">' . esc_html__( 'Prefix', 'stream-error-log-alert' ) . '</span>';
		echo '<span class="input-text-wrap">';
		$form->render_field(
			'text',
			array(
				'name'  => 'wp_stream_error_log_prefix',
				'title' => esc_attr( __( 'Log prefix', 'stream-error-log-alert' ) ),
				'value' => $options['prefix'],
			)
		);
		echo '</span></label>';
	}

	/**
	 * Persist the prefix field. Stream never calls Alert_Type::save_fields().
	 *
	 * @param array  $alert_meta The metadata to be inserted for this alert.
	 * @param string $alert_type The type of alert being added or updated.
	 * @return array
	 */
	public static function add_alert_meta( $alert_meta, $alert_type ) {
		if ( 'error-log' !== $alert_type ) {
			return $alert_meta;
		}

		$prefix = wp_stream_filter_input( INPUT_POST, 'wp_stream_error_log_prefix' );
		if ( empty( $prefix ) ) {
			$alert_meta['prefix'] = 'Stream alert';
		} else {
			$alert_meta['prefix'] = $prefix;
		}

		return $alert_meta;
	}
}
