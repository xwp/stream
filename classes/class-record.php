<?php
namespace WP_Stream;

class Record {
	public $ID;
	public $created;
	public $site_id;
	public $blog_id;
	public $object_id;
	public $user_id;
	public $user_role;
	public $user_meta;
	public $summary;
	public $connector;
	public $context;
	public $action;
	public $ip;
	public $meta;

	public function __construct( $item ) {
		$this->ID = isset( $item->ID ) ? $item->ID : null;
		$this->created = isset( $item->created ) ? $item->created : null;
		$this->site_id = isset( $item->site_id ) ? $item->site_id : null;
		$this->blog_id = isset( $item->blog_id ) ? $item->blog_id : null;
		$this->object_id = isset( $item->object_id ) ? $item->object_id : null;
		$this->user_id = isset( $item->user_id ) ? $item->user_id : null;
		$this->user_role = isset( $item->user_role ) ? $item->user_role : null;
		$this->user_meta = isset( $item->meta['user_meta'] ) ? $item->meta['user_meta'] : null;
		$this->summary = isset( $item->summary ) ? $item->summary : null;
		$this->connector = isset( $item->connector ) ? $item->connector : null;
		$this->context = isset( $item->context ) ? $item->context : null;
		$this->action = isset( $item->action ) ? $item->action : null;
		$this->ip = isset( $item->ip ) ? $item->ip : null;
		$this->meta = isset( $item->meta ) ? $item->meta : null;

		if ( isset( $this->meta['user_meta'] ) ) {
			unset( $this->meta['user_meta'] );
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
