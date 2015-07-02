<?php

class WP_Stream_Record {

	public $ID;
	public $site_id;
	public $blog_id;
	public $object_id;
	public $user_id;
	public $user_role;
	public $user_meta;
	public $summary;
	public $created;
	public $connector;
	public $context;
	public $action;
	public $ip;
	public $meta;

	public function __construct( $id = null ) {
		if ( $id ) {
			$this->load( $id );
		}
	}

	public function load( $id ) {
		$records = WP_Stream::$db->query( array( 'id' => $id ) );

		if ( $record ) {
			$this->populate( $record );
		}
	}

	public function save() {
		if ( ! $this->validate() ) {
			return new WP_Error( 'validation-error', esc_html__( 'Could not validate record data.', 'stream' ) );
		}

		return WP_Stream::$db->store( (array) $this );
	}

	public function populate( array $raw ) {
		$keys = get_class_vars( __CLASS__ );
		$data = array_intersect_key( $raw, $keys );

		foreach ( $data as $key => $val ) {
			$this->{$key} = $val;
		}
	}

	public function validate() {
		return true;
	}

	public static function instance( array $data ) {
		$object = new self();

		$object->populate( $data );

		return $object;
	}

}
