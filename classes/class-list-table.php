<?php
namespace WP_Stream;

class List_Table extends \WP_List_Table {
	/**
	 * Hold Plugin class
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 * @param array $args
	 */
	function __construct( $plugin, $args = array() ) {
		$this->plugin = $plugin;

		$screen_id = isset( $args['screen'] ) ? $args['screen'] : null;

		/**
		 * Filter the list table screen ID
		 *
		 * @return string
		 */
		$screen_id = apply_filters( 'wp_stream_list_table_screen_id', $screen_id );

		parent::__construct(
			array(
				'post_type' => 'stream',
				'plural'    => 'records',
				'screen'    => $screen_id,
			)
		);

		add_screen_option(
			'per_page',
			array(
				'default' => 20,
				'label'   => __( 'Records per page', 'stream' ),
				'option'  => 'edit_stream_per_page',
			)
		);

		// Check for default hidden columns
		$this->get_hidden_columns();

		add_filter( 'screen_settings', array( $this, 'screen_controls' ), 10, 2 );
		add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );

		set_screen_options();
	}

	function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			echo $this->filters_form(); //xss ok
		}
	}

	function no_items() {
		?>
		<div class="stream-list-table-no-items">
			<p><?php esc_html_e( 'Sorry, no activity records were found.', 'stream' ) ?></p>
		</div>
		<?php
	}

	function get_columns() {
		/**
		 * Allows devs to add new columns to table
		 *
		 * @return array
		 */
		return apply_filters(
			'wp_stream_list_table_columns',
			array(
				'date'    => __( 'Date', 'stream' ),
				'summary' => __( 'Summary', 'stream' ),
				'user_id' => __( 'User', 'stream' ),
				'context' => __( 'Context', 'stream' ),
				'action'  => __( 'Action', 'stream' ),
				'ip'      => __( 'IP Address', 'stream' ),
			)
		);
	}

	function get_sortable_columns() {
		return array(
			'date' => array( 'date', false ),
		);
	}

	function get_hidden_columns() {
		if ( ! $user = wp_get_current_user() ) {
			return array();
		}

		// Directly checking the user meta; to check whether user has changed screen option or not
		$hidden = $this->plugin->admin->get_user_meta( $user->ID, 'manage' . $this->screen->id . 'columnshidden', true );

		// If user meta is not found; add the default hidden column 'id'
		if ( ! $hidden ) {
			$hidden = array( 'id' );
			$this->plugin->admin->update_user_meta( $user->ID, 'manage' . $this->screen->id . 'columnshidden', $hidden );
		}

		return $hidden;
	}

	function prepare_items() {
		$columns  = $this->get_columns();
		$sortable = $this->get_sortable_columns();
		$hidden   = $this->get_hidden_columns();
		$primary  = $columns['summary'];

		$this->_column_headers = array( $columns, $hidden, $sortable, $primary );

		$this->items = $this->get_records();

		$total_items = $this->get_total_found_rows();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->get_items_per_page( 'edit_stream_per_page', 20 ),
			)
		);
	}

	function get_records() {
		$args = array();

		// Parse sorting params
		if ( $order = wp_stream_filter_input( INPUT_GET, 'order' ) ) {
			$args['order'] = $order;
		}

		if ( $orderby = wp_stream_filter_input( INPUT_GET, 'orderby' ) ) {
			$args['orderby'] = $orderby;
		}

		$params = array(
			'search',
			'date',
			'date_from',
			'date_to',
			'date_after',
			'date_before',
		);

		foreach ( $params as $param ) {
			$value = wp_stream_filter_input( INPUT_GET, $param );

			if ( $value ) {
				$args[ $param ] = $value;
			}
		}

		// Additional filter properties
		$properties = array(
			'record',
			'site_id',
			'blog_id',
			'object_id',
			'user_id',
			'user_role',
			'ip',
			'connector',
			'context',
			'action',
		);

		// Add property fields to defaults, including their __in/__not_in variations
		foreach ( $properties as $property ) {
			$value = wp_stream_filter_input( INPUT_GET, $property );

			// Allow 0 values
			if ( isset( $value ) && '' !== $value && false !== $value ) {
				$args[ $property ] = $value;
			}

			$value_in = wp_stream_filter_input( INPUT_GET, $property . '__in' );

			if ( $value_in ) {
				$args[ $property . '__in' ] = explode( ',', $value_in );
			}

			$value_not_in = wp_stream_filter_input( INPUT_GET, $property . '__not_in' );

			if ( $value_not_in ) {
				$args[ $property . '__not_in' ] = explode( ',', $value_not_in );
			}
		}

		$args['paged'] = $this->get_pagenum();

		if ( isset( $args['context'] ) && 0 === strpos( $args['context'], 'group-' ) ) {
			$args['connector'] = str_replace( 'group-', '', $args['context'] );
			$args['context']   = '';
		}

		if ( ! isset( $args['records_per_page'] ) ) {
			$args['records_per_page'] = $this->get_items_per_page( 'edit_stream_per_page', 20 );
		}

		$items = $this->plugin->db->query( $args );

		return $items;
	}

	/**
	 * Get last query found rows
	 *
	 * @return integer
	 */
	public function get_total_found_rows() {
		return $this->plugin->db->query->found_records;
	}

	function column_default( $item, $column_name ) {
		$out = '';
		$record = new Record( $item );

		switch ( $column_name ) {
			case 'date' :
				$created     = date( 'Y-m-d H:i:s', strtotime( $record->created ) );
				$date_string = sprintf(
					'<time datetime="%s" class="relative-time record-created">%s</time>',
					wp_stream_get_iso_8601_extended_date( strtotime( $record->created ) ),
					get_date_from_gmt( $created, 'Y/m/d' )
				);
				$out  = $this->column_link( $date_string, 'date', get_date_from_gmt( $created, 'Y/m/d' ) );
				$out .= '<br />';
				$out .= get_date_from_gmt( $created, 'h:i:s A' );
				break;

			case 'summary' :
				$out           = $record->summary;
				$object_title  = $record->get_object_title();
				$view_all_text = $object_title ? sprintf( esc_html__( 'View all activity for "%s"', 'stream' ), esc_attr( $object_title ) ) : esc_html__( 'View all activity for this object', 'stream' );

				if ( $record->object_id ) {
					$out .= $this->column_link(
						'<span class="dashicons dashicons-search stream-filter-object-id"></span>',
						array(
							'object_id' => $record->object_id,
							'context'   => $record->context,
						),
						null,
						esc_attr( $view_all_text )
					);
				}
				$out .= $this->get_action_links( $record );
				break;

			case 'user_id' :
				$user = new Author( (int) $record->user_id, (array) maybe_unserialize( $record->user_meta ) );

				$filtered_records_url = add_query_arg(
					array(
						'page'    => $this->plugin->admin->records_page_slug,
						'user_id' => absint( $user->id ),
					),
					self_admin_url( $this->plugin->admin->admin_parent_page )
				);

				$out = sprintf(
					'<a href="%s">%s <span>%s</span></a>%s%s%s',
					$filtered_records_url,
					$user->get_avatar_img( 80 ),
					$user->get_display_name(),
					$user->is_deleted() ? sprintf( '<br /><small class="deleted">%s</small>', esc_html__( 'Deleted User', 'stream' ) ) : '',
					$user->get_role() ? sprintf( '<br /><small>%s</small>', $user->get_role() ) : '',
					$user->get_agent() ? sprintf( '<br /><small>%s</small>', $user->get_agent_label( $user->get_agent() ) ) : ''
				);
				break;

			case 'context':
				$connector_title = $this->get_term_title( $record->{'connector'}, 'connector' );
				$context_title   = $this->get_term_title( $record->{'context'}, 'context' );

				$out  = $this->column_link( $connector_title, 'connector', $item->{'connector'} );
				$out .= '<br />&#8627;&nbsp;';
				$out .= $this->column_link(
					$context_title,
					array(
						'connector' => $record->{'connector'},
						'context'   => $record->{'context'},
					)
				);
				break;

			case 'action':
				$out = $this->column_link( $this->get_term_title( $record->{$column_name}, $column_name ), $column_name, $record->{$column_name} );
				break;

			case 'blog_id':
				$blog = ( $record->blog_id && is_multisite() ) ? get_blog_details( $record->blog_id ) : $this->plugin->admin->network->get_network_blog();
				$out  = $this->column_link( $blog->blogname, 'blog_id', $blog->blog_id );
				break;

			case 'ip' :
				$out = $this->column_link( $record->{$column_name}, 'ip', $record->{$column_name} );
				break;

			default :
				/**
				 * Registers new Columns to be inserted into the table.  The cell contents of this column is set
				 * below with 'wp_stream_inster_column_default-'
				 *
				 * @return array
				 */
				$inserted_columns = apply_filters( 'wp_stream_register_column_defaults', $new_columns = array() );

				if ( ! empty( $inserted_columns ) && is_array( $inserted_columns ) ) {
					foreach ( $inserted_columns as $column_title ) {
						/**
						 * If column title inserted via wp_stream_register_column_defaults ($column_title) exists
						 * among columns registered with get_columns ($column_name) and there is an action associated
						 * with this column, do the action
						 *
						 * Also, note that the action name must include the $column_title registered
						 * with wp_stream_register_column_defaults
						 */
						if ( $column_title === $column_name && has_filter( "wp_stream_insert_column_default-{$column_title}" ) ) {
							/**
							 * Allows for the addition of content under a specified column.
							 *
							 * @param object $record  Contents of the row
							 *
							 * @return string
							 */
							$out = apply_filters( "wp_stream_insert_column_default-{$column_title}", $column_name, $record );
						} else {
							$out = $column_name;
						}
					}
				} else {
					$out = $column_name;
				}
		}

		echo $out; // xss ok
	}

	public function get_action_links( $record ) {
		$out = '';

		/**
		 * Filter allows modification of action links for a specific connector
		 *
		 * @param array
		 * @param Record
		 *
		 * @return array Action links for this connector
		 */
		$action_links = apply_filters( 'wp_stream_action_links_' . $record->connector, array(), $record );

		/**
		 * Filter allows addition of custom links for a specific connector
		 *
		 * @param array
		 * @param Record
		 *
		 * @return array Custom links for this connector
		 */
		$custom_links = apply_filters( 'wp_stream_custom_action_links_' . $record->connector, array(), $record );

		if ( $action_links || $custom_links ) {
			$out .= '<div class="row-actions">';
		}

		$links = array();
		if ( $action_links && is_array( $action_links ) ) {
			foreach ( $action_links as $al_title => $al_href ) {
				$links[] = sprintf(
					'<span><a href="%s" class="action-link">%s</a></span>',
					$al_href,
					$al_title
				);
			}
		}

		if ( $custom_links && is_array( $custom_links ) ) {
			foreach ( $custom_links as $key => $link ) {
				$links[] = $link;
			}
		}

		$out .= implode( ' | ', $links );

		if ( $action_links || $custom_links ) {
			$out .= '</div>';
		}

		return $out;
	}

	function column_link( $display, $key, $value = null, $title = null ) {
		$url = add_query_arg(
			array(
				'page' => $this->plugin->admin->records_page_slug,
			),
			self_admin_url( $this->plugin->admin->admin_parent_page )
		);

		$args = ! is_array( $key ) ? array( $key => $value ) : $key;

		foreach ( $args as $k => $v ) {
			$url = add_query_arg( $k, $v, $url );
		}

		return sprintf(
			'<a href="%s" title="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $title ),
			$display
		);
	}

	public function get_term_title( $term, $type ) {
		if ( ! isset( $this->plugin->connectors->term_labels[ "stream_$type" ][ $term ] ) ) {
			return $term;
		}

		return $this->plugin->connectors->term_labels[ "stream_$type" ][ $term ];
	}

	/**
	 * Assembles records for display in search filters
	 *
	 * Gathers list of all users/connectors, then compares it to
	 * results of existing records.  All items that do not exist in records
	 * get assigned a disabled value of "true".
	 *
	 * @param string $column List table column name
	 *
	 * @return array Options to be displayed in search filters
	 */
	function assemble_records( $column ) {
		// @todo eliminate special condition for authors, especially using a WP_User object as the value; should use string or stringifiable object
		if ( 'user_id' === $column ) {
			$all_records = array();

			// If the number of users exceeds the max users constant value then return an empty array and use AJAX instead
			$user_count  = count_users();
			$total_users = $user_count['total_users'];

			if ( $total_users > $this->plugin->admin->preload_users_max ) {
				return array();
			}

			$users = array_map(
				function( $user_id ) {
					return new Author( $user_id );
				},
				get_users( array( 'fields' => 'ID' ) )
			);

			$users[] = new Author( 0, array( 'is_wp_cli' => true ) );

			foreach ( $users as $user ) {
				$all_records[ $user->id ] = $user->get_display_name();
			}
		} else {
			$prefixed_column = sprintf( 'stream_%s', $column );
			$all_records     = $this->plugin->connectors->term_labels[ $prefixed_column ];
		}

		$existing_records = $this->plugin->db->existing_records( $column );
		$active_records   = array();
		$disabled_records = array();

		foreach ( $all_records as $record => $label ) {
			if ( array_key_exists( $record, $existing_records ) ) {
				$active_records[ $record ] = array( 'label' => $label, 'disabled' => '' );
			} else {
				$disabled_records[ $record ] = array( 'label' => $label, 'disabled' => 'disabled="disabled"' );
			}
		}

		// Remove WP-CLI pseudo user if no records with user=0 exist
		if ( isset( $disabled_records[0] ) ) {
			unset( $disabled_records[0] );
		}

		$sort = function( $a, $b ) use ( $column ) {
			$label_a = (string) $a['label'];
			$label_b = (string) $b['label'];

			if ( $label_a === $label_b ) {
				return 0;
			}

			return ( strtolower( $label_a ) < strtolower( $label_b ) ) ? -1 : 1;
		};

		uasort( $active_records, $sort );
		uasort( $disabled_records, $sort );

		// Not using array_merge() in order to preserve the array index for the users dropdown which uses the user_id as the key
		$all_records = $active_records + $disabled_records;

		return $all_records;
	}

	public function get_filters() {
		$filters = array();

		$date_interval = new Date_Interval();

		$filters['date'] = array(
			'title' => __( 'dates', 'stream' ),
			'items' => $date_interval->intervals,
		);

		$users = $this->get_users_dropdown_items(
			$this->assemble_records( 'user_id' )
		);

		$filters['user_id'] = array(
			'title' => __( 'users', 'stream' ),
			'items' => $users,
			'ajax'  => count( $users ) <= 0,
		);

		$filters['context'] = array(
			'title' => __( 'contexts', 'stream' ),
			'items' => $this->assemble_records( 'context' ),
		);

		$filters['action'] = array(
			'title' => __( 'actions', 'stream' ),
			'items' => $this->assemble_records( 'action' ),
		);

		/**
		 * Filter allows additional filters in the list table dropdowns
		 * Note the format of the filters above, with they key and array
		 * containing a title and array of items.
		 *
		 * @return array
		 */
		return apply_filters( 'wp_stream_list_table_filters', $filters );
	}

	function filters_form() {
		$user_id = get_current_user_id();
		$filters = $this->get_filters();

		$filters_string  = sprintf( '<input type="hidden" name="page" value="%s" />', 'wp_stream' );
		$filters_string .= sprintf( '<span class="filter_info hidden">%s</span>', esc_html__( 'Show filter controls via the screen options tab above.', 'stream' ) );

		foreach ( $filters as $name => $data ) {
			if ( 'date' === $name ) {
				$filters_string .= $this->filter_date( $data['items'] );
			} else {
				if ( 'context' === $name ) {
					// Add Connectors as parents, and apply the Contexts as children
					$connectors    = $this->assemble_records( 'connector' );
					$context_items = array();

					foreach ( $connectors as $connector => $item ) {
						$context_items[ $connector ]['label'] = $item['label'];

						foreach ( $data['items'] as $context_value => $context_item ) {
							if ( isset( $this->plugin->connectors->contexts[ $connector ] ) && array_key_exists( $context_value, $this->plugin->connectors->contexts[ $connector ] ) ) {
								$context_items[ $connector ]['children'][ $context_value ] = $context_item;
							}
						}

						if ( isset( $context_items[ $connector ]['children'] ) ) {
							$labels = wp_list_pluck( $context_items[ $connector ]['children'], 'label' );

							// Sort child items by label
							array_multisort( $labels, SORT_ASC, $context_items[ $connector ]['children'] );
						}
					}

					foreach ( $context_items as $context_value => $context_item ) {
						if ( ! isset( $context_item['children'] ) || empty( $context_item['children'] ) ) {
							unset( $context_items[ $context_value ] );
						}
					}

					$data['items'] = $context_items;

					$labels = wp_list_pluck( $data['items'], 'label' );

					// Sort top-level items by label
					array_multisort( $labels, SORT_ASC, $data['items'] );

					// Ouput a hidden input to handle the connector value
					$filters_string .= '<input type="hidden" name="connector" class="record-filter-connector" />';
				}

				$filters_string .= $this->filter_select( $name, $data['title'], $data['items'] );
			}
		}

		$filters_string .= sprintf( '<input type="submit" id="record-query-submit" class="button" value="%s" />', __( 'Filter', 'stream' ) );

		// Parse all query vars into an array
		$query_vars = array();

		if ( isset( $_SERVER['QUERY_STRING'] ) ) {
			parse_str( urldecode( $_SERVER['QUERY_STRING'] ), $query_vars );
		}

		// Ignore certain query vars and query vars that are empty
		foreach ( $query_vars as $query_var => $value ) {
			if ( '' === $value || 'page' === $query_var || 'paged' === $query_var ) {
				unset( $query_vars[ $query_var ] );
			}
		}

		$url = add_query_arg(
			array(
				'page' => $this->plugin->admin->records_page_slug,
			),
			self_admin_url( $this->plugin->admin->admin_parent_page )
		);

		// Display reset action if records are being filtered
		if ( ! empty( $query_vars ) ) {
			$filters_string .= sprintf( '<a href="%s" id="record-query-reset"><span class="dashicons dashicons-dismiss"></span> <span class="record-query-reset-text">%s</span></a>', esc_url( $url ), __( 'Reset filters', 'stream' ) );
		}

		return sprintf( '<div class="alignleft actions">%s</div>', $filters_string ); // xss ok
	}

	function filter_select( $name, $title, $items, $ajax = false ) {
		if ( $ajax ) {
			$out = sprintf(
				'<input type="hidden" name="%s" class="chosen-select" value="%s" data-placeholder="%s" />',
				esc_attr( $name ),
				esc_attr( wp_stream_filter_input( INPUT_GET, $name ) ),
				esc_attr( $title )
			);
		} else {
			$options  = array( '<option value=""></option>' );
			$selected = wp_stream_filter_input( INPUT_GET, $name );

			foreach ( $items as $key => $item ) {
				$value       = isset( $item['children'] ) ? 'group-' . $key : $key;
				$option_args = array(
					'value'    => $value,
					'selected' => selected( $value, $selected, false ),
					'disabled' => isset( $item['disabled'] ) ? $item['disabled'] : null,
					'icon'     => isset( $item['icon'] ) ? $item['icon'] : null,
					'group'    => isset( $item['children'] ) ? $key : null,
					'tooltip'  => isset( $item['tooltip'] ) ? $item['tooltip'] : null,
					'class'    => isset( $item['children'] ) ? 'level-1' : null,
					'label'    => isset( $item['label'] ) ? $item['label'] : null,
				);
				$options[] = $this->filter_option( $option_args );

				if ( isset( $item['children'] ) ) {
					foreach ( $item['children'] as $child_value => $child_item ) {
						$option_args  = array(
							'value'    => $child_value,
							'selected' => selected( $child_value, $selected, false ),
							'disabled' => isset( $child_item['disabled'] ) ? $child_item['disabled'] : null,
							'icon'     => isset( $child_item['icon'] ) ? $child_item['icon'] : null,
							'group'    => $key,
							'tooltip'  => isset( $child_item['tooltip'] ) ? $child_item['tooltip'] : null,
							'class'    => 'level-2',
							'label'    => isset( $child_item['label'] ) ? '- ' . $child_item['label'] : null,
						);
						$options[] = $this->filter_option( $option_args );
					}
				}
			}
			$out = sprintf(
				'<select name="%s" class="chosen-select" data-placeholder="%s">%s</select>',
				esc_attr( $name ),
				sprintf( esc_attr__( 'Show all %s', 'stream' ), $title ),
				implode( '', $options )
			);
		}

		return $out;
	}

	function filter_option( $args ) {
		$defaults = array(
			'value'    => null,
			'selected' => null,
			'disabled' => null,
			'icon'     => null,
			'group'    => null,
			'tooltip'  => null,
			'class'    => null,
			'label'    => null,
		);
		wp_parse_args( $args, $defaults );

		return sprintf(
			'<option value="%s" %s %s %s %s %s class="%s">%s</option>',
			esc_attr( $args['value'] ),
			$args['selected'],
			$args['disabled'],
			$args['icon'] ? sprintf( 'data-icon="%s"', esc_attr( $args['icon'] ) ) : null,
			$args['group'] ? sprintf( 'data-group="%s"', esc_attr( $args['group'] ) ) : null,
			$args['tooltip'] ? sprintf( 'title="%s"', esc_attr( $args['tooltip'] ) ) : null,
			$args['class'] ? esc_attr( $args['class'] ) : null,
			esc_html( $args['label'] )
		);
	}

	function filter_search() {
		$search = null;
		if ( isset( $_GET['search'] ) ) {
			// @TODO: Make this pass phpcs
			$search = esc_attr( wp_unslash( $_GET['search'] ) ); // input var okay
		}
		$out = sprintf(
			'<p class="search-box">
				<label class="screen-reader-text" for="record-search-input">%1$s:</label>
				<input type="search" id="record-search-input" name="search" value="%2$s" />
				<input type="submit" name="" id="search-submit" class="button" value="%1$s" />
			</p>',
			esc_attr__( 'Search Records', 'stream' ),
			$search
		);

		return $out;
	}

	function filter_date( $items ) {
		wp_enqueue_style( 'jquery-ui' );
		wp_enqueue_style( 'wp-stream-datepicker' );
		wp_enqueue_script( 'jquery-ui-datepicker' );

		$date_predefined = wp_stream_filter_input( INPUT_GET, 'date_predefined' );
		$date_from       = wp_stream_filter_input( INPUT_GET, 'date_from' );
		$date_to         = wp_stream_filter_input( INPUT_GET, 'date_to' );

		ob_start();
		?>
		<div class="date-interval">

			<select class="field-predefined hide-if-no-js" name="date_predefined" data-placeholder="<?php esc_attr_e( 'All Time', 'stream' ) ?>">
				<option></option>
				<option value="custom" <?php selected( 'custom' === $date_predefined ); ?>><?php esc_attr_e( 'Custom', 'stream' ) ?></option>
				<?php
				foreach ( $items as $key => $interval ) {
					$end = isset( $interval['end'] ) ? $interval['end']->format( 'Y/m/d' ) : null;

					printf(
						'<option value="%s" data-from="%s" data-to="%s" %s>%s</option>',
						esc_attr( $key ),
						esc_attr( $interval['start']->format( 'Y/m/d' ) ),
						esc_attr( $end ),
						selected( $key === $date_predefined ),
						esc_html( $interval['label'] )
					);
				}
				?>
			</select>

			<div class="date-inputs">
				<div class="box">
					<i class="date-remove dashicons"></i>
					<input type="text" name="date_from" class="date-picker field-from" placeholder="<?php esc_attr_e( 'Start Date', 'stream' ) ?>" value="<?php echo esc_attr( $date_from ) ?>" />
				</div>
				<span class="connector dashicons"></span>

				<div class="box">
					<i class="date-remove dashicons"></i>
					<input type="text" name="date_to" class="date-picker field-to" placeholder="<?php esc_attr_e( 'End Date', 'stream' ) ?>" value="<?php echo esc_attr( $date_to ) ?>" />
				</div>
			</div>

		</div>
		<?php

		return ob_get_clean();
	}

	function display() {
		$url = self_admin_url( $this->plugin->admin->admin_parent_page );

		echo '<form method="get" action="' . esc_url( $url ) . '" id="record-filter-form">';
		echo $this->filter_search(); // xss ok

		parent::display();
		echo '</form>';
	}

	function display_tablenav( $which ) {
		if ( 'top' === $which ) : ?>
			<div class="tablenav <?php echo esc_attr( $which ); ?>">
				<?php
				$this->pagination( $which );
				$this->extra_tablenav( $which );
				?>

				<br class="clear" />
			</div>
		<?php else : ?>
			<div class="tablenav <?php echo esc_attr( $which ); ?>">
				<?php
				/**
				 * Fires after the list table is displayed.
				 */
				do_action( 'wp_stream_after_list_table' );
				$this->pagination( $which );
				$this->extra_tablenav( $which );
				?>

				<br class="clear" />
			</div>
		<?php
		endif;
	}

	function set_screen_option( $dummy, $option, $value ) {
		if ( 'edit_stream_per_page' === $option ) {
			return $value;
		} else {
			return $dummy;
		}
	}

	function set_live_update_option( $dummy, $option, $value ) {
		unset( $value );

		// @codingStandardsIgnoreStart
		if (
			$this->plugin->admin->live_update->user_meta_key === $option
			&&
			isset( $_POST[ $this->plugin->admin->live_update->user_meta_key ] )
		) {
			$value = esc_attr( $_POST[ $this->plugin->admin->live_update->user_meta_key ] ); //input var okay

			return $value;
		}
		// @codingStandardsIgnoreEnd

		return $dummy;
	}

	public function screen_controls( $status, $args ) {
		unset( $status );
		unset( $args );

		$user_id   = get_current_user_id();
		$option    = $this->plugin->admin->get_user_meta( $user_id, $this->plugin->admin->live_update->user_meta_key, true );
		$heartbeat = wp_script_is( 'heartbeat', 'done' ) ? 'true' : 'false';

		if ( 'on' === $option && 'false' === $heartbeat ) {
			$option = 'off';

			$this->plugin->admin->update_user_meta( $user_id, $this->plugin->admin->live_update->user_meta_key, 'off' );
		}

		$nonce = wp_create_nonce( $this->plugin->admin->live_update->user_meta_key . '_nonce' );

		ob_start();
		?>
		<fieldset>
			<h5><?php esc_html_e( 'Live updates', 'stream' ) ?></h5>

			<div>
				<input type="hidden" name="stream_live_update_nonce" id="stream_live_update_nonce" value="<?php echo esc_attr( $nonce ) ?>" />
			</div>
			<div>
				<input type="hidden" name="enable_live_update_user" id="enable_live_update_user" value="<?php echo absint( $user_id ) ?>" />
			</div>
			<div class="metabox-prefs stream-live-update-checkbox">
				<label for="enable_live_update">
					<input type="checkbox" value="on" name="enable_live_update" id="enable_live_update" data-heartbeat="<?php echo esc_attr( $heartbeat ) ?>" <?php checked( $option, 'on' ) ?> />
					<?php esc_html_e( 'Enabled', 'stream' ) ?><span class="spinner"></span>
				</label>
			</div>
		</fieldset>
		<?php
		return ob_get_clean();
	}

	/**
	 * This function is use to map List table column name with excluded setting keys
	 *
	 * @param string $column List table column name
	 *
	 * @return string setting name for that column
	 */
	function get_column_excluded_setting_key( $column ) {
		switch ( $column ) {
			case 'connector':
				$output = 'connectors';
				break;
			case 'context':
				$output = 'contexts';
				break;
			case 'action':
				$output = 'action';
				break;
			case 'ip':
				$output = 'ip_addresses';
				break;
			case 'user_id':
				$output = 'users';
				break;
			default:
				$output = false;
		}

		return $output;
	}

	/**
	 * Get users as dropdown items
	 *
	 * @param array $users
	 *
	 * @return array
	 */
	public function get_users_dropdown_items( $users ) {
		$record_meta = array();

		foreach ( $users as $user_id => $args ) {
			$user     = new Author( $user_id );
			$disabled = isset( $args['disabled'] ) ? $args['disabled'] : null;

			$record_meta[ $user_id ] = array(
				'text'     => $user->get_display_name(),
				'id'       => $user_id,
				'label'    => $user->get_display_name(),
				'icon'     => $user->get_avatar_src( 32 ),
				'title'    => '',
				'disabled' => $disabled,
			);
		}

		return $record_meta;
	}
}
