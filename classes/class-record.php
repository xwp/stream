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

	public $stream_meta;

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
}
