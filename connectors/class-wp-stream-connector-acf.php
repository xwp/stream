<?php

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
		'added_user_meta',
		'updated_user_meta',
		'delete_user_meta',
		'added_option',
		'updated_option',
		'deleted_option',
		'pre_post_update',
	);

	/**
	 * Cached location rules, used in shutdown callback to verify changes in meta
	 *
	 * @var array
	 */
	public static $cached_location_rules = array();

	/**
	 * Cached field values updates, used by shutdown callback to verify actual changes
	 *
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
		return _x( 'ACF', 'acf', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'created' => __( 'Created', 'acf', 'stream' ),
			'updated' => __( 'Updated', 'acf', 'stream' ),
			'added'   => __( 'Added', 'acf', 'stream' ),
			'deleted' => __( 'Deleted', 'acf', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		return array(
			'field_groups' => _x( 'Field Groups', 'acf', 'stream' ),
			'fields'       => _x( 'Fields', 'acf', 'stream' ),
			'rules'        => _x( 'Rules', 'acf', 'stream' ),
			'options'      => _x( 'Options', 'acf', 'stream' ),
			'values'       => _x( 'Values', 'acf', 'stream' ),
		);
	}

	/**
	 * Register the connector
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'wp_stream_log_data', array( __CLASS__, 'log_override' ) );

		/**
		 * Allow devs to disable logging values of rendered forms
		 *
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
	 * @return array          Action links
	 */
	public static function action_links( $links, $record ) {
		$links = WP_Stream_Connector_Posts::action_links( $links, $record );

		return $links;
	}

	/**
	 * Track addition of post meta
	 *
	 * @action added_post_meta
	 */
	public static function callback_added_post_meta() {
		call_user_func_array( array( __CLASS__, 'check_meta' ), array_merge( array( 'post', 'added' ), func_get_args() ) );
	}

	/**
	 * Track updating post meta
	 *
	 * @action updated_post_meta
	 */
	public static function callback_updated_post_meta() {
		call_user_func_array( array( __CLASS__, 'check_meta' ), array_merge( array( 'post', 'updated' ), func_get_args() ) );
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
		call_user_func_array( array( __CLASS__, 'check_meta' ), array_merge( array( 'post', 'deleted' ), func_get_args() ) );
	}

	/**
	 * Track addition of user meta
	 *
	 * @action added_user_meta
	 */
	public static function callback_added_user_meta() {
		call_user_func_array( array( __CLASS__, 'check_meta' ), array_merge( array( 'user', 'added' ), func_get_args() ) );
	}

	/**
	 * Track updating user meta
	 *
	 * @action updated_user_meta
	 */
	public static function callback_updated_user_meta() {
		call_user_func_array( array( __CLASS__, 'check_meta' ), array_merge( array( 'user', 'updated' ), func_get_args() ) );
	}

	/**
	 * Track deletion of user meta
	 *
	 * Note: Using delete_user_meta instead of deleted_user_meta to be able to
	 * capture old field value
	 *
	 * @action delete_user_meta
	 */
	public static function callback_delete_user_meta() {
		call_user_func_array( array( __CLASS__, 'check_meta' ), array_merge( array( 'user', 'deleted' ), func_get_args() ) );
	}

	/**
	 * Track addition of post/user meta
	 *
	 * @param string     $type       Type of object, post or user
	 * @param string     $action     Added, updated, deleted
	 * @param integer    $meta_id
	 * @param integer    $object_id
	 * @param string     $meta_key
	 * @param mixed|null $meta_value
	 */
	public static function check_meta( $type, $action, $meta_id, $object_id, $meta_key, $meta_value = null ) {
		if ( 'post' !== $type || ! ( $post = get_post( $object_id ) ) || 'acf' !== $post->post_type ) {
			self::check_meta_values( $type, $action, $meta_id, $object_id, $meta_key, $meta_value = null );
			return;
		}

		$action_labels = self::get_action_labels();

		// Fields
		if ( 0 === strpos( $meta_key, 'field_' ) ) {
			if ( 'deleted' === $action ) {
				$meta_value = get_post_meta( $object_id, $meta_key, true );
			}

			self::log(
				_x( '"%1$s" field in "%2$s" %3$s', 'acf', 'stream' ),
				array(
					'label'  => $meta_value['label'],
					'title'  => $post->post_title,
					'action' => strtolower( $action_labels[ $action ] ),
					'key'    => $meta_value['key'],
					'name'   => $meta_value['name'],
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
				'acf_after_title' => _x( 'High (after title)', 'acf', 'stream' ),
				'normal'          => _x( 'Normal (after content)', 'acf', 'stream' ),
				'side'            => _x( 'Side', 'acf', 'stream' ),
			);

			self::log(
				_x( 'Position of "%1$s" updated to "%2$s"', 'acf', 'stream' ),
				array(
					'title'        => $post->post_title,
					'option_label' => $options[ $meta_value ],
					'option'       => $meta_key,
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
				'no_box'  => _x( 'Seamless (no metabox)', 'acf', 'stream' ),
				'default' => _x( 'Standard (WP metabox)', 'acf', 'stream' ),
			);

			self::log(
				_x( 'Style of "%1$s" updated to "%2$s"', 'acf', 'stream' ),
				array(
					'title'        => $post->post_title,
					'option_label' => $options[ $meta_value ],
					'option'       => $meta_key,
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
				'permalink'       => _x( 'Permalink', 'acf', 'stream' ),
				'the_content'     => _x( 'Content Editor', 'acf', 'stream' ),
				'excerpt'         => _x( 'Excerpt', 'acf', 'stream' ),
				'custom_fields'   => _x( 'Custom Fields', 'acf', 'stream' ),
				'discussion'      => _x( 'Discussion', 'acf', 'stream' ),
				'comments'        => _x( 'Comments', 'acf', 'stream' ),
				'revisions'       => _x( 'Revisions', 'acf', 'stream' ),
				'slug'            => _x( 'Slug', 'acf', 'stream' ),
				'author'          => _x( 'Author', 'acf', 'stream' ),
				'format'          => _x( 'Format', 'acf', 'stream' ),
				'featured_image'  => _x( 'Featured Image', 'acf', 'stream' ),
				'categories'      => _x( 'Categories', 'acf', 'stream' ),
				'tags'            => _x( 'Tags', 'acf', 'stream' ),
				'send-trackbacks' => _x( 'Send Trackbacks', 'acf', 'stream' ),
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
					'title'        => $post->post_title,
					'option_label' => $options_label,
					'option'       => $meta_key,
					'option_value' => $meta_value,
				),
				$object_id,
				'options',
				'updated'
			);
		}
	}

	/**
	 * Track changes to ACF values within rendered post meta forms
	 *
	 * @param string     $type      Type of object, post or user
	 * @param string     $action    Added, updated, deleted
	 * @param integer    $meta_id
	 * @param integer    $object_id
	 * @param string     $key
	 * @param mixed|null $value
	 *
	 * @return bool
	 */
	public static function check_meta_values( $type, $action, $meta_id, $object_id, $key, $value = null ) {
		if ( empty( self::$cached_field_values_updates ) ) {
			return false;
		}

		$object_key = $object_id;

		if ( 'user' === $type ) {
			$object_key = 'user_' . $object_id;
		} elseif ( 'taxonomy' === $type ) {
			if ( 0 === strpos( $key, '_' ) ) { // Ignore the 'revision' stuff!
				return false;
			}

			if ( 1 !== preg_match( '#([a-z0-9_-]+)_([\d]+)_([a-z0-9_-]+)#', $key, $matches ) ) {
				return false;
			}

			list( , $taxonomy, $term_id, $key ) = $matches; // Skips 0 index

			$object_key = $taxonomy . '_' . $term_id;
		}

		if ( isset( self::$cached_field_values_updates[ $object_key ][ $key ] ) ) {
			if ( 'post' === $type ) {
				$post      = get_post( $object_id );
				$title     = $post->post_title;
				$type_name = strtolower( WP_Stream_Connector_Posts::get_post_type_name( $post->post_type ) );
			} elseif ( 'user' === $type ) {
				$user      = new WP_User( $object_id );
				$title     = $user->get( 'display_name' );
				$type_name = __( 'user', 'stream' );
			} elseif ( 'taxonomy' === $type ) {
				$term      = get_term( $term_id, $taxonomy );
				$title     = $term->name;
				$tax_obj   = get_taxonomy( $taxonomy );
				$type_name = strtolower( get_taxonomy_labels( $tax_obj )->singular_name );
			} else {
				return false;
			}

			$cache = self::$cached_field_values_updates[ $object_key ][ $key ];

			self::log(
				_x( '"%1$s" of "%2$s" %3$s updated', 'acf', 'stream' ),
				array(
					'field_label'   => $cache['field']['label'],
					'title'         => $title,
					'singular_name' => $type_name,
					'meta_value'    => $value,
					'meta_key'      => $key,
					'meta_type'     => $type,
				),
				$object_id,
				'values',
				'updated'
			);
		}

		return true;
	}

	/**
	 * Track changes to rules, complements post-meta updates
	 *
	 * @action shutdown
	 */
	public static function check_location_rules() {
		foreach ( self::$cached_location_rules as $post_id => $old ) {
			$new  = get_post_meta( $post_id, 'rule' );
			$post = get_post( $post_id );

			if ( $old === $new ) {
				continue;
			}

			$new     = array_map( 'json_encode', $new );
			$old     = array_map( 'json_encode', $old );
			$added   = array_diff( $new, $old );
			$deleted = array_diff( $old, $new );

			self::log(
				_x( 'Updated rules of "%1$s" (%2$d added, %3$d deleted)', 'acf', 'stream' ),
				array(
					'title'      => $post->post_title,
					'no_added'   => count( $added ),
					'no_deleted' => count( $deleted ),
					'added'      => $added,
					'deleted'    => $deleted,
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
	public static function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( 'posts' === $data['connector'] && 'acf' === $data['context'] ) {
			$data['context']               = 'field_groups';
			$data['connector']             = self::$name;
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
	 */
	public static function callback_acf_update_value( $value, $post_id, $field ) {
		self::$cached_field_values_updates[ $post_id ][ $field['name'] ] = compact( 'field', 'value', 'post_id' );
		return $value;
	}

	/**
	 * Track changes to post main attributes, ie: Order No.
	 *
	 * @param $post_id
	 * @param $data    Array with the updated post data
	 */
	public static function callback_pre_post_update( $post_id, $data ) {
		$post = get_post( $post_id );

		if ( 'acf' !== $post->post_type ) {
			return;
		}

		// menu_order, aka Order No.
		if ( $data['menu_order'] !== $post->menu_order ) {
			self::log(
				_x( 'Updated Order of "%1$s" from %2$d to %3$d', 'acf', 'stream' ),
				array(
					'title'          => $post->post_title,
					'old_menu_order' => $post->menu_order,
					'menu_order'     => $data['menu_order'],
				),
				$post_id,
				'field_groups',
				'updated'
			);
		}
	}

	/**
	 * Track addition of new options
	 *
	 * @param $key   Option name
	 * @param $value Option value
	 */
	public static function callback_added_option( $key, $value ) {
		self::check_meta_values( 'taxonomy', 'added', null, null, $key, $value );
	}

	/**
	 * Track addition of new options
	 *
	 * @param $key
	 * @param $old
	 * @param $value
	 */
	public static function callback_updated_option( $key, $old, $value ) {
		self::check_meta_values( 'taxonomy', 'updated', null, null, $key, $value );
	}

	/**
	 * Track addition of new options
	 *
	 * @param $key
	 */
	public static function callback_deleted_option( $key ) {
		self::check_meta_values( 'taxonomy', 'deleted', null, null, $key, null );
	}

}
