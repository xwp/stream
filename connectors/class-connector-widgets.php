<?php
/**
 * Connector for Widgets
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Connector_Widgets
 */
class Connector_Widgets extends Connector {

	/**
	 * Whether or not 'created' and 'deleted' actions should be logged. Normally
	 * the sidebar 'added' and 'removed' actions will correspond with these.
	 * See note below with usage.
	 *
	 * @var bool
	 */
	public $verbose_widget_created_deleted_actions = false;

	/**
	 * Connector slug
	 *
	 * @var string
	 */
	public $name = 'widgets';

	/**
	 * Actions registered for this connector
	 *
	 * @var array
	 */
	public $actions = array(
		'update_option_sidebars_widgets',
		'updated_option',
	);

	/**
	 * Store the initial sidebars_widgets option when the customizer does its
	 * multiple rounds of saving to the sidebars_widgets option.
	 *
	 * @var array
	 */
	protected $customizer_initial_sidebars_widgets = null;

	/**
	 * Return translated connector label
	 *
	 * @return string Translated connector label
	 */
	public function get_label() {
		return esc_html__( 'Widgets', 'stream' );
	}

	/**
	 * Return translated action labels
	 *
	 * @return array Action label translations
	 */
	public function get_action_labels() {
		return array(
			'added'       => esc_html__( 'Added', 'stream' ),
			'removed'     => esc_html__( 'Removed', 'stream' ),
			'moved'       => esc_html__( 'Moved', 'stream' ),
			'created'     => esc_html__( 'Created', 'stream' ),
			'deleted'     => esc_html__( 'Deleted', 'stream' ),
			'deactivated' => esc_html__( 'Deactivated', 'stream' ),
			'reactivated' => esc_html__( 'Reactivated', 'stream' ),
			'updated'     => esc_html__( 'Updated', 'stream' ),
			'sorted'      => esc_html__( 'Sorted', 'stream' ),
		);
	}

	/**
	 * Return translated context labels
	 *
	 * @return array Context label translations
	 */
	public function get_context_labels() {
		global $wp_registered_sidebars;

		$labels = array();

		foreach ( $wp_registered_sidebars as $sidebar ) {
			$labels[ $sidebar['id'] ] = $sidebar['name'];
		}

		$labels['wp_inactive_widgets'] = esc_html__( 'Inactive Widgets', 'stream' );
		$labels['orphaned_widgets']    = esc_html__( 'Orphaned Widgets', 'stream' );

		return $labels;
	}

	/**
	 * Add action links to Stream drop row in admin list screen
	 *
	 * @filter wp_stream_action_links_{connector}
	 *
	 * @param array  $links   Previous links registered.
	 * @param Record $record  Stream record.
	 *
	 * @return array Action links
	 */
	public function action_links( $links, $record ) {
		$sidebar = $record->get_meta( 'sidebar_id', true );
		if ( $sidebar ) {
			global $wp_registered_sidebars;

			if ( array_key_exists( $sidebar, $wp_registered_sidebars ) ) {
				$links[ esc_html__( 'Edit Widget Area', 'stream' ) ] = admin_url( 'widgets.php#' . $sidebar ); // xss ok (@todo fix WPCS rule).
			}
			// @todo Also old_sidebar_id and new_sidebar_id.
			// @todo Add Edit Widget link.
		}

		return $links;
	}

	/**
	 * Tracks addition/deletion/reordering/deactivation of widgets from sidebars
	 *
	 * @action update_option_sidebars_widgets
	 *
	 * @param array $old  Old sidebars widgets.
	 * @param array $new  New sidebars widgets.
	 *
	 * @return void
	 */
	public function callback_update_option_sidebars_widgets( $old, $new ) {
		// Disable listener if we're switching themes.
		if ( did_action( 'after_switch_theme' ) ) {
			return;
		}

		if ( did_action( 'customize_save' ) ) {
			if ( is_null( $this->customizer_initial_sidebars_widgets ) ) {
				$this->customizer_initial_sidebars_widgets = $old;
				add_action( 'customize_save_after', array( $this, 'callback_customize_save_after' ) );
			}
		} else {
			$this->handle_sidebars_widgets_changes( $old, $new );
		}
	}

