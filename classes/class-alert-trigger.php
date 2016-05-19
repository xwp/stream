<?php
namespace WP_Stream;

abstract class Alert_Trigger {

	public $plugin;
	public $slug;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_action( 'wp_stream_alert_trigger_form_display', array( $this, 'add_fields' ), 10, 2 );
		add_action( 'wp_stream_alert_trigger_form_save', array( $this, 'save_fields' ), 10, 1 );
		add_filter( 'wp_stream_alert_trigger_check', array( $this, 'check_record' ), 10, 4 );
		add_filter( 'stream_alerts_preview_query', array( $this, 'filter_preview_query' ), 10, 2 );
	}

	/**
	 * Checks if a record matches the criteria from the trigger.
	 *
	 * @param int   $record_id Record ID.
	 * @param array $recordarr Record data.
	 * @param Alert $alert The Alert being worked on.
	 * @return bool False on failure, otherwise should return original value of $success.
	 */
	abstract public function check_record ( $success, $record_id, $recordarr, $alert );

	/**
	 * Adds fields to the trigger form.
	 *
	 * @param Form_Generator $form The Form Object to add to.
	 * @param Alert          $alert The Alert being worked on.
	 * @return void
	 */
	abstract public function add_fields( $form, $alert );

	/**
	 * Validate and save Alert object
	 *
	 * @param Alert $alert The Alert being worked on.
	 * @return void
	 */
	abstract public function save_fields( $alert );

	/**
	 * Alters the preview table query to show records matching this query.
	 *
	 * @param array $query_args The database query arguments for the table.
	 * @param Alert $alert The Alert being worked on.
	 * @return array The new query arguments.
	 */
	public function filter_preview_query( $query_args, $alert ) {
		return $query_args;
	}

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
