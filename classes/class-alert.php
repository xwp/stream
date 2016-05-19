<?php
namespace WP_Stream;

class Alert {

	/**
	 * Alert post ID
	 *
	 * @var int
	 */
	public $ID;

	/**
	 * Creation date
	 *
	 * @var string
	 */
	public $date;

	/**
	 * Status
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Alert author ID
	 *
	 * @var int
	 */
	public $author;

	/**
	 * Alert type
	 *
	 * @var string
	 */
	public $alert_type;

	/**
	 * Alert meta data
	 *
	 * @var int
	 */
	public $alert_meta;

	/**
	 * Alert type object
	 *
	 * @var Alert_Type
	 */
	public $alert_type_obj;

	/**
	 * Class constructor
	 *
	 * @param object $item Alert data
	 * @return void
	 */
	public function __construct( $item, $plugin ) {
		$this->plugin  = $plugin;

		$this->ID      = isset( $item->ID ) ? $item->ID : null;
		$this->status  = isset( $item->status ) ? $item->status : 'wp_stream_disabled';
		$this->date    = isset( $item->date ) ? $item->date : null;
		$this->author  = isset( $item->author ) ? $item->author : null;

		$this->alert_type = isset( $item->alert_type ) ? $item->alert_type : null;
		$this->alert_meta = isset( $item->alert_meta ) ? $item->alert_meta : null;
		$this->alert_type_obj   = isset( $item->alert_type_obj ) ? $item->alert_type_obj : null;
	}

	/**
	 * Check if record matches trigger criteria.
	 *
	 * @param array $recordarr Record data.
	 * @return bool True if a positive match. False otherwise.
	 */
	public function check_record( $record_id, $recordarr ) {
		return apply_filters( 'wp_stream_alert_trigger_check', true, $record_id, $recordarr, $this );
	}

	/**
	 * Trigger alert for a specific record.
	 *
	 * @param int $record_id Record ID.
	 * @param int $recordarr Record Data.
	 * @return void
	 */
	public function send_alert( $record_id, $recordarr ) {
		//@TODO move to do_action style alert types
		$this->alert_type_obj->alert( $record_id, $recordarr, $this->alert_meta );
	}

	/**
	 * Process alert settings
	 *
	 * @return bool True if alert was updated, false if inserted.
	 */
	public function save() {
		if ( ! $this->validate() ) {
			return new \WP_Error( 'validation-error', esc_html__( 'Could not validate record data.', 'stream' ) );
		}

		$args = array(
			'ID'           => $this->ID,
			'post_date'    => $this->date,
			'post_status'  => $this->status,
			'post_content' => '',
			'post_title'   => $this->get_title(),
			'post_author'  => $this->author,
			'post_type'    => 'wp_stream_alerts',
		);

		$post_id = wp_insert_post( $args );

		if ( 0 === $post_id ) {
			return false;
		} else if ( null === $this->ID ) {
			$this->ID = $post_id;
		}

		$meta = array(
			'alert_type'     => $this->alert_type,
			'alert_meta'     => $this->alert_meta,
		);

		foreach ( $meta as $key => $value ) {
			$this->update_meta( $key, $value );
		}

		return true;
	}

	public function display_settings_form( $post ) {
		$this->alert_type_obj->display_settings_form( $this, $post );
	}

	public function process_settings_form( $data, $post ) {

		$this->alert_type_obj->process_settings_form( $this, $post );

		if ( ! $this->validate() ) {
			return new \WP_Error( 'validation-error', esc_html__( 'Could not validate record data.', 'stream' ) );
		}

		$args = array(
			'post_date'    => $this->date,
			'post_status'  => $this->status,
			'post_title'   => $this->get_title(),
			'post_author'  => $this->author,
			'post_type'    => 'wp_stream_alerts',
		);

		foreach ( $args as $key => $value ) {
			$data[ $key ] = $value;
		}

		$meta_input = array(
			'alert_type'     => $this->alert_type,
			'alert_meta'     => $this->alert_meta,
		);

		foreach ( $meta_input as $key => $value ) {
			$this->update_meta( $key, $value );
		}

		return $data;
	}

	public function populate( array $raw ) {
		$keys = get_class_vars( $this );
		$data = array_intersect_key( $raw, $keys );
		foreach ( $data as $key => $val ) {
			$this->{$key} = $val;
		}
	}

	public function validate() {
		return true;
	}

	/**
	 * Query record meta
	 *
	 * @param string $meta_key Meta key to retrieve (optional). Otherwise will
	 * 	grab all meta data for the ID.
	 * @param bool   $single Whether to only retrieve the first value (optional).
	 *
	 * @return mixed Single value if $single is true, array if false.
	 */
	public function get_meta( $meta_key = '', $single = false ) {
		return maybe_unserialize( get_post_meta( $this->ID, $meta_key, $single ) );
	}

	/**
	 * Update record meta
	 *
	 * @param string $meta_key Meta key to update.
	 * @param string $meta_value Value to update with.
	 * @param string $prev_value Previous value to change (optional).
	 * @return array
	 */
	public function update_meta( $meta_key, $meta_value, $prev_value = '' ) {
		return update_post_meta( $this->ID, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Determine the title of the alert.
	 *
	 * @todo enhance human readibility
	 * @return string The title of the alert
	 */
	function get_title() {

		$alert_type = $this->alert_type_obj->name;
		$action  = $this->get_trigger_display( 'action', 'post_title' );
		$author  = $this->get_trigger_display( 'author', 'post_title' );
		$context = $this->get_trigger_display( 'context', 'post_title' );

		$format = __( '%1$s when %2$s %3$s an item in %4$s.', 'stream' );
		return sprintf(
			$format,
			ucfirst( $alert_type ),
			ucfirst( $author ),
			$action,
			ucfirst( $context )
		);
	}

	function get_trigger_display( $trigger, $context = 'normal' ) {
		if ( array_key_exists( $trigger, $this->plugin->alerts->alert_triggers ) ) {
			return $this->plugin->alerts->alert_triggers[ $trigger ]->get_display_value( $context, $this );
		} else {
			return false;
		}
	}
}
