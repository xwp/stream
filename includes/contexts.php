<?php

class X_Stream_Contexts {

	/**
	 * Contexts registered
	 * @var array
	 */
	public static $contexts         = array();

	/**
	 * Context Taxonomy
	 * @var string
	 */
	public static $context_taxonomy = 'stream_context';

	/**
	 * Actions taxonomy
	 * @var string
	 */
	public static $action_taxonomy  = 'stream_action';

	public static function load() {
		require_once X_STREAM_CLASS_DIR . 'context.php';
		$contexts = array();
		if ( $found = glob( X_STREAM_DIR . 'contexts/*.php' ) ) {
			foreach ( $found as $context ) {
				include_once $context;
				$class = ucwords( preg_match( '#(.+)\.php#', basename( $context ), $matches ) ? $matches[1] : '' );
				$contexts[] = "X_Stream_Context_$class";
			}
		}
		self::$contexts = apply_filters( 'wp_stream_contexts', $contexts );

		foreach ( self::$contexts as $context ) {
			$context::register();
		}
	}

}