	/**
	 * Since the sidebars_widgets may get updated multiple times when saving
	 * changes to Widgets in the Customizer, defer handling the changes until
	 * customize_save_after.
	 *
	 * @see callback_update_option_sidebars_widgets()
	 */
	public function callback_customize_save_after() {
		$old_sidebars_widgets = $this->customizer_initial_sidebars_widgets;
		$new_sidebars_widgets = get_option( 'sidebars_widgets' );

		$this->handle_sidebars_widgets_changes( $old_sidebars_widgets, $new_sidebars_widgets );
	}

	/**
	 * Processes tracked widget actions
	 *
	 * @param array $old  Old sidebar widgets.
	 * @param array $new  New sidebar widgets.
	 */
	protected function handle_sidebars_widgets_changes( $old, $new ) {
		unset( $old['array_version'] );
		unset( $new['array_version'] );

		if ( $old === $new ) {
			return;
		}

		$this->handle_deactivated_widgets( $old, $new );
		$this->handle_reactivated_widgets( $old, $new );
		$this->handle_widget_removal( $old, $new );
		$this->handle_widget_addition( $old, $new );
		$this->handle_widget_reordering( $old, $new );
		$this->handle_widget_moved( $old, $new );
	}

	/**
	 * Track deactivation of widgets from sidebars
	 *
	 * @param array $old  Old sidebars widgets.
	 * @param array $new  New sidebars widgets.
	 * @return void
	 */
	protected function handle_deactivated_widgets( $old, $new ) {
		$new_deactivated_widget_ids = array_diff( $new['wp_inactive_widgets'], $old['wp_inactive_widgets'] );

		foreach ( $new_deactivated_widget_ids as $widget_id ) {
			$sidebar_id = '';

			foreach ( $old as $old_sidebar_id => $old_widget_ids ) {
				if ( in_array( $widget_id, $old_widget_ids, true ) ) {
					$sidebar_id = $old_sidebar_id;
					break;
				}
			}

			$action       = 'deactivated';
			$name         = $this->get_widget_name( $widget_id );
			$title        = $this->get_widget_title( $widget_id );
			$labels       = $this->get_context_labels();
			$sidebar_name = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;

			if ( $name && $title ) {
				/* translators: %1$s: a widget name, %2$s: a widget title, %3$s: a sidebar name (e.g. "Archives", "Browse", "Footer Area 1") */
				$message = _x( '%1$s widget named "%2$s" from "%3$s" deactivated', '1: Name, 2: Title, 3: Sidebar Name', 'stream' );
			} elseif ( $name ) {
				// Empty title, but we have the name.
				/* translators: %1$s: a widget name, %3$s: a sidebar name (e.g. "Archives", "Footer Area 1") */
				$message = _x( '%1$s widget from "%3$s" deactivated', '1: Name, 3: Sidebar Name', 'stream' );
			} elseif ( $title ) {
				// Likely a single widget since no name is available.
				/* translators: %1$s: a widget title, %2$s: a sidebar name (e.g. "Browse", "Footer Area 1") */
				$message = _x( 'Unknown widget type named "%2$s" from "%3$s" deactivated', '2: Title, 3: Sidebar Name', 'stream' );
			} else {
				// Neither a name nor a title are available, so use the widget ID.
				/* translators: %4$s: a widget ID, %3$s: a sidebar name (e.g. "42", "Footer Area 1") */
				$message = _x( '%4$s widget from "%3$s" deactivated', '4: Widget ID, 3: Sidebar Name', 'stream' );
			}

			$message = sprintf( $message, $name, $title, $sidebar_name, $widget_id );

			$this->log(
				$message,
				compact( 'title', 'name', 'widget_id', 'sidebar_id' ),
				null,
				'wp_inactive_widgets',
				$action
			);
		}
	}

