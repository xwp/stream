<?php

class WP_Stream_Connectors {

	/**
	 * Connectors registered
	 * @var array
	 */
	public static $connectors = array();

	/**
	 * Contexts registered to Connectors
	 * @var array
	 */
	public static $contexts = array();

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

		$connectors = array(
			'comments',
			'editor',
			'installer',
			'media',
			'menus',
			'posts',
			'settings',
			'taxonomies',
			'users',
			'widgets',
		);

		if ( is_network_admin() ) {
			$connectors[] = 'blogs';
		}

		$classes = array();
		foreach ( $connectors as $connector ) {
			include_once WP_STREAM_DIR . '/connectors/' . $connector .'.php';
			$class     = "WP_Stream_Connector_$connector";
			$classes[] = $class;
		}

		/**
		 * Filter allows for adding additional connectors via classes that extend
		 * WP_Stream_Connector
		 *
		 * @param  array  Connector Class names
		 * @return array  Updated Array of Connector Class names
		 */
		self::$connectors = apply_filters( 'wp_stream_connectors', $classes );

		foreach ( self::$connectors as $connector ) {
			self::$term_labels['stream_connector'][ $connector::$name ] = $connector::get_label();
		}

		// Get excluded connectors
		$excluded_connectors = array();

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

			$connector::register();

			// Link context labels to their connector
			self::$contexts[ $connector::$name ] = $connector::get_context_labels();

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
}
