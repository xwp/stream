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
		'sidebar_admin_setup',
		'wp_ajax_widgets-order',
		'widget_update_callback',
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

		$labels['wp_inactive_widgets'] = esc_html__( 'Inactive Widgets', 'stream' );

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
				$links[ __( 'Edit Widget Area', 'stream' ) ] = admin_url( 'widgets.php#' . $sidebar );
			}
		}

		return $links;
	}

	/**
	 * Tracks addition/deletion/deactivation of widgets from sidebars
	 *
	 * @action update_option_sidebars_widgets
	 * @param  array $old  Old sidebars
	 * @param  array $new  New sidebars
	 * @return void
	 */
	public static function callback_update_option_sidebars_widgets( $old, $new ) {

		// Disable listener if we're switching themes
		if ( did_action( 'after_switch_theme' ) ) {
			return;
		}

		global $wp_stream_widget_order_operation;

		$widget_id = null;
		$sidebar   = null;

		if ( $deactivated = array_diff( $new['wp_inactive_widgets'], $old['wp_inactive_widgets'] ) ) {
			$action  = 'deactivated';
			$message = _x(
				'"%1$s" has been deactivated',
				'1: Widget title',
				'stream'
			);

			// It is not always the 0th key value. If a widget is dropped after any other widget in the Inactive area;
			// then the difference would be on a non-zero key.
			$diff_ids  = array_values( $deactivated );
			$widget_id = $diff_ids[0];
			$sidebar   = $old;

			list( $id_base, $name, $title, $sidebar, $sidebar_name ) = array_values( self::get_widget_info( $widget_id, $sidebar ) );

			self::log(
				$message,
				compact( 'title', 'sidebar_name', 'id_base', 'widget_id', 'sidebar' ),
				null,
				array( 'wp_inactive_widgets' => $action )
			);

			$wp_stream_widget_order_operation = null;

			return;
		}

		if ( ! $widget_id ) {
			foreach ( $new as $sidebar_id => $new_widgets ){
				if (
					( ! isset( $old[ $sidebar_id ] ) )
					||
					( ! isset( $new[ $sidebar_id ] ) )
					||
					( ! is_array( $old[ $sidebar_id ] ) )
					||
					( ! is_array( $new_widgets ) )
					) {
					break; // Switching themes ?, do not return so order operation is logged
				}
				$old_widgets = $old[ $sidebar_id ];

				// Added ?
				if ( $changed = array_diff( $new_widgets, $old_widgets ) ) {
					$action    = 'added';
					$message   = _x(
						'"%1$s" has been added to "%2$s"',
						'1: Widget title, 2: Sidebar name',
						'stream'
					);
					$widget_id = reset( $changed );
					$sidebar   = $new;
				}
				// Removed
				elseif ( $changed = array_diff( $old_widgets, $new_widgets ) ) {
					$action    = 'deleted';
					$message   = _x(
						'"%1$s" has been deleted from "%2$s"',
						'1: Widget title, 2: Sidebar name',
						'stream'
					);
					$widget_id = reset( $changed );
					$sidebar   = $old;
				}

				if ( ! $widget_id ) {
					continue;
				}

				$wp_stream_widget_order_operation = null;

				list( $id_base, $name, $title, $sidebar, $sidebar_name ) = array_values( self::get_widget_info( $widget_id, $sidebar ) );

				self::log(
					$message,
					compact( 'title', 'sidebar_name', 'id_base', 'widget_id', 'sidebar' ),
					null,
					array( $sidebar => $action )
				);

				$widget_id = null;
			}
		}

		// Did anything happen ? if not, just record the reorder log entry
		if ( $wp_stream_widget_order_operation ) {
			call_user_func_array( array( __CLASS__, 'log' ), $wp_stream_widget_order_operation );
		}

	}

	/**
	 * Tracks widget instance updates
	 *
	 * @filter widget_update_callback
	 * @param $instance
	 * @param $new_instance
	 * @param $old_instance
	 * @param $widget
	 * @return array
	 */
	public static function callback_widget_update_callback( $instance, $new_instance, $old_instance, $widget ) {
		global $wp_registered_sidebars;

		$id_base   = $widget->id_base;
		$widget_id = $widget->id;

		list( $id_base, $name, $title, $sidebar, $sidebar_name ) = array_values( self::get_widget_info( $widget_id, false ) );
		$title = isset( $new_instance['title'] ) ? $new_instance['title'] : null;

		// If it wasn't assigned to a sidebar, then its a new thing, skip it
		if ( $sidebar_name ) {
			self::log(
				_x(
					'"%1$s" in "%2$s" updated',
					'1: Widget title, 2: Sidebar name',
					'stream'
				),
				compact( 'name', 'sidebar_name', 'title', 'id_base', 'sidebar', 'widget_id', 'new_instance', 'old_instance' ),
				null,
				array( $sidebar => 'updated' )
			);
		}

		return $instance;
	}

	/**
	 * Tracks reordering of widgets
	 *
	 * @action wp_ajax_widgets_order
	 * @return void
	 */
	public static function callback_wp_ajax_widgets_order() {
		global $wp_stream_widget_order_operation;

		// If this was a widget update, skip adding a new record
		if ( did_action( 'widget_update_callback' ) ) {
			return;
		}

		$labels = self::get_context_labels();
		$old    = self::get_sidebar_widgets();

		unset( $old['array_version'] );

		$new = $_POST['sidebars'];

		foreach ( $new as $sidebar_id => $widget_ids ) {

			$widget_ids         = preg_replace( '#(widget-\d+_)#', '', $widget_ids );
			$new[ $sidebar_id ] = array_filter( explode( ',', $widget_ids ) );

			if ( $new[ $sidebar_id ] !== $old[ $sidebar_id ] ) {
				$changed = $sidebar_id;
				break;
			}
		}

		if ( isset( $changed ) ) {
			$sidebar      = $changed;
			$sidebar_name = isset( $labels[ $sidebar ] ) ? $labels[ $sidebar ] : esc_html__( 'Widgets', 'stream' );

			// Saving this in a global var, so it can be accessed and
			// executed by self::callback_update_option_sidebars_widgets
			// in case this is ONLY a reorder process
			$wp_stream_widget_order_operation = array(
				_x( 'Widgets in "%s" were reordered', 'Sidebar name', 'stream' ),
				compact( 'sidebar_name', 'sidebar' ),
				null,
				array( $sidebar => 'sorted' ),
			);
		}
	}

	/**
	 * Returns widget info based on widget id
	 *
	 * @param  integer $id       Widget ID, ex: pages-1
	 * @param  array   $sidebars Existing sidebars to search in
	 * @return array             array( $id_base, $name, $title, $sidebar, $sidebar_name, $widget_class )
	 */
	public static function get_widget_info( $id, $sidebars = array() ) {
		global $wp_registered_widgets, $wp_widget_factory;

		$ids = array_combine(
			wp_list_pluck( $wp_widget_factory->widgets, 'id_base' ),
			array_keys( $wp_widget_factory->widgets )
		);

		$labels  = self::get_context_labels();
		$id_base = preg_match( '#(.*)-(\d+)$#', $id, $matches ) ? $matches[1] : null;
		$number  = $matches[2];
		$name    = $wp_widget_factory->widgets[ $ids[ $id_base ] ]->name;

		$settings = self::get_widget_settings( $id );
		$title    = ! empty( $settings['title'] ) ? $settings['title'] : $name;

		$sidebar      = null;
		$sidebar_name = null;

		if ( false === $sidebars ) {
			$sidebars = self::get_sidebar_widgets();
		}

		foreach ( $sidebars as $_sidebar_id => $_sidebar ) {
			if ( is_array( $_sidebar ) && in_array( $id, $_sidebar ) ) {
				$sidebar      = $_sidebar_id;
				$sidebar_name = isset( $labels[ $sidebar ] ) ? $labels[ $sidebar ] : esc_html__( 'Widgets', 'stream' );
				break;
			}
		}

		return array( $id_base, $name, $title, $sidebar, $sidebar_name, $ids[ $id_base ] );
	}

	/**
	 * Returns widget instance settings
	 *
	 * @param  string $id  Widget ID, ex: pages-1
	 * @return array       Widget instance
	 */
	public static function get_widget_settings( $id ) {
		global $wp_widget_factory, $wp_registered_widgets, $wp_widget_factory, $wp_registered_sidebars;

		$id_base = preg_match( '#(.*)-(\d+)#', $id, $matches ) ? $matches[1] : null;
		$number  = $matches[2];

		$ids = array_combine(
			wp_list_pluck( $wp_widget_factory->widgets, 'id_base' ),
			array_keys( $wp_widget_factory->widgets )
		);

		$instance = $wp_widget_factory->widgets[ $ids[ $id_base ] ]->get_settings();

		return isset( $instance[ $number ] ) ? $instance[ $number ] : array();
	}

	/**
	 * Get global sidebars widgets
	 *
	 * @return array
	 */
	public static function get_sidebar_widgets() {
		/**
		 * Filter allows for insertion of sidebar widgets
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
	 * @param  string $id Widget id, ex: pages-1
	 * @return string     Sidebar id
	 */
	public static function get_widget_sidebar( $id ) {
		$sidebars = self::get_sidebar_widgets();

		foreach ( $sidebars as $sidebar_id => $bar ) {
			if ( in_array( $id, $bar ) ) {
				return $sidebar_id;
			}
		}

		return null;
	}

}
