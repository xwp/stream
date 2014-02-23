<?php

class WP_Stream_Notification_Rule {

	private $ID;
	private $author;
	private $summary;
	private $visibility;
	private $created;

	private $type = 'notification_rule';

	private $triggers = array();
	private $groups   = array();
	private $alerts   = array();

	function __construct( $id = null ) {
		if ( $id ) {
			$this->load( $id );
		}
	}

	function load( $id ) {
		global $wpdb;
		$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->stream WHERE type = 'notification_rule' AND ID = %d", $id ) ); // cache ok, db call ok
		if ( $item ) {
			$meta = get_option( 'stream_notifications_' . $item->ID );
			if ( ! $meta || ! is_array( $meta ) ) {
				$meta = array();
			}
			$this->load_from_array( array_merge( (array) $item, $meta ) );
		}
		return $this;
	}

	function load_from_array( array $arr ) {
		$keys = array( 'ID', 'author', 'summary', 'visibility', 'type', 'created', 'triggers', 'groups', 'alerts', );
		foreach ( $keys as $key ) {
			if ( isset( $arr[$key] ) ) {
				$this->{$key} = $arr[$key];
			}
		}
		return $this;
	}

	function exists() {
		return (bool) $this->ID;
	}

	function save() {
		global $wpdb;

		$defaults = array(
			'ID'         => null,
			'author'     => wp_get_current_user()->ID,
			'summary'    => null,
			'visibility' => 'inactive',
			'type'       => 'notfication_rule',
			'created'    => current_time( 'mysql', 1 ),
		);

		$data   = $this->to_array();
		$record = array_intersect_key( $data, $defaults );

		if ( $this->exists() ) {
			$before = new WP_Stream_Notification_Rule( $this->ID );

			// update `created` to current date if needed
			if ( $before->visibility === 'inactive' || $this->visibility === 'inactive' ) {
				$record['created'] = $defaults['created'];
			}

			$result  = $wpdb->update( $wpdb->stream, $record, array( 'ID' => $this->ID ) );  // cache ok, db call ok
			$success = ( false !== $result );
		} else {
			if ( ! $record['created'] ) {
				unset( $record['created'] );
			}
			$record  = wp_parse_args( $record, $defaults );
			$result  = $wpdb->insert( $wpdb->stream, $record );  // cache ok, db call ok
			$success = ( is_int( $result ) );
			if ( $success ) {
				$this->ID = $wpdb->insert_id; // cache ok, db call ok
			}
		}

		if ( $this->ID ) {
			$meta_keys = array( 'triggers', 'groups', 'alerts', );
			$meta      = array_intersect_key( $data, array_flip( $meta_keys ) );
			update_option( 'stream_notifications_'.$this->ID, $meta );
		}

		return $success;
	}

	function to_array() {
		$data = array();
		$keys = array( 'ID', 'author', 'summary', 'visibility', 'type', 'created', 'triggers', 'groups', 'alerts', );
		foreach ( $keys as $key ) {
			$data[$key] = $this->{$key};
		}
		return $data;
	}

	function __get( $key ) {
		switch ( $key ) {
			default:
				$r = $this->{$key};
		}
		return $r;
	}

	function __set( $key, $value ) {
		switch ( $key ) {
			default:
				$this->{$key} = $value;
				break;
		}
	}

}
