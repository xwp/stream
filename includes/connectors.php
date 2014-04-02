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
	 * Admin notice messages
	 *
	 * @since 1.2.3
	 * @var array
	 */
	protected static $admin_notices = array();

	/**
	 * Load built-in connectors
	 */
	public static function load() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );

		require_once WP_STREAM_CLASS_DIR . 'connector.php';

		$classes = array();
		if ( $found = glob( WP_STREAM_DIR . 'connectors/*.php' ) ) {
			foreach ( $found as $class ) {
				include_once $class;
				$class_name = ucwords( preg_match( '#(.+)\.php#', basename( $class ), $matches ) ? $matches[1] : '' );
				$class      = "WP_Stream_Connector_$class_name";
				$classes[]  = $class;
			}
		}

		$exclude_all_connector = false;

		// Check if logging action is enable for user or provide a hook for plugin to override on specific cases
		if ( ! self::is_logging_enabled_for_user() ) {
			$exclude_all_connector = true;
		}

		// Check if logging action is enable for ip or provide a hook for plugin to override on specific cases
		if ( ! self::is_logging_enabled_for_ip() ) {
			$exclude_all_connector = true;
		}

		/**
		 * Filter allows for adding additional connectors via classes that extend
		 * WP_Stream_Connector
		 *
		 * @param  array  Connector Class names
		 * @return array  Updated Array of Connector Class names
		 */
		self::$connectors = apply_filters( 'wp_stream_connectors', $classes );

		foreach ( $classes as $class ) {
			self::$term_labels['stream_connector'][ $class::$name ] = $class::get_label();
		}

		// Get excluded connectors
		$excluded_connectors = WP_Stream_Settings::get_excluded_by_key( 'connectors' );

		foreach ( self::$connectors as $connector ) {
			// Check if the connectors extends the WP_Stream_Connector class, if not skip it
			if ( ! is_subclass_of( $connector, 'WP_Stream_Connector' ) ) {
				self::$admin_notices[] = sprintf(
					__( "%s class wasn't loaded because it doesn't extends the %s class.", 'stream' ),
					$connector,
					'WP_Stream_Connector'
				);

				continue;
			}

			// Store connector label
			if ( ! in_array( $connector::$name, self::$term_labels['stream_connector'] ) ) {
				self::$term_labels['stream_connector'][ $connector::$name ] = $connector::get_label();
			}

			/**
			 * Filter allows to continue register excluded connector
			 *
			 * @param boolean TRUE if exclude otherwise false
			 * @param string connector unique name
			 * @param array Excluded connector array
			 */

			$is_excluded_connector = apply_filters( 'wp_stream_check_connector_is_excluded', in_array( $connector::$name, $excluded_connectors ), $connector::$name, $excluded_connectors );

			if ( $is_excluded_connector ) {
				continue;
			}

			if ( ! $exclude_all_connector ) {
				$connector::register();
			}

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

		/**
		 * This allow to perform action after all connectors registration
		 *
		 * @param array all register connectors labels array
		 */
		do_action( 'wp_stream_after_connectors_registration', self::$term_labels['stream_connector'] );
	}


	/**
	 * Print admin notices
	 *
	 * @since 1.2.3
	 */
	public static function admin_notices() {
		if ( ! empty( self::$admin_notices ) ) :
			?>
			<div class="error">
				<?php foreach ( self::$admin_notices as $message ) : ?>
					<?php echo wpautop( esc_html( $message ) ) // xss ok ?>
				<?php endforeach; ?>
			</div>
			<?php
		endif;
	}

	/**
	 * Check if we need to record action for specific users
	 *
	 * @param null $user
	 *
	 * @return mixed|void
	 */
	public static function is_logging_enabled_for_user( $user = null ) {
		if ( is_null( $user ) ) {
			$user = wp_get_current_user();
		}

		// If the user is not a valid user then we log action
		if ( ! ( $user instanceof WP_User ) || 0 === $user->ID ) {
			$bool = true;
		} else {
			// If a user is part of a role that we don't want to log, we disable it
			$user_roles   = array_values( $user->roles );
			$roles_logged = WP_Stream_Settings::get_excluded_by_key( 'authors_and_roles' );
			$bool         = ( 0 === count( array_intersect( $user_roles, $roles_logged ) ) );
			//Check user id in exclude array
			if ( $bool ) {
				$bool = ! ( in_array( $user->ID, $roles_logged ) );
			}
		}

		/**
		 * Filter sets boolean result value for this method
		 *
		 * @param      bool
		 * @param  obj $user         Current user object
		 * @param      string        Current class name
		 *
		 * @return bool
		 */
		return apply_filters( 'wp_stream_record_log', $bool, $user, get_called_class() );
	}

	/**
	 * Check if we need to record action for IP
	 *
	 * @param null $ip
	 *
	 * @return mixed|void
	 */
	public static function is_logging_enabled_for_ip( $ip = null ) {
		if ( is_null( $ip ) ) {
			$ip = wp_stream_filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
		} else {
			$ip = wp_stream_filter_var( $ip, FILTER_VALIDATE_IP );
		}

		// If ip is not valid the we will log the action
		if ( false === $ip ) {
			$bool = true;
		} else {
			$bool = self::is_logging_enabled( 'ip_addresses', $ip );
		}

		/**
		 * Filter to exclude actions of a specific ip from being logged
		 *
		 * @param         bool      True if logging is enable else false
		 * @param  string $ip       Current user ip address
		 * @param         string    Current class name
		 *
		 * @return bool
		 */
		return apply_filters( 'wp_stream_ip_record_log', $bool, $ip, get_called_class() );
	}

	/**
	 * This function is use to check whether logging is enabled
	 *
	 * @param $column string name of the setting key (actions|ip_addresses|contexts|connectors)
	 * @param $value string to check in excluded array
	 * @return array
	 */
	public static function is_logging_enabled( $column, $value ) {
		$excluded_values = WP_Stream_Settings::get_excluded_by_key( $column );
		$bool            = ( ! in_array( $value, $excluded_values ) );

		return $bool;
	}
}
