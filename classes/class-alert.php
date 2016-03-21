<?php
namespace WP_Stream;

class Alert {
	public $ID;
	public $date;
	public $author;

	public $filter_author;
	public $filter_action;
	public $filter_context;
	public $alert_type;
	public $alert_meta;

	public function __construct( $item ) {

		$this->ID      = isset( $item->ID ) ? $item->ID : null;
		$this->date    = isset( $item->date ) ? $item->date : null;
		$this->author = isset( $item->author ) ? $item->author : null;

		$this->filter_action  = isset( $item->filter_action ) ? $item->filter_action : null;
		$this->filter_author  = isset( $item->filter_author ) ? $item->filter_author : null;
		$this->filter_context = isset( $item->filter_context ) ? $item->filter_context : null;

		$this->alert_type = isset( $item->alert_type ) ? $item->alert_type : null;
		$this->alert_meta = isset( $item->alert_meta ) ? $item->alert_meta : null;
	}

	public function save() {
		if ( ! $this->validate() ) {
			return new \WP_Error( 'validation-error', esc_html__( 'Could not validate record data.', 'stream' ) );
		}

		$args = array(
			'ID'           => $this->ID,
			'post_date'    => $this->date,
			'post_content' => '',
			'post_title'   => $this->get_title(),
			'post_author'  => $this->author,
		);
		$post_id = wp_insert_post( $args );
		if ( 0 === $post_id ) {
			return false;
		} else if ( null === $this->ID ) {
			$this->ID = $post_id;
		}

		$meta = array(
			'filter_action'  => $this->filter_action,
			'filter_author'  => $this->filter_author,
			'filter_context' => $this->filter_context,
			'alert_type'     => $this->alert_type,
			'alert_meta'     => $this->alert_meta,
		);
		foreach ( $meta as $key => $value ) {
			$this->update_meta( $key, $value );
		}

		return true;
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
	 * @param string $meta_key (optional)
	 * @param bool   $single (optional)
	 *
	 * @return array
	 */
	public function get_meta( $meta_key = '', $single = false ) {
		return maybe_unserialize( get_post_meta( $this->ID, $meta_key, $single ) );
	}

	/**
	 * Update record meta
	 *
	 * @param string $meta_key
	 * @param string $meta_value
	 * @param string $prev_value (optional)
	 *
	 * @return array
	 */
	public function update_meta( $meta_key, $meta_value, $prev_value = '' ) {
		return update_post_meta( $this->ID, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Determine the title of the alert.
	 * @todo enhance human readibility
	 * @return string   The title of the alert
	 */
	function get_title() {
		$format = __( '%1$s when %2$s %3$s in %4$s', 'stream' );
		return sprintf(
			$format,
			ucfirst( $this->alert_type ),
			ucfirst( $this->filter_author ),
			$this->filter_action,
			ucfirst( $this->filter_context )
		);
	}
}