	/**
	 * Track reactivation of widgets from sidebars
	 *
	 * @param array $old  Old sidebars widgets.
	 * @param array $new  New sidebars widgets.
	 * @return void
	 */
	protected function handle_reactivated_widgets( $old, $new ) {
		$new_reactivated_widget_ids = array_diff( $old['wp_inactive_widgets'], $new['wp_inactive_widgets'] );

		foreach ( $new_reactivated_widget_ids as $widget_id ) {
			$sidebar_id = '';

			foreach ( $new as $new_sidebar_id => $new_widget_ids ) {
				if ( in_array( $widget_id, $new_widget_ids, true ) ) {
					$sidebar_id = $new_sidebar_id;
					break;
				}
			}

			$action = 'reactivated';
			$name   = $this->get_widget_name( $widget_id );
			$title  = $this->get_widget_title( $widget_id );

			if ( $name && $title ) {
				/* translators: %1$s: a widget name, %2$s: a widget title (e.g. "Archives", "Browse") */
				$message = _x( '%1$s widget named "%2$s" reactivated', '1: Name, 2: Title', 'stream' );
			} elseif ( $name ) {
				// Empty title, but we have the name.
				/* translators: %1$s: a widget name (e.g. "Archives") */
				$message = _x( '%1$s widget reactivated', '1: Name', 'stream' );
			} elseif ( $title ) {
				// Likely a single widget since no name is available.
				/* translators: %2$s: a widget title (e.g. "Browse") */
				$message = _x( 'Unknown widget type named "%2$s" reactivated', '2: Title', 'stream' );
			} else {
				// Neither a name nor a title are available, so use the widget ID.
				/* translators: %3$s: a widget ID (e.g. "42") */
				$message = _x( '%3$s widget reactivated', '3: Widget ID', 'stream' );
			}

			$message = sprintf( $message, $name, $title, $widget_id );

			$this->log(
				$message,
				compact( 'title', 'name', 'widget_id', 'sidebar_id' ),
				null,
				$sidebar_id,
				$action
			);
		}
	}

	/**
	 * Track deletion of widgets from sidebars
	 *
	 * @param array $old  Old sidebars widgets.
	 * @param array $new  New sidebars widgets.
	 * @return void
	 */
	protected function handle_widget_removal( $old, $new ) {
		$all_old_widget_ids = array_unique( call_user_func_array( 'array_merge', array_values( $old ) ) );
		$all_new_widget_ids = array_unique( call_user_func_array( 'array_merge', array_values( $new ) ) );
		// @todo In the customizer, moving widgets to other sidebars is problematic because each sidebar is registered as a separate setting; so we need to make sure that all $_POST['customized'] are applied?
		// @todo The widget option is getting updated before the sidebars_widgets are updated, so we need to hook into the option update to try to cache any deletions for future lookup

		$deleted_widget_ids = array_diff( $all_old_widget_ids, $all_new_widget_ids );

		foreach ( $deleted_widget_ids as $widget_id ) {
			$sidebar_id = '';

			foreach ( $old as $old_sidebar_id => $old_widget_ids ) {
				if ( in_array( $widget_id, $old_widget_ids, true ) ) {
					$sidebar_id = $old_sidebar_id;
					break;
				}
			}

			$action       = 'removed';
			$name         = $this->get_widget_name( $widget_id );
			$title        = $this->get_widget_title( $widget_id );
			$labels       = $this->get_context_labels();
			$sidebar_name = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;

			if ( $name && $title ) {
				/* translators: %1$s: a widget name, %2$s: a widget title, %3$s: a sidebar name (e.g. "Archives", "Browse", "Footer Area 1") */
				$message = _x( '%1$s widget named "%2$s" removed from "%3$s"', '1: Name, 2: Title, 3: Sidebar Name', 'stream' );
			} elseif ( $name ) {
				// Empty title, but we have the name.
				/* translators: %1$s: a widget name, %3$s: a sidebar name (e.g. "Archives", "Footer Area 1") */
				$message = _x( '%1$s widget removed from "%3$s"', '1: Name, 3: Sidebar Name', 'stream' );
			} elseif ( $title ) {
				// Likely a single widget since no name is available.
				/* translators: %2$s: a widget title, %3$s: a sidebar name (e.g. "Browse", "Footer Area 1") */
				$message = _x( 'Unknown widget type named "%2$s" removed from "%3$s"', '2: Title, 3: Sidebar Name', 'stream' );
			} else {
				// Neither a name nor a title are available, so use the widget ID.
				/* translators: %4$s: a widget ID, %3$s: a sidebar name (e.g. "42", "Footer Area 1") */
				$message = _x( '%4$s widget removed from "%3$s"', '4: Widget ID, 3: Sidebar Name', 'stream' );
			}

			$message = sprintf( $message, $name, $title, $sidebar_name, $widget_id );

			$this->log(
				$message,
				compact( 'widget_id', 'sidebar_id' ),
				null,
				$sidebar_id,
				$action
			);
		}
	}

