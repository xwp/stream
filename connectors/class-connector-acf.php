<?php
/**
 * Connector - Advanced Custom Fields
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_ACF
 */
class Connector_ACF extends Connector {
	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'acf';

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
	public $actions = array(
		'save_post',
		'post_updated',
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
	public $cached_location_rules = array();

	/**
	 * Cached field values updates, used by shutdown callback to verify actual changes
	 *
	 * @var array
	 */
	public $cached_field_values_updates = array();


	/**
	 * Check if plugin dependencies are satisfied and add an admin notice if not
	 *
	 * @return bool
	 */
	public function is_dependency_satisfied() {
		if ( class_exists( 'acf' ) ) { // TODO: Should this be function_exists?
			$acf = \acf();
			if ( version_compare( $acf->settings['version'], self::PLUGIN_MIN_VERSION, '>=' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return esc_html_x( 'ACF', 'acf', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'created' => esc_html_x( 'Created', 'acf', 'stream' ),
			'updated' => esc_html_x( 'Updated', 'acf', 'stream' ),
			'added'   => esc_html_x( 'Added', 'acf', 'stream' ),
			'deleted' => esc_html_x( 'Deleted', 'acf', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		return array(
			'field_groups' => esc_html_x( 'Field Groups', 'acf', 'stream' ),
			'fields'       => esc_html_x( 'Fields', 'acf', 'stream' ),
			'rules'        => esc_html_x( 'Rules', 'acf', 'stream' ),
			'options'      => esc_html_x( 'Options', 'acf', 'stream' ),
			'values'       => esc_html_x( 'Values', 'acf', 'stream' ),
		);
	}

	/**
	 * Register the connector
	 */
	public function register() {
		add_filter( 'wp_stream_log_data', array( $this, 'log_override' ) );

		/**
		 * Allow devs to disable logging values of rendered forms
		 *
		 * @return bool
		 */
		if ( apply_filters( 'wp_stream_acf_enable_value_logging', true ) ) {
			$this->actions[] = 'acf/update_value';
		}

		parent::register();
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param array  $links  Previous links registered.
	 * @param object $record Stream record.
	 *
	 * @return array          Action links
	 */
	public function action_links( $links, $record ) {
		$posts_connector = new Connector_Posts();
		$links           = $posts_connector->action_links( $links, $record );

		return $links;
	}

	/**
	 * Tracks the creation of custom field group fields and settings (ACF v5+ only)
	 *
	 * @action save_post
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	 */
	public function callback_save_post( $post_id, $post, $update ) {
		// Bail if updating existing post.
		if ( false !== $update ) {
			return;
		}

		// Log new ACF field additions to field groups.
		if ( 'acf-field' === $post->post_type ) {
			$parent = get_post( $post->post_parent );
			if ( $parent ) {
				$this->log_prop( 'added', $post_id, $post, 'parent', $parent );
			}
		} elseif ( 'acf-field-group' === $post->post_type ) {
			$props = maybe_unserialize( $post->post_content );

			if ( ! empty( $props ) && is_array( $props ) ) {
				foreach ( $props as $prop => $value ) {
					$this->log_prop( 'added', $post_id, $post, $prop, $value );
				}
			}
		}
	}

	/**
	 * Tracks changes to custom field groups settings.
	 *
	 * @action post_updated
	 *
	 * @param int      $post_id       Post ID.
	 * @param \WP_Post $posts_after   Newly saved post object.
	 * @param \WP_Post $posts_before  Old post object.
	 * @return void
	 */
	public function callback_post_updated( $post_id, $posts_after, $posts_before ) {
		if ( 'acf-field-group' !== $posts_after->post_type ) {
			return;
		}

		$_new = ! empty( $posts_after->post_content ) ? maybe_unserialize( $posts_after->post_content ) : array();
		$_old = ! empty( $posts_before->post_content ) ? maybe_unserialize( $posts_before->post_content ) : array();

		// Get updated settings.
		$updated_keys = $this->get_changed_keys( $_new, $_old );
		$updated_keys = empty( $updated_keys ) ? array_keys( $_new ) : $updated_keys;

		// Process updated properties.
		foreach ( $updated_keys as $prop ) {
			$old_value = null;
			$value     = $_new[ $prop ];
			if ( empty( $value ) && is_array( $_old ) && ! empty( $_old[ $prop ] ) ) {
				$action    = 'deleted';
				$old_value = $_old[ $prop ];
			} else {
				$action = 'updated';
			}

			$this->log_prop( $action, $post_id, $posts_after, $prop, $value, $old_value );
		}
	}


	/**
	 * Logs field/field group property changes (ACF v5 only).
	 *
	 * @param string     $action     Added, updated, deleted.
	 * @param int        $post_id    Post ID.
	 * @param WP_Post    $post       Post object.
	 * @param string     $property   ACF property.
	 * @param mixed|null $value      Value assigned to property.
	 * @param mixed|null $old_value  Old value previously assigned to property.
	 * @return void
	 */
	public function log_prop( $action, $post_id, $post, $property, $value = null, $old_value = null ) {
		$action_labels = $this->get_action_labels();

		// Fields.
		if ( 'parent' === $property ) {
			if ( 'deleted' === $action ) {
				$meta_value = $old_value;
			}

			$this->log(
				/* translators: %1$s: field label, %2$s: form title, %3$s: action (e.g. "Message", "Contact", "Created") */
				esc_html_x( '"%1$s" field in "%2$s" %3$s', 'acf', 'stream' ),
				array(
					'label'  => $post->post_title,
					'title'  => $value->post_title,
					'action' => strtolower( $action_labels[ $action ] ),
					'key'    => $post->post_name,
					'name'   => $post->post_excerpt,
				),
				$value->ID,
				'fields',
				$action
			);
		} elseif ( 'position' === $property ) {
			if ( 'deleted' === $action ) {
				return;
			}

			$options = array(
				'acf_after_title' => esc_html_x( 'High (after title)', 'acf', 'stream' ),
				'normal'          => esc_html_x( 'Normal (after content)', 'acf', 'stream' ),
				'side'            => esc_html_x( 'Side', 'acf', 'stream' ),
			);

			$this->log(
				/* translators: %1$s: form title, %2$s a position (e.g. "Contact", "Side") */
				esc_html_x( 'Position of "%1$s" updated to "%2$s"', 'acf', 'stream' ),
				array(
					'title'        => $post->post_title,
					'option_label' => $options[ $value ],
					'option'       => $property,
					'option_value' => $value,
				),
				$post_id,
				'options',
				'updated'
			);
		} elseif ( 'layout' === $property ) {
			if ( 'deleted' === $action ) {
				return;
			}

			$options = array(
				'no_box'  => esc_html_x( 'Seamless (no metabox)', 'acf', 'stream' ),
				'default' => esc_html_x( 'Standard (WP metabox)', 'acf', 'stream' ),
			);

			$this->log(
				/* translators: %1$s: form title, %2$s a layout (e.g. "Contact", "Seamless") */
				esc_html_x( 'Style of "%1$s" updated to "%2$s"', 'acf', 'stream' ),
				array(
					'title'        => $post->post_title,
					'option_label' => $options[ $value ],
					'option'       => $property,
					'option_value' => $value,
				),
				$post_id,
				'options',
				'updated'
			);
		} elseif ( 'hide_on_screen' === $property ) {
			if ( 'deleted' === $action ) {
				return;
			}

			$options = array(
				'permalink'       => esc_html_x( 'Permalink', 'acf', 'stream' ),
				'the_content'     => esc_html_x( 'Content Editor', 'acf', 'stream' ),
				'excerpt'         => esc_html_x( 'Excerpt', 'acf', 'stream' ),
				'custom_fields'   => esc_html_x( 'Custom Fields', 'acf', 'stream' ),
				'discussion'      => esc_html_x( 'Discussion', 'acf', 'stream' ),
				'comments'        => esc_html_x( 'Comments', 'acf', 'stream' ),
				'revisions'       => esc_html_x( 'Revisions', 'acf', 'stream' ),
				'slug'            => esc_html_x( 'Slug', 'acf', 'stream' ),
				'author'          => esc_html_x( 'Author', 'acf', 'stream' ),
				'format'          => esc_html_x( 'Format', 'acf', 'stream' ),
				'featured_image'  => esc_html_x( 'Featured Image', 'acf', 'stream' ),
				'categories'      => esc_html_x( 'Categories', 'acf', 'stream' ),
				'tags'            => esc_html_x( 'Tags', 'acf', 'stream' ),
				'send-trackbacks' => esc_html_x( 'Send Trackbacks', 'acf', 'stream' ),
			);

			if ( is_array( $value ) && count( $options ) === count( $value ) ) {
				$options_label = esc_html_x( 'All screens', 'acf', 'stream' );
			} elseif ( empty( $value ) ) {
				$options_label = esc_html_x( 'No screens', 'acf', 'stream' );
			} else {
				$options_label = implode( ', ', array_intersect_key( $options, array_flip( $value ) ) );
			}

			$this->log(
				/* translators: %1$s: a form title, %2$s: a display option (e.g. "Contact", "All screens") */
				esc_html_x( '"%1$s" set to display on "%2$s"', 'acf', 'stream' ),
				array(
					'title'        => $post->post_title,
					'option_label' => $options_label,
					'option'       => $property,
					'option_value' => $value,
				),
				$post_id,
				'options',
				'updated'
			);
		}
	}

	/**
	 * Track addition of post meta
	 *
	 * @action added_post_meta
	 */
	public function callback_added_post_meta() {
		$this->check_meta( 'post', 'added', ...func_get_args() );
	}

	/**
	 * Track updating post meta
	 *
	 * @action updated_post_meta
	 */
	public function callback_updated_post_meta() {
		$this->check_meta( 'post', 'updated', ...func_get_args() );
	}

	/**
	 * Track deletion of post meta
	 *
	 * Note: Using delete_post_meta instead of deleted_post_meta to be able to
	 * capture old field value
	 *
	 * @action delete_post_meta
	 */
	public function callback_delete_post_meta() {
		$this->check_meta( 'post', 'deleted', ...func_get_args() );
	}

	/**
	 * Track addition of user meta
	 *
	 * @action added_user_meta
	 */
	public function callback_added_user_meta() {
		$this->check_meta( 'user', 'added', ...func_get_args() );
	}

	/**
	 * Track updating user meta
	 *
	 * @action updated_user_meta
	 */
	public function callback_updated_user_meta() {
		$this->check_meta( 'user', 'updated', ...func_get_args() );
	}

	/**
	 * Track deletion of user meta
	 *
	 * Note: Using delete_user_meta instead of deleted_user_meta to be able to
	 * capture old field value
	 *
	 * @action delete_user_meta
	 */
	public function callback_delete_user_meta() {
		$this->check_meta( 'user', 'deleted', ...func_get_args() );
	}

	/**
	 * Track addition of post/user meta
	 *
	 * @param string     $type       Type of object, post or user.
	 * @param string     $action     Added, updated, deleted.
	 * @param integer    $meta_id    Meta ID.
	 * @param integer    $object_id  Object ID.
	 * @param string     $meta_key   Meta Key.
	 * @param mixed|null $meta_value Value being stored in meta.
	 */
	public function check_meta( $type, $action, $meta_id, $object_id, $meta_key, $meta_value = null ) {
		$post = get_post( $object_id );
		if ( 'post' !== $type || ! $post || ! in_array( $post->post_type, array( 'acf', 'acf-field-group' ), true ) ) {
			$this->check_meta_values( $type, $action, $meta_id, $object_id, $meta_key, $meta_value );
			return;
		}

		$action_labels = $this->get_action_labels();

		// Fields.
		if ( 0 === strpos( $meta_key, 'field_' ) ) {
			if ( 'deleted' === $action ) {
				$meta_value = get_post_meta( $object_id, $meta_key, true );
			}

			$this->log(
				/* translators: %1$s: field label, %2$s: form title, %3$s: action (e.g. "Message", "Contact", "Created") */
				esc_html_x( '"%1$s" field in "%2$s" %3$s', 'acf', 'stream' ),
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
		} elseif ( 'rule' === $meta_key ) {
			if ( 'deleted' === $action ) {
				$this->cached_location_rules[ $object_id ] = get_post_meta( $object_id, 'rule' );

				add_action( 'shutdown', array( $this, 'check_location_rules' ), 9 );
			}
		} elseif ( 'position' === $meta_key ) {
			if ( 'deleted' === $action ) {
				return;
			}

			$options = array(
				'acf_after_title' => esc_html_x( 'High (after title)', 'acf', 'stream' ),
				'normal'          => esc_html_x( 'Normal (after content)', 'acf', 'stream' ),
				'side'            => esc_html_x( 'Side', 'acf', 'stream' ),
			);

			$this->log(
				/* translators: %1$s: form title, %2$s a position (e.g. "Contact", "Side") */
				esc_html_x( 'Position of "%1$s" updated to "%2$s"', 'acf', 'stream' ),
				array(
					'title'        => $post->post_title,
					'option_label' => $options[ $meta_value ],
					'option'       => $meta_key,
					'option_value' => $meta_value,
				),
				$object_id,
				'options',
				$action
			);
		} elseif ( 'layout' === $meta_key ) {
			if ( 'deleted' === $action ) {
				return;
			}

			$options = array(
				'no_box'  => esc_html_x( 'Seamless (no metabox)', 'acf', 'stream' ),
				'default' => esc_html_x( 'Standard (WP metabox)', 'acf', 'stream' ),
			);

			$this->log(
				/* translators: %1$s: form title, %2$s a layout (e.g. "Contact", "Seamless") */
				esc_html_x( 'Style of "%1$s" updated to "%2$s"', 'acf', 'stream' ),
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
		} elseif ( 'hide_on_screen' === $meta_key ) {
			if ( 'deleted' === $action ) {
				return;
			}

			$options = array(
				'permalink'       => esc_html_x( 'Permalink', 'acf', 'stream' ),
				'the_content'     => esc_html_x( 'Content Editor', 'acf', 'stream' ),
				'excerpt'         => esc_html_x( 'Excerpt', 'acf', 'stream' ),
				'custom_fields'   => esc_html_x( 'Custom Fields', 'acf', 'stream' ),
				'discussion'      => esc_html_x( 'Discussion', 'acf', 'stream' ),
				'comments'        => esc_html_x( 'Comments', 'acf', 'stream' ),
				'revisions'       => esc_html_x( 'Revisions', 'acf', 'stream' ),
				'slug'            => esc_html_x( 'Slug', 'acf', 'stream' ),
				'author'          => esc_html_x( 'Author', 'acf', 'stream' ),
				'format'          => esc_html_x( 'Format', 'acf', 'stream' ),
				'featured_image'  => esc_html_x( 'Featured Image', 'acf', 'stream' ),
				'categories'      => esc_html_x( 'Categories', 'acf', 'stream' ),
				'tags'            => esc_html_x( 'Tags', 'acf', 'stream' ),
				'send-trackbacks' => esc_html_x( 'Send Trackbacks', 'acf', 'stream' ),
			);

			if ( count( $options ) === count( $meta_value ) ) {
				$options_label = esc_html_x( 'All screens', 'acf', 'stream' );
			} elseif ( empty( $meta_value ) ) {
				$options_label = esc_html_x( 'No screens', 'acf', 'stream' );
			} else {
				$options_label = implode( ', ', array_intersect_key( $options, array_flip( $meta_value ) ) );
			}

			$this->log(
				/* translators: %1$s: a form title, %2$s: a display option (e.g. "Contact", "All screens") */
				esc_html_x( '"%1$s" set to display on "%2$s"', 'acf', 'stream' ),
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
	 * @param string     $type       Type of object, post or user.
	 * @param string     $action     Added, updated, deleted.
	 * @param integer    $meta_id    Meta ID.
	 * @param integer    $object_id  Object ID.
	 * @param string     $key        Meta Key.
	 * @param mixed|null $value      Value being stored in meta.
	 *
	 * @return bool
	 */
	public function check_meta_values( $type, $action, $meta_id, $object_id, $key, $value = null ) {
		unset( $action );
		unset( $meta_id );

		if ( empty( $this->cached_field_values_updates ) ) {
			return false;
		}

		$object_key = $object_id;

		if ( 'user' === $type ) {
			$object_key = 'user_' . $object_id;
		} elseif ( 'taxonomy' === $type ) {
			if ( 0 === strpos( $key, '_' ) ) { // Ignore the 'revision' stuff!.
				return false;
			}

			if ( 1 !== preg_match( '#([a-z0-9_-]+)_([\d]+)_([a-z0-9_-]+)#', $key, $matches ) ) {
				return false;
			}

			list( , $taxonomy, $term_id, $key ) = $matches; // Skips 0 index.

			$object_key = $taxonomy . '_' . $term_id;
		} elseif ( 'option' === $type ) {
			$object_key = 'options';
			$key        = preg_replace( '/^options_/', '', $key );
		}

		if ( isset( $this->cached_field_values_updates[ $object_key ][ $key ] ) ) {
			if ( 'post' === $type ) {
				$posts_connector = new Connector_Posts();

				$post      = get_post( $object_id );
				$title     = $post->post_title;
				$type_name = strtolower( $posts_connector->get_post_type_name( $post->post_type ) );
			} elseif ( 'user' === $type ) {
				$user      = new \WP_User( $object_id );
				$title     = $user->get( 'display_name' );
				$type_name = esc_html__( 'user', 'stream' );
			} elseif ( 'taxonomy' === $type && isset( $term_id ) && isset( $taxonomy ) ) {
				$term      = get_term( $term_id, $taxonomy );
				$title     = $term->name;
				$tax_obj   = get_taxonomy( $taxonomy );
				$type_name = strtolower( get_taxonomy_labels( $tax_obj )->singular_name );
			} elseif ( 'option' === $type ) {
				$title     = 'settings page';
				$type_name = 'option';
			} else {
				return false;
			}

			$cache = $this->cached_field_values_updates[ $object_key ][ $key ];

			$this->log(
				/* translators: %1$s: a field label, %2$s: an object title, %3$s: an object type (e.g. "Message", "Hello World", "post") */
				esc_html_x( '"%1$s" of "%2$s" %3$s updated', 'acf', 'stream' ),
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
	public function check_location_rules() {
		foreach ( $this->cached_location_rules as $post_id => $old ) {
			$new  = get_post_meta( $post_id, 'rule' );
			$post = get_post( $post_id );

			if ( $old === $new ) {
				continue;
			}

			$new     = array_map( 'wp_stream_json_encode', $new );
			$old     = array_map( 'wp_stream_json_encode', $old );
			$added   = array_diff( $new, $old );
			$deleted = array_diff( $old, $new );

			$this->log(
				/* translators: %1$s: a form title, %2$d: the number of rules added, %3$d: the number of rules deleted (e.g. "Contact", "42", "7") */
				esc_html_x( 'Updated rules of "%1$s" (%2$d added, %3$d deleted)', 'acf', 'stream' ),
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
	 * @param array $data  Record data.
	 *
	 * @return array|bool
	 */
	public function log_override( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$is_acf_context = in_array( $data['context'], array( 'acf', 'acf-field-group', 'acf-field' ), true );

		if ( 'posts' === $data['connector'] && $is_acf_context ) {
			// If ACF field group CPT being logged.
			if ( 'acf' === $data['context'] || 'acf-field-group' === $data['context'] ) {
				$data['context']               = 'field_groups';
				$data['connector']             = $this->name;
				$data['args']['singular_name'] = esc_html__( 'field group', 'stream' );

				// elseif ACF field CPT being logged (ACF v5+ only).
			} elseif ( 'acf-field' === $data['context'] ) {
				$field_group = get_post( wp_get_post_parent_id( $data['object_id'] ) );

				$data['context']               = 'fields';
				$data['connector']             = $this->name;
				$data['args']['singular_name'] = ! empty( $field_group )
					? sprintf(
						/* translators: %s: field group name */
						esc_html__( 'field in the "%s" field group', 'stream' ),
						$field_group->post_title
					)
					: esc_html__( 'field', 'stream' );
			}
		}

		return $data;
	}

	/**
	 * Track changes to custom field values updates, saves filtered values to be
	 * processed by callback_updated_post_meta
	 *
	 * @param string $value    Field value.
	 * @param int    $post_id  Field post ID.
	 * @param string $field    Field name.
	 *
	 * @return string
	 */
	public function callback_acf_update_value( $value, $post_id, $field ) {
		$this->cached_field_values_updates[ $post_id ][ $field['name'] ] = compact( 'field', 'value', 'post_id' );
		return $value;
	}

	/**
	 * Track changes to post main attributes, ie: Order No.
	 *
	 * @param int   $post_id Field post ID.
	 * @param array $data    Array with the updated post data.
	 */
	public function callback_pre_post_update( $post_id, $data ) {
		$post = get_post( $post_id );

		if ( 'acf' !== $post->post_type ) {
			return;
		}

		// menu_order, aka Order No.
		if ( $data['menu_order'] !== $post->menu_order ) {
			$this->log(
				/* translators: %1$s: a form title, %2$d: a numeric position, %3$d: numeric position (e.g. "Contact", "42", "7") */
				esc_html_x( '"%1$s" reordered from %2$d to %3$d', 'acf', 'stream' ),
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
	 * @param string $key   Option name.
	 * @param string $value Option value.
	 */
	public function callback_added_option( $key, $value ) {
		$this->check_meta_values( self::get_saved_option_type( $key ), 'added', null, null, $key, $value );
	}

	/**
	 * Track addition of new options
	 *
	 * @param string $key   Option key.
	 * @param string $old   Old value.
	 * @param string $value New value.
	 */
	public function callback_updated_option( $key, $old, $value ) {
		unset( $old );
		$this->check_meta_values( self::get_saved_option_type( $key ), 'updated', null, null, $key, $value );
	}

	/**
	 * Track addition of new options
	 *
	 * @param string $key Option key.
	 */
	public function callback_deleted_option( $key ) {
		$this->check_meta_values( self::get_saved_option_type( $key ), 'deleted', null, null, $key, null );
	}

	/**
	 * Determines the type of option that is saved
	 *
	 * @param string $key Option key.
	 * @return string
	 */
	private function get_saved_option_type( $key ) {
		return substr( $key, 0, 8 ) === 'options_' ? 'option' : 'taxonomy';
	}
}
