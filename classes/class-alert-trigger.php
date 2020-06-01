<?php
/**
 * Alert Trigger abstract class.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class Alert_Trigger
 *
 * @package WP_Stream
 */
abstract class Alert_Trigger {

	/**
	 * Holds instance of plugin object
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Unique identifier
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Class constructor
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_action( 'wp_stream_alert_trigger_form_display', array( $this, 'add_fields' ), 10, 2 );
		add_action( 'wp_stream_alert_trigger_form_save', array( $this, 'save_fields' ), 10, 1 );
		add_filter( 'wp_stream_alert_trigger_check', array( $this, 'check_record' ), 10, 4 );
	}

	/**
	 * Checks if a record matches the criteria from the trigger.
	 *
	 * @filter wp_stream_alert_trigger_check
	 *
	 * @param bool  $success Status of previous checks.
	 * @param int   $record_id Record ID.
	 * @param array $recordarr Record data.
	 * @param Alert $alert The Alert being worked on.
	 * @return bool False on failure, otherwise should return original value of $success.
	 */
	abstract public function check_record ( $success, $record_id, $recordarr, $alert );

	/**
	 * Adds fields to the trigger form.
	 *
	 * @action wp_stream_alert_trigger_form_display
	 *
	 * @param Form_Generator $form The Form Object to add to.
	 * @param Alert          $alert The Alert being worked on.
	 * @return void
	 */
	abstract public function add_fields( $form, $alert );

	/**
	 * Validate and save Alert object
	 *
	 * @action wp_stream_alert_trigger_form_save
	 *
	 * @param Alert $alert The Alert being worked on.
	 * @return void
	 */
	abstract public function save_fields( $alert );

	/**
	 * Returns the trigger's value for the given alert.
	 *
	 * @param string $context The location this data will be displayed in.
	 * @param Alert  $alert Alert being processed.
	 * @return string
	 */
	abstract public function get_display_value( $context = 'normal', $alert );

	/**
	 * Allow connectors to determine if their dependencies is satisfied or not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		return true;
	}
}
