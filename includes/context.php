<?php

abstract class X_Stream_Context {

	/**
	 * Name/slug of the context
	 * @var string
	 */
	static $name = null;

	/**
	 * Label of the context
	 * @var string
	 */
	static $label = '';

	/**
	 * Actions this context is hooked to
	 * @var array
	 */
	static $actions = array();

	/**
	 * Register required hooks
	 * @return array   Array of actions to hook into
	 */
	static function register() {
		foreach ( self::$actions as $action ) {
			add_action( $action, array( __CLASS__, 'callback' ) );
		}
	}

	static function callback() {
		$action   = current_filter();
		$callback = array( __CLASS__, 'callback_' . $action );
		if ( is_callable( $callback ) ) {
			call_user_func_array( $callback, func_get_args() );
		}
	}

	static function log( $message, $args, $tags, $object_id, $user_id = null ) {
		// DO SOMETHING
		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		var_dump(
			array(
				$message,
				$args,
				$tags,
				$object_id,
				static::$name,
			)
			);
		die();
	}

}