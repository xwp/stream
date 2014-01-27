<?php

abstract class WP_Stream_Notification_Adapter {

	public $params = array();

	public static function register( $title ) {
		$class = get_called_class();
		$name  = strtolower( str_replace( 'WP_Stream_Notification_Adapter_', '', $class ) );
		WP_Stream_Notifications::register_adapter( $class, $name, $title );
	}

	public static function fields() {
		return array();
	}

	/**
	 * Replace placeholders in alert[field]s with proper info from the log
	 * @param  string $haystack Text to replace in
	 * @param  array  $log      Log array
	 * @return string
	 */
	public static function replace( $haystack, $log ) {
		if ( preg_match_all( '#%%([^%]+)%%#', $haystack, $placeholders ) ) {

			foreach ( $placeholders[1] as $placeholder ) {
				$value = false;
				switch ( $placeholder ) {
					case 'summary':
					case 'object_id':
					case 'author':
					case 'created':
						$value = $log[$placeholder];
						break;
					case ( strpos( $placeholder, 'meta.' ) !== false ):
						$meta_key = substr( $placeholder, 5 );
						if ( isset( $log['meta'][ $meta_key ] ) ) {
							$value = $log['meta'][ $meta_key ];
						}
						break;
					case ( strpos( $placeholder, 'author.' ) !== false ):
						$meta_key = substr( $placeholder, 7 );
						$author = get_userdata( $log['author'] );
						if ( $author && isset( $author->{$meta_key} ) ) {
							$value = $author->{$meta_key};
						}
						break;
					// TODO Move this part to Stream base, and abstract it
					case ( strpos( $placeholder, 'object.' ) !== false ):
						$meta_key = substr( $placeholder, 7 );
						$context = key( $log['contexts'] );
						// can only guess the object type, since there is no
						// actual reference here
						switch ( $context ) {
							case 'post':
								$object = get_post( $log['object_id'] );
								break;
							case 'user':
								$object = get_userdata( $log['object_id'] );
								break;
							// TODO Add the rest of objects, ie: comments, terms, etc
						}
						if ( isset( $object->{$meta_key} ) ) {
							$value = $object->{$meta_key};
						}
						break;
				}
				if ( $value ) {
					$haystack = str_replace( "%%$placeholder%%", $value, $haystack );
				}
			}
		}
		return $haystack;
	}

	function load( $alert ) {
		$params = array();
		$fields = $this::fields();
		foreach ( $fields as $field => $options ) {
			$params[ $field ] = isset( $alert[ $field ] )
				? $alert[ $field ]
				: null;
		}
		$this->params = $params;
		return $this;
	}

	abstract function send( $log );

}