	/**
	 * Track reactivation of widgets from sidebars
	 *
	 * @param array $old  Old sidebars widgets.
	 * @param array $new  New sidebars widgets.
	 * @return void
	 */
	protected function handle_widget_addition( $old, $new ) {
		$all_old_widget_ids = array_unique( call_user_func_array( 'array_merge', array_values( $old ) ) );
		$all_new_widget_ids = array_unique( call_user_func_array( 'array_merge', array_values( $new ) ) );
		$added_widget_ids   = array_diff( $all_new_widget_ids, $all_old_widget_ids );

		foreach ( $added_widget_ids as $widget_id ) {
			$sidebar_id = '';

			foreach ( $new as $new_sidebar_id => $new_widget_ids ) {
				if ( in_array( $widget_id, $new_widget_ids, true ) ) {
					$sidebar_id = $new_sidebar_id;
					break;
				}
			}

			$action       = 'added';
			$name         = $this->get_widget_name( $widget_id );
			$title        = $this->get_widget_title( $widget_id );
			$labels       = $this->get_context_labels();
			$sidebar_name = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;

			if ( $name && $title ) {
				/* translators: %1$s: a widget name, %2$s: a widget title, %3$s: a sidebar name (e.g. "Archives", "Browse", "Footer Area 1") */
				$message = _x( '%1$s widget named "%2$s" added to "%3$s"', '1: Name, 2: Title, 3: Sidebar Name', 'stream' );
			} elseif ( $name ) {
				// Empty title, but we have the name.
				/* translators: %1$s: a widget name, %3$s: a sidebar name (e.g. "Archives", "Footer Area 1") */
				$message = _x( '%1$s widget added to "%3$s"', '1: Name, 3: Sidebar Name', 'stream' );
			} elseif ( $title ) {
				// Likely a single widget since no name is available.
				/* translators: %2$s: a widget title, %3$s: a sidebar name (e.g. "Browse", "Footer Area 1") */
				$message = _x( 'Unknown widget type named "%2$s" added to "%3$s"', '2: Title, 3: Sidebar Name', 'stream' );
			} else {
				// Neither a name nor a title are available, so use the widget ID.
				/* translators: %4$s: a widget ID, %3$s: a sidebar name (e.g. "42", "Footer Area 1") */
				$message = _x( '%4$s widget added to "%3$s"', '4: Widget ID, 3: Sidebar Name', 'stream' );
			}

			$message = sprintf( $message, $name, $title, $sidebar_name, $widget_id );

			$this->log(
				$message,
				compact( 'widget_id', 'sidebar_id' ), // @todo Do we care about sidebar_id in meta if it is already context? But there is no 'context' for what the context signifies
				null,
				$sidebar_id,
				$action
			);
		}
	}

