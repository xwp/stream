<?php

/**
 * Class WP_Stream_Connector_ACF
 *
 * Tracking admin actions related to ACF plugin
 *
 * @author: Shady Sharaf <shady@x-team.com>
 */
class WP_Stream_Connector_ACF extends WP_Stream_Connector {

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public static $name = 'acf';

	/**
	 * Holds tracked plugin minimum version required
	 *
	 * @const string
	 */
	const PLUGIN_MIN_VERSION = '4.3.8';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public static $actions = array(
		'added_post_meta',
		'updated_post_meta',
		'delete_post_meta',
	);

	/**
	 * Cached location rules, used in shutdown callback to verify changes in meta
	 * @var array
	 */
	public static $cached_location_rules = array();

	/**
	 * Cached field values updates, used by shutdown callback to verify actual changes
	 * @var array
	 */
	public static $cached_field_values_updates = array();


	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public static function is_dependency_satisfied() {
		if ( class_exists( 'acf' ) && version_compare( acf()->settings['version'], self::PLUGIN_MIN_VERSION, '>=' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public static function get_label() {
		return __( 'ACF', 'acf' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'created'   => __( 'Created', 'stream' ),
			'updated'   => __( 'Updated', 'stream' ),
			'added'     => __( 'Added', 'stream' ),
			'deleted'   => __( 'Deleted', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'field_groups'  => _x( 'Field groups', 'acf', 'stream' ),
			'fields'        => _x( 'Fields', 'acf', 'stream' ),
			'rules'         => _x( 'Rules', 'acf', 'stream' ),
			'options'       => _x( 'Options', 'acf', 'stream' ),
			'values'        => _x( 'Values', 'acf', 'stream' ),
		);
	}

	/**
	 * Register the connector
	 */
	public static function register() {
		add_filter( 'wp_stream_log_data', array( __CLASS__, 'log_override' ) );

		/**
		 * Allow devs to disable logging values of rendered forms
		 * @return bool
		 */
		if ( apply_filters( 'wp_stream_acf_enable_value_logging', true ) ) {
			self::$actions[] = 'acf/update_value';
		}

		parent::register();
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param  array  $links  Previous links registered
	 * @param  object $record Stream record
	 *
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		$links = WP_Stream_Connector_Posts::action_links( $links, $record );
		return $links;
	}

	/**
	 * Track addition of post meta
	 * @action added_post_meta
	 */
	public static function callback_added_post_meta() {
		call_user_func_array( array( __CLASS__, 'check_post_meta' ), array_merge( array( 'added' ), func_get_args() ) );
	}

	/**
	 * Track updating post meta
	 * @action updated_post_meta
	 */
	public static function callback_updated_post_meta() {
		call_user_func_array( array( __CLASS__, 'check_post_meta' ), array_merge( array( 'updated' ), func_get_args() ) );
	}

	/**
	 * Track deletion of post meta
	 *
	 * Note: Using delete_post_meta instead of deleted_post_meta to be able to
	 * capture old field value
	 *
	 * @action delete_post_meta
	 */
	public static function callback_delete_post_meta() {
		call_user_func_array( array( __CLASS__, 'check_post_meta' ), array_merge( array( 'deleted' ), func_get_args() ) );
	}

	/**
	 * Track addition of post meta
	 * @action added_post_meta
	 */
	public static function check_post_meta( $action, $meta_id, $object_id, $meta_key, $meta_value = null ) {
		$post = get_post( $object_id );
		if ( ! $post || is_wp_error( $post ) ) {
			return;
		}

		if ( 'acf' !== $post->post_type ) {
			self::check_post_meta_values( $action, $meta_id, $object_id, $meta_key, $meta_value = null );
			return;
		}

		$action_labels = self::get_action_labels();

		// Fields
		if ( 0 === strpos( $meta_key, 'field_' ) ) {
			if ( 'deleted' === $action ) {
				$meta_value = get_post_meta( $object_id, $meta_key, true );
			}
			self::log(
				_x( '%1$s field in "$2$s" was %2$s', 'acf', 'stream' ),
				array(
					'label' => $meta_value['label'],
					'title' => $post->post_title,
					'action' => strtolower( $action_labels[ $action ] ),
					'key' => $meta_value['key'],
					'name' => $meta_value['name'],
				),
				$object_id,
				'fields',
				$action
			);
		}
		// Location rules
		elseif ( 'rule' === $meta_key ) {
			if ( 'deleted' === $action ) {
				self::$cached_location_rules[ $object_id ] = get_post_meta( $object_id, 'rule' );
				add_action( 'shutdown', array( __CLASS__, 'check_location_rules' ), 9 );
			}
		}
		// Position option
		elseif ( 'position' === $meta_key ) {
			if ( 'deleted' === $action ) {
				return;
			}
			$options = array(
				'acf_after_title'	=>	__( 'High (after title)', 'acf' ),
				'normal'			=>	__( 'Normal (after content)', 'acf' ),
				'side'				=>	__( 'Side', 'acf' ),
			);
			self::log(
				_x( 'Position of "%1$s" was updated to be "%2$s"', 'acf', 'stream' ),
				array(
					'title' => $post->post_title,
					'option_label' => $options[ $meta_value ],
					'option' => $meta_key,
					'option_value' => $meta_value,
				),
				$object_id,
				'options',
				'updated'
			);
		}
		// Layout option
		elseif ( 'layout' === $meta_key ) {
			if ( 'deleted' === $action ) {
				return;
			}
			$options = array(
				'no_box'			=>	__( 'Seamless (no metabox)', 'acf' ),
				'default'			=>	__( 'Standard (WP metabox)', 'acf' ),
			);
			self::log(
				_x( 'Style of "%1$s" was updated to be "%2$s"', 'acf', 'stream' ),
				array(
					'title' => $post->post_title,
					'option_label' => $options[ $meta_value ],
					'option' => $meta_key,
					'option_value' => $meta_value,
				),
				$object_id,
				'options',
				'updated'
			);
		}
		// Screen exclusion option
		elseif ( 'hide_on_screen' === $meta_key ) {
			if ( 'deleted' === $action ) {
				return;
			}
			$options = array(
				'permalink'			=>	__( 'Permalink', 'acf' ),
				'the_content'		=>	__( 'Content Editor', 'acf' ),
				'excerpt'			=>	__( 'Excerpt', 'acf' ),
				'custom_fields'		=>	__( 'Custom Fields', 'acf' ),
				'discussion'		=>	__( 'Discussion', 'acf' ),
				'comments'			=>	__( 'Comments', 'acf' ),
				'revisions'			=>	__( 'Revisions', 'acf' ),
				'slug'				=>	__( 'Slug', 'acf' ),
				'author'			=>	__( 'Author', 'acf' ),
				'format'			=>	__( 'Format', 'acf' ),
				'featured_image'	=>	__( 'Featured Image', 'acf' ),
				'categories'		=>	__( 'Categories', 'acf' ),
				'tags'				=>	__( 'Tags', 'acf' ),
				'send-trackbacks'	=>	__( 'Send Trackbacks', 'acf' ),
			);
			if ( count( $options ) === count( $meta_value ) ) {
				$options_label = _x( 'All screens', 'acf', 'stream' );
			} elseif ( empty( $meta_value ) ) {
				$options_label = _x( 'No screens', 'acf', 'stream' );
			} else {
				$options_label = implode( ', ', array_intersect_key( $options, array_flip( $meta_value ) ) );
			}
			self::log(
				_x( '"%1$s" set to display on "%2$s"', 'acf', 'stream' ),
				array(
					'title' => $post->post_title,
					'option_label' => $options_label,
					'option' => $meta_key,
					'option_value' => $meta_value,
				),
				$object_id,
				'options',
				'updated'
			);
		}

	}

	/**
	 * Track changes to cf values within rendered forms
	 *
	 * @param      $action
	 * @param      $meta_id
	 * @param      $object_id
	 * @param      $meta_key
	 * @param null $meta_value
	 */
	public static function check_post_meta_values( $action, $meta_id, $object_id, $meta_key, $meta_value = null ) {
		if ( empty( self::$cached_field_values_updates ) ) {
			return;
		}
		if ( isset( self::$cached_field_values_updates[ $object_id ][ $meta_key ] ) ) {
			$post = get_post( $object_id );
			$cache = self::$cached_field_values_updates[ $object_id ][ $meta_key ];
			self::log(
				_x( '"%1$s" of "%2$s" was updated', 'acf', 'stream' ),
				array(
					'field_label' => $cache['field']['label'],
					'title' => $post->post_title,
					'meta_value' => $meta_value,
					'meta_key' => $meta_key,
				),
				$object_id,
				'values',
				'updated'
			);
		}
	}

	/**
	 * Track changes to rules, complements post-meta updates
	 * @action shutdown
	 */
	public static function check_location_rules() {
		foreach ( self::$cached_location_rules as $post_id => $old ) {
			$new = get_post_meta( $post_id, 'rule' );
			$post = get_post( $post_id );
			if ( $old === $new ) {
				continue;
			}

			$new = array_map( 'json_encode', $new );
			$old = array_map( 'json_encode', $old );

			$added = array_diff( $new, $old );
			$deleted = array_diff( $old, $new );

			self::log(
				_x( 'Updated rules of "%1$s" ( %2$d added, %3$d deleted )', 'acf', 'stream' ),
				array(
					'title' => $post->post_title,
					'no_added' => count( $added ),
					'no_deleted' => count( $deleted ),
					'added' => $added,
					'deleted' => $deleted,
				),
				$post_id,
				'rules',
				'updated'
			);
		}
	}

	/**
	 * Override connector log for our own Settings / Actions
	 *
	 * @param array $data
	 *
	 * @return array|bool
	 */
	public static function log_override( array $data ) {
		if ( 'posts' === $data['connector'] && 'acf' === $data['context'] ) {
			$data['context']   = 'field_groups';
			$data['connector'] = self::$name;
			$data['args']['singular_name'] = __( 'field group', 'stream' );
		}

		return $data;
	}

	/**
	 * Track changes to custom field values updates, saves filtered values to be
	 * processed by callback_updated_post_meta
	 *
	 * @param $value
	 * @param $post_id
	 * @param $field
	 *
	 */
	public static function callback_acf_update_value( $value, $post_id, $field ) {
		self::$cached_field_values_updates[ $post_id ][ $field['name'] ] = compact( 'field', 'value', 'post_id' );
	}

}
