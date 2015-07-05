<?php
namespace WP_Stream;

class Record {
	public $ID;
	public $site_id;
	public $blog_id;
	public $object_id;
	public $user;
	public $user_role;
	public $user_meta;
	public $summary;
	public $connector;
	public $context;
	public $action;
	public $ip;
	public $meta;

	public function __construct( $id = null ) {
		if ( $id ) {
			$records = wp_stream_get_instance()->db->query->query( array( 'id' => $id ) );
			if ( isset( $records[0] ) ) {
				$this->populate( $records[0] );
			}
		}
	}

	public function save() {
		if ( ! $this->validate() ) {
			return new \WP_Error( 'validation-error', esc_html__( 'Could not validate record data.', 'stream' ) );
		}

		return wp_stream_get_instance()->db->insert( (array) $this );
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
		return maybe_unserialize( get_metadata( 'record', $this->ID, $meta_key, $single ) );
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
		return update_metadata( 'record', $this->ID, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Determine the title of an object that a record is for.
	 *
	 * @param  object  Record object
	 * @return mixed   The title of the object as a string, otherwise false
	 */
	function get_object_title() {
		if ( ! isset( $this->object_id ) || empty( $this->object_id ) ) {
			return false;
		}

		$output = false;

		if ( isset( $this->meta->post_title ) && ! empty( $this->meta->post_title ) ) {
			$output = (string) $this->meta->post_title;
		} elseif ( isset( $this->meta->display_name ) && ! empty( $this->meta->display_name ) ) {
			$output = (string) $this->meta->display_name;
		} elseif ( isset( $this->meta->name ) && ! empty( $this->meta->name ) ) {
			$output = (string) $this->meta->name;
		}

		return $output;
	}
}
