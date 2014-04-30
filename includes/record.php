<?php

class WP_Stream_Record
{

	public $ID;
	public $site_id;
	public $blog_id;
	public $object_id;
	public $author;
	public $author_role;
	public $summary;
	public $visibility;
	public $parent;
	public $type;
	public $created;
	public $ip;

	public $meta;
	public $contexts;
	public $connector;

	public function __construct( $id = null ) {
		if ( $id ) {
			$this->load( $id );
		}
	}

	public function load( $id ) {
		$records = WP_Stream::get_instance()->db->query( array( 'id' => $id ) );

		if ( $record ) {
			$this->populate( $record );
		}
	}

	public function save() {
		if ( ! $this->validate() ) {
			return new WP_Error( 'validation-error', __( 'Could not validate record data.', 'stream' ) );
		}

		return WP_Stream::get_instance()->db->store( (array) $this );
	}

	public function populate( array $data ) {
		$keys = get_class_vars( self );
		$data = array_intersect_key( $raw, $valid );
		foreach ( $data as $key => $val ) {
			$this->{$key} = $val;
		}
	}

	public function validate() {
		return true;
	}

}
