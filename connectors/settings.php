<?php

class WP_Stream_Connector_Settings extends WP_Stream_Connector {

	/**
	 * Context name
	 * @var string
	 */
	public static $name = 'settings';

	/**
	 * Actions registered for this context
	 * @var array
	 */
	public static $actions = array(
		'whitelist_options',
	);

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return __( 'Settings', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'updated' => __( 'Updated', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'settings' => __( 'Settings', 'stream' ),
		);
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_posts
	 * @param  array $links      Previous links registered
	 * @param  int   $stream_id  Stream drop id
	 * @param  int   $object_id  Object ( post ) id
	 * @return array             Action links
	 */
	public static function action_links( $links, $stream_id, $object_id ) {
		return $links;
	}

	/**
	 * Trigger this connector core tracker, only on options.php page
	 *
	 * @action whitelist_options
	 */
	public static function callback_whitelist_options( $options ) {
		add_action( 'updated_option', array( __CLASS__, 'callback' ), 10, 3 );

		return $options;
	}

	/**
	 * Track updated settings
	 *
	 * @action updated_option
	 */
	public static function callback_updated_option( $option, $old_value, $value ) {
		global $new_whitelist_options, $whitelist_options;
		$options = $whitelist_options + $new_whitelist_options;

		foreach ( $options as $key => $opts ) {
			if ( in_array( $option, $opts ) ) {
				$current_key = $key;
				break;
			}
		}

		if ( ! isset( $current_key ) ) {
			$current_key = 'settings';
		}

		self::log(
			__( '"%s" setting was updated', 'stream' ),
			compact( 'option', 'old_value', 'value' ),
			null,
			array(
				ucwords( $current_key ) => 'updated',
			)
		);
	}

}