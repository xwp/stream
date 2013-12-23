<?php

class WP_Stream_Notification_Rule {

	function __construct( $id = null ) {
		if ( $id ) {
			$this->load( $id );
		}
	}

	function load( $id ) {

	}

	function load_from_array( array $arr ) {
		
		return $this;
	}

	function exists() {
		return (bool) $this->id;
	}

	function save() {
		{echo '<pre>';var_dump($_POST);echo '</pre>';die();}
	}

	function __get( $key ) {
		return 'sample';
	}
}