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
				$class     = ucwords( preg_match( '#(.+)\.php#', basename( $class ), $matches ) ? $matches[1] : '' );
				$classes[] = "WP_Stream_Connector_$class";
			}
		}

		/**
		 * Filter allows for adding additional connectors via classes that extend
		 * WP_Stream_Connector
		 *
		 * @param  array  Connector Class names
		 * @return array  Updated Array of Connector Class names
		 */
		self::$connectors = apply_filters( 'wp_stream_connectors', $classes );

		// Get active connectors
		$active_connectors = WP_Stream_Settings::get_active_connectors();

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

			self::$term_labels['stream_connector'][$connector::$name] = $connector::get_label();
			if ( ! in_array( $connector::$name, $active_connectors ) ) {
				continue;
			}

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
					<?php echo wpautop( esc_html( $message ) ); // xss ok ?>
				<?php endforeach; ?>
			</div>
			<?php
		endif;
	}
}