	/**
	 * Track reordering of widgets
	 *
	 * @param array $old  Old sidebars widgets.
	 * @param array $new  New sidebars widgets.
	 * @return void
	 */
	protected function handle_widget_reordering( $old, $new ) {
		$all_sidebar_ids = array_intersect( array_keys( $old ), array_keys( $new ) );

		foreach ( $all_sidebar_ids as $sidebar_id ) {
			if ( $old[ $sidebar_id ] === $new[ $sidebar_id ] ) {
				continue;
			}

			// Use intersect to ignore widget additions and removals.
			$all_widget_ids       = array_unique( array_merge( $old[ $sidebar_id ], $new[ $sidebar_id ] ) );
			$common_widget_ids    = array_intersect( $old[ $sidebar_id ], $new[ $sidebar_id ] );
			$uncommon_widget_ids  = array_diff( $all_widget_ids, $common_widget_ids );
			$new_widget_ids       = array_values( array_diff( $new[ $sidebar_id ], $uncommon_widget_ids ) );
			$old_widget_ids       = array_values( array_diff( $old[ $sidebar_id ], $uncommon_widget_ids ) );
			$widget_order_changed = ( $new_widget_ids !== $old_widget_ids );

			if ( $widget_order_changed ) {
				$labels         = $this->get_context_labels();
				$sidebar_name   = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;
				$old_widget_ids = $old[ $sidebar_id ];

				/* translators: %s: a sidebar name (e.g. "Footer Area 1") */
				$message = _x( 'Widgets reordered in "%s"', 'Sidebar name', 'stream' );
				$message = sprintf( $message, $sidebar_name );

				$this->log(
					$message,
					compact( 'sidebar_id', 'old_widget_ids' ),
					null,
					$sidebar_id,
					'sorted'
				);
			}
		}

	}

	/**
	 * Track movement of widgets to other sidebars
	 *
	 * @param array $old  Old sidebars widgets.
	 * @param array $new  New sidebars widgets.
	 * @return void
	 */
	protected function handle_widget_moved( $old, $new ) {
		$all_sidebar_ids = array_intersect( array_keys( $old ), array_keys( $new ) );

		foreach ( $all_sidebar_ids as $new_sidebar_id ) {
			if ( $old[ $new_sidebar_id ] === $new[ $new_sidebar_id ] ) {
				continue;
			}

			$new_widget_ids = array_diff( $new[ $new_sidebar_id ], $old[ $new_sidebar_id ] );

			foreach ( $new_widget_ids as $widget_id ) {
				// Now find the sidebar that the widget was originally located in, as long it is not wp_inactive_widgets.
				$old_sidebar_id = null;
				foreach ( $old as $sidebar_id => $old_widget_ids ) {
					if ( in_array( $widget_id, $old_widget_ids, true ) ) {
						$old_sidebar_id = $sidebar_id;
						break;
					}
				}

				if ( ! $old_sidebar_id || 'wp_inactive_widgets' === $old_sidebar_id || 'wp_inactive_widgets' === $new_sidebar_id ) {
					continue;
				}

				assert( $old_sidebar_id !== $new_sidebar_id );

				$name             = $this->get_widget_name( $widget_id );
				$title            = $this->get_widget_title( $widget_id );
				$labels           = $this->get_context_labels();
				$old_sidebar_name = isset( $labels[ $old_sidebar_id ] ) ? $labels[ $old_sidebar_id ] : $old_sidebar_id;
				$new_sidebar_name = isset( $labels[ $new_sidebar_id ] ) ? $labels[ $new_sidebar_id ] : $new_sidebar_id;

				if ( $name && $title ) {
					/* translators: %1$s: a widget name, %2$s: a widget title, %4$s: a sidebar name, %5$s: another sidebar name (e.g. "Archives", "Browse", "Footer Area 1", "Footer Area 2") */
					$message = _x( '%1$s widget named "%2$s" moved from "%4$s" to "%5$s"', '1: Name, 2: Title, 4: Old Sidebar Name, 5: New Sidebar Name', 'stream' );
				} elseif ( $name ) {
					// Empty title, but we have the name.
					/* translators: %1$s: a widget name, %4$s: a sidebar name, %5$s: another sidebar name (e.g. "Archives", "Footer Area 1", "Footer Area 2") */
					$message = _x( '%1$s widget moved from "%4$s" to "%5$s"', '1: Name, 4: Old Sidebar Name, 5: New Sidebar Name', 'stream' );
				} elseif ( $title ) {
					// Likely a single widget since no name is available.
					/* translators: %2$s: a widget title, %4$s: a sidebar name, %5$s: another sidebar name (e.g. "Browse", "Footer Area 1", "Footer Area 2") */
					$message = _x( 'Unknown widget type named "%2$s" moved from "%4$s" to "%5$s"', '2: Title, 4: Old Sidebar Name, 5: New Sidebar Name', 'stream' );
				} else {
					// Neither a name nor a title are available, so use the widget ID.
					/* translators: %3$s: a widget ID, %4$s: a sidebar name, %5$s: another sidebar name (e.g. "42", "Footer Area 1", "Footer Area 2") */
					$message = _x( '%3$s widget moved from "%4$s" to "%5$s"', '3: Widget ID, 4: Old Sidebar Name, 5: New Sidebar Name', 'stream' );
				}

				$message    = sprintf( $message, $name, $title, $widget_id, $old_sidebar_name, $new_sidebar_name );
				$sidebar_id = $new_sidebar_id;

				$this->log(
					$message,
					compact( 'widget_id', 'sidebar_id', 'old_sidebar_id' ),
					null,
					$sidebar_id,
					'moved'
				);
			}
		}

	}

