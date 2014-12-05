<?php

class WP_Stream_Connectors {

	/**
	 * Connectors registered
	 *
	 * @var array
	 */
	public static $connectors = array();

	/**
	 * Contexts registered to Connectors
	 *
	 * @var array
	 */
	public static $contexts = array();

	/**
	 * Action taxonomy terms
	 * Holds slug to localized label association
	 *
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
			// Core
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

			// Extras
			'acf',
			'bbpress',
			'buddypress',
			'edd',
			'gravityforms',
			'jetpack',
			'stream',
			'woocommerce',
			'wordpress-seo',
		);

		$classes = array();
		foreach ( $connectors as $connector ) {
			include_once WP_STREAM_DIR . '/connectors/class-wp-stream-connector-' . $connector .'.php';
			$class = sprintf( 'WP_Stream_Connector_%s', str_replace( '-', '_', $connector ) );
			if ( $class::is_dependency_satisfied() ) {
				$classes[] = $class;
			}
		}

		/**
		 * Allows for adding additional connectors via classes that extend WP_Stream_Connector.
		 *
		 * @since 0.0.2
		 *
		 * @param array $classes An array of connector class names.
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

			$connector_name = $connector::$name;
			$is_excluded    = in_array( $connector_name, $excluded_connectors );

			/**
			 * Allows excluded connectors to be overridden and registered.
			 *
			 * @since 1.3.0
			 *
			 * @param bool   $is_excluded         True if excluded, otherwise false.
			 * @param string $connector           The current connector's slug.
			 * @param array  $excluded_connectors An array of all excluded connector slugs.
			 */
			$is_excluded_connector = apply_filters( 'wp_stream_check_connector_is_excluded', $is_excluded, $connector_name, $excluded_connectors );

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

		$connectors = self::$term_labels['stream_connector'];

		/**
		 * Fires after all connectors have been registered.
		 *
		 * @since 1.3.0
		 *
		 * @param array all register connectors labels array
		 */
		do_action( 'wp_stream_after_connectors_registration', $connectors );
	}

	/**
	 * Print admin notices
	 *
	 * @since  1.2.3
	 *
	 * @return void
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
