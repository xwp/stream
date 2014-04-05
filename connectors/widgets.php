<?php

class WP_Stream_Connector_Widgets extends WP_Stream_Connector {

	/**
	 * Context name
	 *
	 * @var string
	 */
	public static $name = 'widgets';

	/**
	 * Actions registered for this context
	 *
	 * @var array
	 */
	public static $actions = array(
		'update_option_sidebars_widgets',
		'updated_option',
	);

	/**
	 * Return translated context label
	 *
	 * @return string Translated context label
	 */
	public static function get_label() {
		return __( 'Widgets', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public static function get_action_labels() {
		return array(
			'added'       => __( 'Added', 'stream' ),
			'deleted'     => __( 'Deleted', 'stream' ),
			'deactivated' => __( 'Deactivated', 'stream' ),
			'reactivated' => __( 'Reactivated', 'stream' ),
			'moved'       => __( 'Moved', 'stream' ),
			'updated'     => __( 'Updated', 'stream' ),
			'sorted'      => __( 'Sorted', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public static function get_context_labels() {
		global $wp_registered_sidebars;

		$labels = array();

		foreach ( $wp_registered_sidebars as $sidebar ) {
			$labels[ $sidebar['id'] ] = $sidebar['name'];
		}

		$labels['wp_inactive_widgets'] = esc_html__( 'Inactive Widgets' );
		$labels['orphaned_widgets'] = esc_html__( 'Orphaned Widgets' );
		$labels[''] = esc_html__( 'Unknown', 'stream' );

		return $labels;
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 * @param  array $links      Previous links registered
	 * @param  int   $record     Stream record
	 * @return array             Action links
	 */
	public static function action_links( $links, $record ) {
		if ( $sidebar = get_stream_meta( $record->ID, 'sidebar', true ) ) {
			global $wp_registered_sidebars;

			if ( array_key_exists( $sidebar, $wp_registered_sidebars ) ) {
				$links[ __( 'Edit Widget Area', 'stream' ) ] = admin_url( "widgets.php#$sidebar" );
			}
		}

		return $links;
	}

	/**
	 * @return bool
	 */
	public static function is_customizer_preview() {
		global $wp_customize;
		return ! empty( $wp_customize ) && $wp_customize->is_preview();
	}

	/**
	 * Tracks addition/deletion/reordering/deactivation of widgets from sidebars
	 *
	 * @action update_option_sidebars_widgets
	 * @param  array $old  Old sidebars widgets
	 * @param  array $new  New sidebars widgets
	 * @return void
	 */
	public static function callback_update_option_sidebars_widgets( $old, $new ) {

		// Disable listener if we're switching themes
		if ( did_action( 'after_switch_theme' ) ) {
			return;
		}

		unset( $old['array_version'] );
		unset( $new['array_version'] );

		self::handle_deactivated_widgets( $old, $new );
		self::handle_reactivated_widgets( $old, $new );
		self::handle_widget_deletion( $old, $new );
		self::handle_widget_addition( $old, $new );
		self::handle_widget_reordering( $old, $new );
		self::handle_widget_moved( $old, $new );
	}


	/**
	 * Track deactivation of widgets from sidebars
	 *
	 * @param  array $old  Old sidebars widgets
	 * @param  array $new  New sidebars widgets
	 * @return void
	 */
	static protected function handle_deactivated_widgets( $old, $new ) {
		$new_deactivated_widget_ids = array_diff( $new['wp_inactive_widgets'], $old['wp_inactive_widgets'] );
		foreach ( $new_deactivated_widget_ids as $widget_id ) {
			$sidebar_id = '';
			foreach ( $old as $old_sidebar_id => $old_widget_ids ) {
				if ( in_array( $widget_id, $old_widget_ids ) ) {
					$sidebar_id = $old_sidebar_id;
					break;
				}
			}
			$action  = 'deactivated';
			$title = self::get_widget_title( $widget_id );
			$name = self::get_widget_name( $widget_id );
			if ( $name && $title ) {
				$message = _x(
					'"%1$s" (%2$s) widget deactivated',
					'1: Widget title, 2: Widget name',
					'stream'
				);
			} else if ( $name ) {
				// Empty title, but we have the name
				$message = _x(
					'%2$s widget deactivated',
					'2: Widget name',
					'stream'
				);
			} else if ( $title ) {
				// Likely a single widget since no name is available
				$message = _x(
					'"%1$s" widget deactivated',
					'1: Widget title',
					'stream'
				);
			} else {
				// Neither a name nor a title are available, so use the sidebar ID
				$message = _x(
					'%3$s widget deactivated',
					'3: Widget ID',
					'stream'
				);
			}

			self::log(
				$message,
				compact( 'title', 'name', 'widget_id', 'sidebar_id' ),
				null,
				array( 'wp_inactive_widgets' => $action )
			);
		}
	}


	/**
	 * Track reactivation of widgets from sidebars
	 *
	 * @param  array $old  Old sidebars widgets
	 * @param  array $new  New sidebars widgets
	 * @return void
	 */
	static protected function handle_reactivated_widgets( $old, $new ) {
		$new_reactivated_widget_ids = array_diff( $old['wp_inactive_widgets'], $new['wp_inactive_widgets'] );
		foreach ( $new_reactivated_widget_ids as $widget_id ) {
			$sidebar_id = '';
			foreach ( $new as $new_sidebar_id => $new_widget_ids ) {
				if ( in_array( $widget_id, $new_widget_ids ) ) {
					$sidebar_id = $new_sidebar_id;
					break;
				}
			}

			$action  = 'reactivated';
			$title = self::get_widget_title( $widget_id );
			$name = self::get_widget_name( $widget_id );
			if ( $name && $title ) {
				$message = _x(
					'"%1$s" (%2$s) widget reactivated',
					'1: Widget title, 2: Widget name',
					'stream'
				);
			} else if ( $name ) {
				// Empty title, but we have the name
				$message = _x(
					'%2$s widget reactivated',
					'2: Widget name',
					'stream'
				);
			} else if ( $title ) {
				// Likely a single widget since no name is available
				$message = _x(
					'"%1$s" widget reactivated',
					'1: Widget title',
					'stream'
				);
			} else {
				// Neither a name nor a title are available, so use the sidebar ID
				$message = _x(
					'%3$s widget reactivated',
					'3: Widget ID',
					'stream'
				);
			}
			self::log(
				$message,
				compact( 'title', 'name', 'widget_id', 'sidebar_id' ),
				null,
				array( $sidebar_id => $action )
			);
		}
	}

	/**
	 * Track deletion of widgets from sidebars
	 *
	 * @param  array $old  Old sidebars widgets
	 * @param  array $new  New sidebars widgets
	 * @return void
	 */
	static protected function handle_widget_deletion( $old, $new ) {
		$all_old_widget_ids = array_unique( call_user_func_array( 'array_merge', $old ) );
		$all_new_widget_ids = array_unique( call_user_func_array( 'array_merge', $new ) );
		$deleted_widget_ids = array_diff( $all_old_widget_ids, $all_new_widget_ids );
		foreach ( $deleted_widget_ids as $widget_id ) {
			$sidebar_id = '';
			foreach ( $old as $old_sidebar_id => $old_widget_ids ) {
				if ( in_array( $widget_id, $old_widget_ids ) ) {
					$sidebar_id = $old_sidebar_id;
					break;
				}
			}
			$action  = 'deleted';
			$title = self::get_widget_title( $widget_id );
			$name = self::get_widget_name( $widget_id );
			if ( $name && $title ) {
				$message = _x(
					'"%1$s" (%2$s) widget deleted',
					'1: Widget title, 2: Widget name',
					'stream'
				);
			} else if ( $name ) {
				// Empty title, but we have the name
				$message = _x(
					'%2$s widget deleted',
					'2: Widget name',
					'stream'
				);
			} else if ( $title ) {
				// Likely a single widget since no name is available
				$message = _x(
					'"%1$s" widget deleted',
					'1: Widget title',
					'stream'
				);
			} else {
				// Neither a name nor a title are available, so use the sidebar ID
				$message = _x(
					'%3$s widget deleted',
					'3: Widget ID',
					'stream'
				);
			}
			self::log(
				$message,
				compact( 'title', 'name', 'widget_id', 'sidebar_id' ),
				null,
				array( $sidebar_id => $action )
			);
		}
	}

	/**
	 * Track reactivation of widgets from sidebars
	 *
	 * @param  array $old  Old sidebars widgets
	 * @param  array $new  New sidebars widgets
	 * @return void
	 */
	static protected function handle_widget_addition( $old, $new ) {
		$all_old_widget_ids = array_unique( call_user_func_array( 'array_merge', $old ) );
		$all_new_widget_ids = array_unique( call_user_func_array( 'array_merge', $new ) );
		$added_widget_ids = array_diff( $all_new_widget_ids, $all_old_widget_ids );
		foreach ( $added_widget_ids as $widget_id ) {
			$sidebar_id = '';
			foreach ( $new as $new_sidebar_id => $new_widget_ids ) {
				if ( in_array( $widget_id, $new_widget_ids ) ) {
					$sidebar_id = $new_sidebar_id;
					break;
				}
			}

			$action  = 'added';
			$title = self::get_widget_title( $widget_id );
			$name = self::get_widget_name( $widget_id );
			if ( $name && $title ) {
				$message = _x(
					'"%1$s" (%2$s) widget added',
					'1: Widget title, 2: Widget name',
					'stream'
				);
			} else if ( $name ) {
				// Empty title, but we have the name
				$message = _x(
					'%2$s widget added',
					'2: Widget name',
					'stream'
				);
			} else if ( $title ) {
				// Likely a single widget since no name is available
				$message = _x(
					'"%1$s" widget added',
					'1: Widget title',
					'stream'
				);
			} else {
				// Neither a name nor a title are available, so use the sidebar ID
				$message = _x(
					'%3$s widget added',
					'3: Widget ID',
					'stream'
				);
			}
			self::log(
				$message,
				compact( 'title', 'name', 'widget_id', 'sidebar_id' ),
				null,
				array( $sidebar_id => $action )
			);
		}
	}

	/**
	 * Track reordering of widgets
	 *
	 * @param  array $old  Old sidebars widgets
	 * @param  array $new  New sidebars widgets
	 * @return void
	 */
	static protected function handle_widget_reordering( $old, $new ) {

		$all_sidebar_ids = array_intersect( array_keys( $old ), array_keys( $new ) );
		foreach ( $all_sidebar_ids as $sidebar_id ) {
			if ( $old[ $sidebar_id ] === $new[ $sidebar_id ] ) {
				continue;
			}

			// Use intersect to ignore widget additions and removals
			$all_widget_ids = array_unique( array_merge( $old[ $sidebar_id ], $new[ $sidebar_id ] ) );
			$common_widget_ids = array_intersect( $old[ $sidebar_id ], $new[ $sidebar_id ] );
			$uncommon_widget_ids = array_diff( $all_widget_ids, $common_widget_ids );
			$new_widget_ids = array_values( array_diff( $new[ $sidebar_id ], $uncommon_widget_ids ) );
			$old_widget_ids = array_values( array_diff( $old[ $sidebar_id ], $uncommon_widget_ids ) );
			$widget_order_changed = ( $new_widget_ids !== $old_widget_ids );
			if ( $widget_order_changed ) {
				$labels = self::get_context_labels();
				$sidebar_name = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;

				$message = __( 'Widgets in "{sidebar_name}" were reordered', 'stream' );
				$message = self::apply_tpl_vars( $message, compact( 'sidebar_name' ) );
				self::log(
					$message,
					compact( 'sidebar_id' ), // @todo Do we need to store the sidebar_id in Stream meta if if is already in the context?
					null,
					array( $sidebar_id => 'sorted' )
				);
			}
		}

	}

	/**
	 * Track movement of widgets to other sidebars
	 *
	 * @param  array $old  Old sidebars widgets
	 * @param  array $new  New sidebars widgets
	 * @return void
	 */
	static protected function handle_widget_moved( $old, $new ) {

		$all_sidebar_ids = array_intersect( array_keys( $old ), array_keys( $new ) );
		foreach ( $all_sidebar_ids as $new_sidebar_id ) {
			if ( $old[ $new_sidebar_id ] === $new[ $new_sidebar_id ] ) {
				continue;
			}

			$new_widget_ids = array_diff( $new[ $new_sidebar_id ], $old[ $new_sidebar_id ] );

			foreach ( $new_widget_ids as $widget_id ) {
				// Now find the sidebar that the widget was originally located in, as long it is not wp_inactive_widgets
				$old_sidebar_id = null;
				foreach ( $old as $sidebar_id => $old_widget_ids ) {
					if ( in_array( $widget_id, $old_widget_ids ) ) {
						$old_sidebar_id = $sidebar_id;
						break;
					}
				}
				if ( ! $old_sidebar_id ) {
					continue;
				}
				assert( $old_sidebar_id !== $new_sidebar_id );

				$labels = self::get_context_labels();
				$old_sidebar_name = isset( $labels[ $new_sidebar_id ] ) ? $labels[ $new_sidebar_id ] : $new_sidebar_id;
				$new_sidebar_name = isset( $labels[ $old_sidebar_id ] ) ? $labels[ $old_sidebar_id ] : $old_sidebar_id;
				$title = self::get_widget_title( $widget_id );
				$name = self::get_widget_name( $widget_id );
				if ( $name && $title ) {
					$message = __( '"{title}" ({name}) widget moved from {old_sidebar_name} to {new_sidebar_name}', 'stream' );
				} else if ( $name ) {
					// Empty title, but we have the name
					$message = __( '{name} widget moved from {old_sidebar_name} to {new_sidebar_name}', 'stream' );
				} else if ( $title ) {
					// Likely a single widget since no name is available
					$message = __( '"{title}" widget moved from {old_sidebar_name} to {new_sidebar_name}', 'stream' );
				} else {
					// Neither a name nor a title are available, so use the sidebar ID
					$message = __( '{widget_id} widget moved from {old_sidebar_name} to {new_sidebar_name}', 'stream' );
				}

				$tpl_vars = compact( 'title', 'name', 'old_sidebar_name', 'new_sidebar_name' );
				$message = self::apply_tpl_vars( $message, $tpl_vars );
				self::log(
					$message,
					compact( 'widget_id' ),
					null,
					array(
						$new_sidebar_id => 'moved', // added
						$old_sidebar_id => 'moved', // subtracted
						// @todo add widget_id as a context?
					)
				);

			}
		}

	}

	/**
	 * Track changes to widgets
	 *
	 * @faction updated_option
	 * @param string $option_name
	 * @param array $old_value
	 * @param array $new_value
	 */
	public static function callback_updated_option( $option_name, $old_value, $new_value ) {
		// @todo What about adding widget for first time?
		// @todo What about deleting a widget?

		if ( ! preg_match( '/^widget_(.+)$/', $option_name, $matches ) || ! is_array( $new_value ) ) {
			return;
		}
		$is_multi = ! empty( $new_value['_multiwidget'] );
		$widget_id_base = $matches[1];
		$updates = array();

		if ( $is_multi ) {
			$widget_id_format = "$widget_id_base-%d";

			unset( $new_value['_multiwidget'] );
			unset( $old_value['_multiwidget'] );
			$widget_numbers = array_intersect( array_keys( $old_value ), array_keys( $new_value ) ); // ignore widgets added/removed
			foreach ( $widget_numbers as $widget_number ) {
				$new_instance = $new_value[ $widget_number ];
				$old_instance = $old_value[ $widget_number ];
				if ( $old_instance !== $new_instance ) {
					$widget_id = sprintf( $widget_id_format, $widget_number );
					$title = ! empty( $new_instance['title'] ) ? $new_instance['title'] : null;
					$name = self::get_widget_name( $widget_id );
					$sidebar_id = self::get_widget_sidebar_id( $widget_id );
					$updates[] = compact( 'widget_id', 'title', 'name', 'sidebar_id', 'old_instance' );
				}
			}
		} else {
			$widget_id = $widget_id_base;
			$name = $widget_id; // There aren't names available for single widgets
			$title = ! empty( $new_value['title'] ) ? $new_value['title'] : null;
			$sidebar_id = self::get_widget_sidebar_id( $widget_id );
			$old_instance = $old_value;
			$updates[] = compact( 'widget_id', 'title', 'name', 'sidebar_id', 'old_instance' );
		}

		foreach ( $updates as $update ) {

			if ( $update['name'] && $update['title'] ) {
				$message = _x(
					'"%1$s" (%2$s) widget updated',
					'1: Widget title, 2: Widget name',
					'stream'
				);
			} else if ( $update['name'] ) {
				// Empty title, but we have the name
				$message = _x(
					'%2$s widget updated',
					'2: Widget name',
					'stream'
				);
			} else if ( $update['title'] ) {
				// Likely a single widget since no name is available
				$message = _x(
					'"%1$s" widget updated',
					'1: Widget title',
					'stream'
				);
			} else {
				// Neither a name nor a title are available, so use the widget ID
				$message = _x(
					'%3$s widget updated',
					'3: Widget ID',
					'stream'
				);
			}
			self::log(
				$message,
				compact( 'title', 'name', 'widget_id', 'sidebar_id', 'old_instance' ),
				null,
				array( $update['sidebar_id'] => 'updated' )
			);
		}
	}

	/**
	 * Replace in $message any $tpl_vars array keys bounded by curly-braces,
	 * supplying the $tpl_vars array values in their place. A saner approach
	 * than using vsprintf.
	 *
	 * @param string $message
	 * @param array $tpl_vars
	 * @return string
	 */
	public static function apply_tpl_vars( $message, array $tpl_vars ) {
		$tpl_vars = array_filter( $tpl_vars, function ( $v ) { return is_string( $v ) || is_numeric( $v ); } );
		$message = str_replace(
			array_map( function ( $m ) { return '{' . $m . '}'; }, array_keys( $tpl_vars ) ),
			array_values( $tpl_vars ),
			$message
		);
		return $message;
	}

	/**
	 * @param string $widget_id
	 * @return string
	 */
	public static function get_widget_title( $widget_id ) {
		$instance = self::get_widget_instance( $widget_id );
		return ! empty( $instance['title'] ) ? $instance['title'] : null;
	}

	/**
	 * @param string $widget_id
	 * @return string|null
	 */
	public static function get_widget_name( $widget_id ) {
		$widget_obj = self::get_widget_object( $widget_id );
		return $widget_obj ? $widget_obj->name : null;
	}

	/**
	 * @param string $widget_id
	 * @return WP_Widget|null
	 */
	public static function get_widget_object( $widget_id ) {
		global $wp_registered_widget_controls, $wp_widget_factory;
		if ( ! isset( $wp_registered_widget_controls[ $widget_id ] ) || empty( $wp_registered_widget_controls[ $widget_id ]['id_base'] ) ) {
			return null;
		}

		$id_base = $wp_registered_widget_controls[ $widget_id ]['id_base'];
		$id_base_to_widget_class_map = array_combine(
			wp_list_pluck( $wp_widget_factory->widgets, 'id_base' ),
			array_keys( $wp_widget_factory->widgets )
		);

		if ( ! isset( $id_base_to_widget_class_map[ $id_base ] ) ) {
			return null;
		}

		return $wp_widget_factory->widgets[ $id_base_to_widget_class_map[ $id_base ] ];
	}

	/**
	 * Returns widget instance settings
	 *
	 * @param  string $widget_id  Widget ID, ex: pages-1
	 * @return array|null         Widget instance
	 */
	public static function get_widget_instance( $widget_id ) {
		$instance = null;

		$widget_obj = self::get_widget_object( $widget_id );
		if ( $widget_obj && ! empty( $widget_obj->params[0]['number'] ) ) {
			$settings = $widget_obj->get_settings();
			$multi_number = $widget_obj->params[0]['number'];
			if ( isset( $settings[ $multi_number ] ) && ! empty( $settings[ $multi_number ]['title'] ) ) {
				$instance = $settings[ $multi_number ];
			}
		} else {
			// Single widgets, try our best guess at the option used
			$potential_instance = get_option( "widget_{$widget_id}" );
			if ( ! empty( $potential_instance ) && ! empty( $potential_instance['title'] ) ) {
				$instance = $potential_instance;
			}
		}
		return $instance;
	}

	/**
	 * Get global sidebars widgets
	 *
	 * @return array
	 */
	public static function get_sidebars_widgets() {
		/**
		 * Filter allows for insertion of sidebar widgets
		 * @todo Do we need this filter?
		 *
		 * @param  array  Sidebar Widgets in Options table
		 * @param  array  Inserted Sidebar Widgets
		 * @return array  Array of updated Sidebar Widgets
		 */
		return apply_filters( 'sidebars_widgets', get_option( 'sidebars_widgets', array() ) );
	}

	/**
	 * Return the sidebar of a certain widget, based on widget_id
	 *
	 * @param  string $widget_id  Widget id, ex: pages-1
	 * @return string             Sidebar id
	 */
	public static function get_widget_sidebar_id( $widget_id ) {
		$sidebars_widgets = self::get_sidebars_widgets();
		foreach ( $sidebars_widgets as $sidebar_id => $widget_ids ) {
			if ( in_array( $widget_id, $widget_ids ) ) {
				return $sidebar_id;
			}
		}
		return 'orphaned_widgets';
	}

}
