<?php

class XT_Filter_Input {

	public static $filter_callbacks = array(
		FILTER_DEFAULT          => null,
		FILTER_VALIDATE_BOOLEAN => 'is_bool',
		FILTER_VALIDATE_EMAIL   => 'is_email',
		FILTER_VALIDATE_FLOAT   => 'is_float',
		FILTER_VALIDATE_INT     => 'is_int',
		FILTER_VALIDATE_IP      => array( 'XT_Filter_Input', 'is_ip_address' ),
		FILTER_VALIDATE_REGEXP  => array( 'XT_Filter_Input', 'is_regex' ),
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

	public static function super( $type, $variable_name, $filter = null, array $options = array() ) {
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

		$var = self::filter( $var, $filter, $options );

		return $var;
	}

	public static function filter( $var, $filter = null, array $options = array() ) {
		if ( $filter && $filter != FILTER_DEFAULT ) {
			$filter_callback = self::$filter_callbacks[ $filter ];
			$result = call_user_func( $filter_callback, $var );

			// filter_var / filter_input treats validation/sanitization filters the same
			// they both return output and change the var value, this shouldn't be the case here.
			// We'll do a boolean check on validation function, and let sanitizers change the value
			if ( $filter < 500 ) { // Validation functions
				if ( ! $result ) {
					$var = null;
				}
			} else { // Santization functions
				$var = $result;
			}
		}

		if ( false === $var ) {
			$var = null;
		}

		// Polyfill the `default` attribute only, for now.
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

function xt_filter_input( $type, $variable_name, $filter = null, array $options = array() ) {
	return call_user_func_array( array( 'XT_Filter_Input', 'super' ), func_get_args() );
}

function xt_filter_var( $var, $filter = null, array $options = array() ) {
	return call_user_func_array( array( 'XT_Filter_Input', 'filter' ), func_get_args() );
}