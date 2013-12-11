<?php

class WP_Stream_Connectors {

	/**
	 * Contexts registered
	 * @var array
	 */
	public static $connectors = array();

	/**
	 * Action taxonomy terms
	 * Holds slug to -localized- label association
	 * @var array
	 */
	public static $term_labels = array(
		'stream_connector' => array(),
		'stream_context'   => array(),
		'stream_action'    => array(),
	);

	/**
	 * Load built-in connectors
	 */
	public static function load() {
		require_once WP_STREAM_CLASS_DIR . 'connector.php';

		$classes = array();
		if ( $found = glob( WP_STREAM_DIR . 'connectors/*.php' ) ) {
			foreach ( $found as $class ) {
				include_once $class;
				$class     = ucwords( preg_match( '#(.+)\.php#', basename( $class ), $matches ) ? $matches[1] : '' );
				$classes[] = "WP_Stream_Connector_$class";
			}
		}
		self::$connectors = apply_filters( 'wp_stream_connectors', $classes );

		foreach ( self::$connectors as $connector ) {
			self::$term_labels['stream_connector'][$connector::$name] = $connector::get_label();
		}

		foreach ( self::$connectors as $connector ) {
			$connector::register();

			// Add new terms to our label lookup array
			self::$term_labels['stream_action']  = array_merge(
				self::$term_labels['stream_action'],
				$connector::get_action_labels()
			);
			self::$term_labels['stream_context'] = array_merge(
				self::$term_labels['stream_context'],
				$connector::get_context_labels()
			);
		}
	}

}