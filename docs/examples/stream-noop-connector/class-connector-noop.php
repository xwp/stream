<?php
/**
 * Connector that logs a single demo event when the sample admin button is clicked.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Connector_Noop
 */
class Connector_Noop extends Connector {
	/**
	 * Connector slug.
	 *
	 * @var string
	 */
	public string $name = 'noop';

	/**
	 * Actions registered for this connector.
	 *
	 * @var array<int, string>
	 */
	public array $actions = array(
		'stream_noop_demo_clicked',
	);

	/**
	 * This sample only fires from wp-admin.
	 *
	 * @var bool
	 */
	public bool $register_frontend = false;

	/**
	 * Return translated connector label.
	 *
	 * @return string
	 */
	public function get_label() {
		return esc_html__( 'Noop (example)', 'stream-noop-connector' );
	}

	/**
	 * Return translated context labels.
	 *
	 * @return array<string, string>
	 */
	public function get_context_labels() {
		return array(
			'demo' => esc_html__( 'Demo', 'stream-noop-connector' ),
		);
	}

	/**
	 * Return translated action labels.
	 *
	 * @return array<string, string>
	 */
	public function get_action_labels() {
		return array(
			'clicked' => esc_html__( 'Clicked', 'stream-noop-connector' ),
		);
	}

	/**
	 * Log one fake event when the sample admin button is submitted.
	 *
	 * @action stream_noop_demo_clicked
	 *
	 * @return void
	 */
	public function callback_stream_noop_demo_clicked() {
		$this->log(
			__( 'Noop demo button clicked.', 'stream-noop-connector' ),
			array(),
			null,
			'demo',
			'clicked'
		);
	}
}
