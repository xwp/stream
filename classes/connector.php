<?php

abstract class WP_Stream_Connector {

	/**
	* Name/slug of the context
	* @var string
	*/
	public static $name = null;

	/**
	* Actions this context is hooked to
	* @var array
	*/
	public static $actions = array();

	/**
	* Previous Stream entry in same request
	* @var int
	*/
	public static $prev_stream = null;

	/**
	 * Register all context hooks
	 * 
	 * @return void
	 */
	public static function register() {
		$class = get_called_class();

		foreach ( $class::$actions as $action ) {
			add_action( $action, array( $class, 'callback' ), null, 5 );
		}

		add_filter( 'wp_stream_action_links_' . $class::$name, array( $class, 'action_links' ), 10, 3 );
	}

	/**
	 * Callback for all registered hooks throughout Stream
	 * Looks for a class method with the convention: "callback_{action name}"
	 * 
	 * @return void
	 */
	public static function callback() {
		$action   = current_filter();
		$class    = get_called_class();
		$callback = array( $class, 'callback_' . $action );
		if ( is_callable( $callback ) ) {
			return call_user_func_array( $callback, func_get_args() );
		}
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{context}
	 * @param  array $links      Previous links registered
	 * @param  int   $stream_id  Stream drop id
	 * @param  int   $object_id  Object id
	 * @return array             Associative array, eg: ( label => href )
	 */
	public static function action_links( $links, $stream_id, $object_id ) {
		return $links;
	}

	/**
	 * Log handler
	 * @param  string $message   sprintf-ready error message string
	 * @param  array  $args      sprintf (and extra) arguments to use
	 * @param  int    $object_id Target object id
	 * @param  string $action    Action performed (stream_action)
	 * @param  int    $user_id   User responsible for the action
	 * @param  array  $contexts  Contexts of the action
	 * @return void
	 */
	public static function log( $message, $args, $object_id, $contexts, $user_id = null ) {
		$class = get_called_class();
		return WP_Stream_Log::get_instance()->log(
			$class::$name,
			$message,
			$args,
			$object_id,
			$contexts,
			$user_id
			);
	}

}