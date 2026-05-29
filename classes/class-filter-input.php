<?php
/**
 * Processes form input
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Filter_Input
 */
class Filter_Input {

	/**
	 * Callbacks to be used for input validation/sanitation.
	 *
	 * @var array
	 */
	public static $filter_callbacks = array(
		FILTER_DEFAULT                     => null,
		// Validate.
		FILTER_VALIDATE_BOOLEAN            => 'is_bool',
		FILTER_VALIDATE_EMAIL              => 'is_email',
		FILTER_VALIDATE_FLOAT              => 'is_float',
		FILTER_VALIDATE_INT                => 'is_int',
		FILTER_VALIDATE_IP                 => array( __CLASS__, 'is_ip_address' ),
		FILTER_VALIDATE_REGEXP             => array( __CLASS__, 'is_regex' ),
		FILTER_VALIDATE_URL                => 'wp_http_validate_url',
		// Sanitize.
		FILTER_SANITIZE_EMAIL              => 'sanitize_email',
		FILTER_SANITIZE_ENCODED            => 'esc_url_raw',
		FILTER_SANITIZE_NUMBER_FLOAT       => 'floatval',
		FILTER_SANITIZE_NUMBER_INT         => 'intval',
		FILTER_SANITIZE_SPECIAL_CHARS      => 'htmlspecialchars',
		FILTER_SANITIZE_FULL_SPECIAL_CHARS => 'sanitize_text_field',
		FILTER_SANITIZE_URL                => 'esc_url_raw',
		// Other.
		FILTER_UNSAFE_RAW                  => null,
	);

	/**
	 * Returns input variable
	 *
	 * @param int    $type           Input type.
	 * @param string $variable_name  Variable key.
	 * @param int    $filter         Filter callback.
	 * @param array  $options        Filter callback parameters.
	 * @throws \Exception  Invalid input type provided.
	 * @return mixed
	 */
	public static function super( $type, $variable_name, $filter = null, $options = array() ) {
		$super = null;

		// @codingStandardsIgnoreStart
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
		// @codingStandardsIgnoreEnd

		if ( is_null( $super ) ) {
			throw new \Exception( esc_html__( 'Invalid use, type must be one of INPUT_* family.', 'stream' ) );
		}

		$value = isset( $super[ $variable_name ] ) ? $super[ $variable_name ] : null;
		$value = self::filter( $value, $filter, $options );

		return $value;
	}

	/**
	 * Sanitize or validate input.
	 *
	 * @param mixed $value   Raw input value.
	 * @param int   $filter  Filter callback.
	 * @param array $options Filter callback parameters.
	 *
	 * @return mixed
	 * @throws \Exception Unsupported filter provided.
	 */
	public static function filter( $value, $filter = null, $options = array() ) {
		// Default filter is a sanitizer, not validator.
		$filter_type = 'sanitizer';

		// Only filter value if it is not null.
		if ( isset( $value ) && $filter && FILTER_DEFAULT !== $filter ) {
			if ( ! isset( self::$filter_callbacks[ $filter ] ) ) {
				throw new \Exception( esc_html__( 'Filter not supported.', 'stream' ) );
			}

			$filter_callback = self::$filter_callbacks[ $filter ];
			$result          = call_user_func( $filter_callback, $value );

			/**
			 * "filter_var / filter_input" treats validation/sanitization filters the same
			 * they both return output and change the var value, this shouldn't be the case here.
			 * We'll do a boolean check on validation function, and let sanitizers change the value
			 */
			$filter_type = ( $filter < 500 ) ? 'validator' : 'sanitizer';
			if ( 'validator' === $filter_type ) { // Validation functions.
				if ( ! $result ) {
					$value = false;
				}
			} else { // Santization functions.
				$value = $result;
			}
		}

		// Detect FILTER_REQUIRE_ARRAY flag.
		if ( isset( $value ) && is_int( $options ) && FILTER_REQUIRE_ARRAY === $options ) {
			if ( ! is_array( $value ) ) {
				$value = ( 'validator' === $filter_type ) ? false : null;
			}
		}

		// Polyfill the `default` attribute only, for now.
		if ( is_array( $options ) && ! empty( $options['options']['default'] ) ) {
			if ( 'validator' === $filter_type && false === $value ) {
				$value = $options['options']['default'];
			} elseif ( 'sanitizer' === $filter_type && null === $value ) {
				$value = $options['options']['default'];
			}
		}

		return $value;
	}

	/**
	 * Returns whether the variable is a Regular Expression or not?
	 *
	 * @param string $maybe_regex Raw input value.
	 *
	 * @return boolean
	 */
	public static function is_regex( $maybe_regex ) {
		$test = @preg_match( $maybe_regex, '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return false !== $test;
	}

	/**
	 * Returns whether the variable is an IP address or not?
	 *
	 * @param string $maybe_ip Raw input.
	 *
	 * @return boolean
	 */
	public static function is_ip_address( $maybe_ip ) {
		return false !== \WP_Http::is_ip_address( $maybe_ip );
	}
}
