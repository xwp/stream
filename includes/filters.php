<?php

class WP_Stream_Filter_Input {

	public static $filter_callbacks = array(
		FILTER_DEFAULT                     => null,
		// Validate
		FILTER_VALIDATE_BOOLEAN            => 'is_bool',
		FILTER_VALIDATE_EMAIL              => 'is_email',
		FILTER_VALIDATE_FLOAT              => 'is_float',
		FILTER_VALIDATE_INT                => 'is_int',
		FILTER_VALIDATE_IP                 => array( 'WP_Stream_Filter_Input', 'is_ip_address' ),
		FILTER_VALIDATE_REGEXP             => array( 'WP_Stream_Filter_Input', 'is_regex' ),
		FILTER_VALIDATE_URL                => 'wp_http_validate_url',
		// Sanitize
		FILTER_SANITIZE_EMAIL              => 'sanitize_email',
		FILTER_SANITIZE_ENCODED            => 'esc_url_raw',
		FILTER_SANITIZE_NUMBER_FLOAT       => 'floatval',
		FILTER_SANITIZE_NUMBER_INT         => 'intval',
		FILTER_SANITIZE_SPECIAL_CHARS      => 'htmlspecialchars',
		FILTER_SANITIZE_STRING             => 'sanitize_text_field',
		FILTER_SANITIZE_URL                => 'esc_url_raw',
		// Other
		FILTER_UNSAFE_RAW                  => null,
	);

	public static function super( $type, $variable_name, $filter = null, $options = array() ) {
		$super = null;

		switch ( $type ) {
			case INPUT_POST :
				$super = $_POST;
				break;
			case INPUT_GET :
				$super = $_GET;
				break;
			case INPUT_COOKIE :
				$super = $_COOKIE;
				break;
			case INPUT_ENV :
				$super = $_ENV;
				break;
			case INPUT_SERVER :
				$super = $_SERVER;
				break;
		}

		if ( is_null( $super ) ) {
			throw new Exception( __( 'Invalid use, type must be one of INPUT_* family.', 'stream' ) );
		}

		$var = isset( $super[ $variable_name ] ) ? $super[ $variable_name ] : null;
		$var = self::filter( $var, $filter, $options );

		return $var;
	}

	public static function filter( $var, $filter = null, $options = array() ) {
		if ( $filter && FILTER_DEFAULT !== $filter ) {
			if ( ! isset( self::$filter_callbacks[ $filter ] ) ) {
				throw new Exception( __( 'Filter not supported.', 'stream' ) );
			}

			$filter_callback = self::$filter_callbacks[ $filter ];
			$result          = call_user_func( $filter_callback, $var );

			// filter_var / filter_input treats validation/sanitization filters the same
			// they both return output and change the var value, this shouldn't be the case here.
			// We'll do a boolean check on validation function, and let sanitizers change the value
			if ( $filter < 500 ) { // Validation functions
				if ( ! $result ) {
					$var = false;
				}
			} else { // Santization functions
				$var = $result;
			}
		}

		// Detect FILTER_REQUIRE_ARRAY flag
		if ( is_int( $options ) && FILTER_REQUIRE_ARRAY === $options ) {
			if ( ! is_array( $var ) ) {
				$var = false;
			}
		}

		// Polyfill the `default` attribute only, for now.
		if ( is_array( $options ) && ! empty( $options['options']['default'] ) ) {
			if ( false === $var || is_null( $var ) ) {
				return $options['options']['default'];
			}
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

function wp_stream_filter_input( $type, $variable_name, $filter = null, $options = array() ) {
	return call_user_func_array( array( 'WP_Stream_Filter_Input', 'super' ), func_get_args() );
}

function wp_stream_filter_var( $var, $filter = null, $options = array() ) {
	return call_user_func_array( array( 'WP_Stream_Filter_Input', 'filter' ), func_get_args() );
}
