<?php
namespace WP_Stream;

class Record {
	public $ID;
	public $site_id;
	public $blog_id;
	public $object_id;
	public $author;
	public $author_role;
	public $summary;
	public $visibility;
	public $type;
	public $connector;
	public $context;
	public $action;
	public $created;
	public $ip;

	public $stream_meta;

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

		return $this->plugin->db->store( (array) $this );
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

	public function get_stream_meta( $meta_key = '', $single = false ) {
		if ( isset( $this->stream_meta->$meta_key ) ) {
			$stream_meta = $this->stream_meta->$meta_key;
		} else {
			return '';
		}

		if ( $single ) {
			return $stream_meta;
		}

		return array( $stream_meta );
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

		if ( isset( $this->$stream_meta->post_title ) && ! empty( $this->$stream_meta->post_title ) ) {
			$output = (string) $this->$stream_meta->post_title;
		} elseif ( isset( $this->$stream_meta->display_name ) && ! empty( $this->$stream_meta->display_name ) ) {
			$output = (string) $this->$stream_meta->display_name;
		} elseif ( isset( $this->$stream_meta->name ) && ! empty( $this->$stream_meta->name ) ) {
			$output = (string) $this->$stream_meta->name;
		}

		return $output;
	}
}