	/**
	 * Track changes to widgets
	 *
	 * @action updated_option
	 *
	 * @param string $option_name  Option key.
	 * @param array  $old_value    Old value.
	 * @param array  $new_value    New value.
	 */
	public function callback_updated_option( $option_name, $old_value, $new_value ) {
		if ( ! preg_match( '/^widget_(.+)$/', $option_name, $matches ) || ! is_array( $new_value ) ) {
			return;
		}

		$is_multi       = ! empty( $new_value['_multiwidget'] );
		$widget_id_base = $matches[1];

		$creates = array();
		$updates = array();
		$deletes = array();

		if ( $is_multi ) {
			$widget_id_format = "$widget_id_base-%d";

			unset( $new_value['_multiwidget'] );
			unset( $old_value['_multiwidget'] );

			/**
			 * Created widgets
			 */
			$created_widget_numbers = array_diff( array_keys( $new_value ), array_keys( $old_value ) );

			foreach ( $created_widget_numbers as $widget_number ) {
				$instance   = $new_value[ $widget_number ];
				$widget_id  = sprintf( $widget_id_format, $widget_number );
				$name       = $this->get_widget_name( $widget_id );
				$title      = ! empty( $instance['title'] ) ? $instance['title'] : null;
				$sidebar_id = $this->get_widget_sidebar_id( $widget_id ); // @todo May not be assigned yet

				$creates[] = compact( 'name', 'title', 'widget_id', 'sidebar_id', 'instance' );
			}

			/**
			 * Updated widgets
			 */
			$updated_widget_numbers = array_intersect( array_keys( $old_value ), array_keys( $new_value ) );

			foreach ( $updated_widget_numbers as $widget_number ) {
				$new_instance = $new_value[ $widget_number ];
				$old_instance = $old_value[ $widget_number ];

				if ( $old_instance !== $new_instance ) {
					$widget_id    = sprintf( $widget_id_format, $widget_number );
					$name         = $this->get_widget_name( $widget_id );
					$title        = ! empty( $new_instance['title'] ) ? $new_instance['title'] : null;
					$sidebar_id   = $this->get_widget_sidebar_id( $widget_id );
					$labels       = $this->get_context_labels();
					$sidebar_name = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;

					$updates[] = compact( 'name', 'title', 'widget_id', 'sidebar_id', 'old_instance', 'sidebar_name' );
				}
			}

			/**
			 * Deleted widgets
			 */
			$deleted_widget_numbers = array_diff( array_keys( $old_value ), array_keys( $new_value ) );

			foreach ( $deleted_widget_numbers as $widget_number ) {
				$instance   = $old_value[ $widget_number ];
				$widget_id  = sprintf( $widget_id_format, $widget_number );
				$name       = $this->get_widget_name( $widget_id );
				$title      = ! empty( $instance['title'] ) ? $instance['title'] : null;
				$sidebar_id = $this->get_widget_sidebar_id( $widget_id ); // @todo May not be assigned anymore

				$deletes[] = compact( 'name', 'title', 'widget_id', 'sidebar_id', 'instance' );
			}
		} else {
			// Doing our best guess for tracking changes to old single widgets, assuming their options start with 'widget_'.
			$widget_id    = $widget_id_base;
			$name         = $widget_id; // There aren't names available for single widgets.
			$title        = ! empty( $new_value['title'] ) ? $new_value['title'] : null;
			$sidebar_id   = $this->get_widget_sidebar_id( $widget_id );
			$old_instance = $old_value;
			$labels       = $this->get_context_labels();
			$sidebar_name = isset( $labels[ $sidebar_id ] ) ? $labels[ $sidebar_id ] : $sidebar_id;

			$updates[] = compact( 'widget_id', 'title', 'name', 'sidebar_id', 'old_instance', 'sidebar_name' );
		}

		/**
		 * Log updated actions
		 */
		foreach ( $updates as $update ) {
			if ( $update['name'] && $update['title'] ) {
				/* translators: %1$s: a widget name, %2$s: a widget title, %3$s: a sidebar name (e.g. "Archives", "Browse", "Footer Area 1") */
				$message = _x( '%1$s widget named "%2$s" in "%3$s" updated', '1: Name, 2: Title, 3: Sidebar Name', 'stream' );
			} elseif ( $update['name'] ) {
				// Empty title, but we have the name.
				/* translators: %1$s: a widget name, %3$s: a sidebar name (e.g. "Archives", "Footer Area 1") */
				$message = _x( '%1$s widget in "%3$s" updated', '1: Name, 3: Sidebar Name', 'stream' );
			} elseif ( $update['title'] ) {
				// Likely a single widget since no name is available.
				/* translators: %2$s: a widget title, %3$s: a sidebar name (e.g. "Browse", "Footer Area 1") */
				$message = _x( 'Unknown widget type named "%2$s" in "%3$s" updated', '2: Title, 3: Sidebar Name', 'stream' );
			} else {
				// Neither a name nor a title are available, so use the widget ID.
				/* translators: %4$s: a widget ID, %3$s: a sidebar name (e.g. "42", "Footer Area 1") */
				$message = _x( '%4$s widget in "%3$s" updated', '4: Widget ID, 3: Sidebar Name', 'stream' );
			}

			$message = sprintf( $message, $update['name'], $update['title'], $update['sidebar_name'], $update['widget_id'] );

			unset( $update['title'], $update['name'] );

			$this->log(
				$message,
				$update,
				null,
				$update['sidebar_id'],
				'updated'
			);
		}

		/**
		 * In the normal case, widgets are never created or deleted in a vacuum.
		 * Created widgets are immediately assigned to a sidebar, and deleted
		 * widgets are immediately removed from their assigned sidebar. If,
		 * however, widget instances get manipulated programmatically, it is
		 * possible that they could be orphaned, in which case the following
		 * actions would be useful to log.
		 */
		if ( $this->verbose_widget_created_deleted_actions ) {
			// We should only do these if not captured by an update to the sidebars_widgets option.
			/**
			 * Log created actions
			 */
			foreach ( $creates as $create ) {
				if ( $create['name'] && $create['title'] ) {
					/* translators: %1$s: a widget name, %2$s: a widget title (e.g. "Archives", "Browse") */
					$message = _x( '%1$s widget named "%2$s" created', '1: Name, 2: Title', 'stream' );
				} elseif ( $create['name'] ) {
					// Empty title, but we have the name.
					/* translators: %1$s: a widget name (e.g. "Archives") */
					$message = _x( '%1$s widget created', '1: Name', 'stream' );
				} elseif ( $create['title'] ) {
					// Likely a single widget since no name is available.
					/* translators: %2$s: a widget title (e.g. "Browse") */
					$message = _x( 'Unknown widget type named "%2$s" created', '2: Title', 'stream' );
				} else {
					// Neither a name nor a title are available, so use the widget ID.
					/* translators: %3$s: a widget ID (e.g. "42") */
					$message = _x( '%3$s widget created', '3: Widget ID', 'stream' );
				}

				$message = sprintf( $message, $create['name'], $create['title'], $create['widget_id'] );

				unset( $create['title'], $create['name'] );

				$this->log(
					$message,
					$create,
					null,
					$create['sidebar_id'],
					'created'
				);
			}

			/**
			 * Log deleted actions
			 */
			foreach ( $deletes as $delete ) {
				if ( $delete['name'] && $delete['title'] ) {
					/* translators: %1$s: a widget name, %2$s: a widget title (e.g. "Archives", "Browse") */
					$message = _x( '%1$s widget named "%2$s" deleted', '1: Name, 2: Title', 'stream' );
				} elseif ( $delete['name'] ) {
					// Empty title, but we have the name.
					/* translators: %1$s: a widget name (e.g. "Archives") */
					$message = _x( '%1$s widget deleted', '1: Name', 'stream' );
				} elseif ( $delete['title'] ) {
					// Likely a single widget since no name is available.
					/* translators: %2$s: a widget title (e.g. "Browse") */
					$message = _x( 'Unknown widget type named "%2$s" deleted', '2: Title', 'stream' );
				} else {
					// Neither a name nor a title are available, so use the widget ID.
					/* translators: %3$s: a widget ID (e.g. "42") */
					$message = _x( '%3$s widget deleted', '3: Widget ID', 'stream' );
				}

				$message = sprintf( $message, $delete['name'], $delete['title'], $delete['widget_id'] );

				unset( $delete['title'], $delete['name'] );

				$this->log(
					$message,
					$delete,
					null,
					$delete['sidebar_id'],
					'deleted'
				);
			}
		}
	}

