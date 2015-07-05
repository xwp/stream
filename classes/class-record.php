<?php
namespace WP_Stream;

class Record {
	/**
	 * Hold Plugin class
	 * @var Plugin
	 */
	public $plugin;

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

	public $meta;

	public function __construct( $plugin, $id = null ) {
		$this->plugin = $plugin;

		if ( $id ) {
			$records = $this->plugin->db->query->query( array( 'id' => $id ) );
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

	public function get_meta( $meta_key = '', $single = false ) {
		if ( isset( $this->meta->$meta_key ) ) {
			$meta = $this->meta->$meta_key;
		} else {
			return '';
		}

		if ( $single ) {
			return $meta;
		} else {
			return array( $meta );
		}
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
