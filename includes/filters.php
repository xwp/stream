<?php

class XT_Filter_Input {

	public static $filter_callbacks = array(
		FILTER_DEFAULT          => null,
		FILTER_VALIDATE_BOOLEAN => 'is_bool',
		FILTER_VALIDATE_EMAIL   => 'is_email',
		FILTER_VALIDATE_FLOAT   => 'is_float',
		FILTER_VALIDATE_INT     => 'is_int',
		FILTER_VALIDATE_IP      => array( 'XT_Filter', 'is_ip_address' ),
		FILTER_VALIDATE_REGEXP  => array( 'XT_Filter', 'is_regex' ),
		FILTER_VALIDATE_URL     => 'wp_http_validate_url',

		FILTER_SANITIZE_EMAIL   => 'sanitize_email',
		FILTER_SANITIZE_ENCODED => 'esc_url_raw',
		FILTER_SANITIZE_NUMBER_FLOAT => 'floatval',
		FILTER_SANITIZE_NUMBER_INT => 'intval',
		FILTER_SANITIZE_SPECIAL_CHARS => 'htmlspecialchars',
		FILTER_SANITIZE_FULL_SPECIAL_CHARS => 'htmlspecialchars',
		FILTER_SANITIZE_STRING => 'sanitize_text_field',
		FILTER_SANITIZE_URL => 'esc_url_raw',
		FILTER_UNSAFE_RAW => null,
	);

	public function filter( $type, $variable_name, $filter = null, array $options = array() ) {
		switch ( $type ) {
			case INPUT_POST   : $super = $_POST; break;
			case INPUT_GET    : $super = $_GET; break;
			case INPUT_COOKIE : $super = $_COOKIE; break;
			case INPUT_ENV    : $super = $_ENV; break;
			case INPUT_SERVER : $super = $_SERVER; break;
		}

		if ( is_null( $super ) ) {
			throw new Exception( 'Invalid use, type must be one of INPUT_* family.' );
		}

		if ( ! isset( $super[ $variable_name ] ) ) {
			return;
		}

		$var = $super[ $variable_name ];

		if ( $filter && $filter != FILTER_DEFAULT ) {
			$filter_callback = self::$filter_callbacks[ $filter ];
			$var = call_user_func( $filter_callback, $var );
		}

		if ( false === $var ) {
			$var = null;
		}

		// Polyfill the default attribute only, for now.
		if ( ! empty( $options['options']['default'] ) && is_null( $var ) ) {
			return $options['options']['default'];
		}

		return $var;
	}

	public static function is_regex( $var ) {
		$test = @preg_match( $var, '' );
		return $test !== false;
	}

	public static function is_ip_address( $var ) {
		return false !== WP_Http::is_ip_address( $var );
	}

}

function xt_filter_input() {
	static $filter;
	$filter || $filter = new XT_Filter_Input;
	return call_user_func_array( array( $filter, 'filter' ), func_get_args() );
}