	/**
	 * Returns widget title.
	 *
	 * @param string $widget_id  Widget instance ID.
	 *
	 * @return string
	 */
	public function get_widget_title( $widget_id ) {
		$instance = $this->get_widget_instance( $widget_id );
		return ! empty( $instance['title'] ) ? $instance['title'] : null;
	}

	/**
	 * Returns widget name.
	 *
	 * @param string $widget_id  Widget instance ID.
	 *
	 * @return string|null
	 */
	public function get_widget_name( $widget_id ) {
		$widget_obj = $this->get_widget_object( $widget_id );
		return $widget_obj ? $widget_obj->name : null;
	}

	/**
	 * Parses widget instance ID and widget type data.
	 *
	 * @param string $widget_id  Widget instance ID.
	 *
	 * @return array|null
	 */
	public function parse_widget_id( $widget_id ) {
		if ( preg_match( '/^(.+)-(\d+)$/', $widget_id, $matches ) ) {
			return array(
				'id_base'       => $matches[1],
				'widget_number' => intval( $matches[2] ),
			);
		} else {
			return null;
		}
	}

	/**
	 * Returns widget object.
	 *
	 * @param string $widget_id  Widget instance ID.
	 *
	 * @return \WP_Widget|null
	 */
	public function get_widget_object( $widget_id ) {
		global $wp_widget_factory;

		$parsed_widget_id = $this->parse_widget_id( $widget_id );

		if ( ! $parsed_widget_id ) {
			return null;
		}

		$id_base = $parsed_widget_id['id_base'];

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
	 * @param string $widget_id  Widget ID, ex: pages-1.
	 *
	 * @return array|null Widget instance
	 */
	public function get_widget_instance( $widget_id ) {
		$instance         = null;
		$parsed_widget_id = $this->parse_widget_id( $widget_id );
		$widget_obj       = $this->get_widget_object( $widget_id );

		if ( $widget_obj && $parsed_widget_id ) {
			$settings     = $widget_obj->get_settings();
			$multi_number = $parsed_widget_id['widget_number'];

			if ( isset( $settings[ $multi_number ] ) && ! empty( $settings[ $multi_number ]['title'] ) ) {
				$instance = $settings[ $multi_number ];
			}
		} else {
			// Single widgets, try our best guess at the option used.
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
	public function get_sidebars_widgets() {
		/**
		 * Filter allows for insertion of sidebar widgets
		 *
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
	 * @param string $widget_id  Widget id, ex: pages-1.
	 *
	 * @return string Sidebar id
	 */
	public function get_widget_sidebar_id( $widget_id ) {
		$sidebars_widgets = $this->get_sidebars_widgets();

		unset( $sidebars_widgets['array_version'] );

		foreach ( $sidebars_widgets as $sidebar_id => $widget_ids ) {
			if ( in_array( $widget_id, $widget_ids, true ) ) {
				return $sidebar_id;
			}
		}

		return 'orphaned_widgets';
	}
}
