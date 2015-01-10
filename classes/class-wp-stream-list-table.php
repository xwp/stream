<?php

class WP_Stream_List_Table extends WP_List_Table {

	function __construct( $args = array() ) {

		$screen_id = isset( $args['screen'] ) ? $args['screen'] : null;
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
		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );

		set_screen_options();
	}

	function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			echo $this->filters_form(); //xss ok
		}
	}

	function no_items() {
		$site = WP_Stream::$api->get_site();

		if ( isset( $site->plan->type ) && 'free' === $site->plan->type && 0 !== $this->get_total_found_rows() ) {
			?>
			<div class="stream-list-table-upgrade">
				<p><?php printf( _n( 'Your free account is limited to viewing 24 hours of activity history.', 'Your free account is limited to viewing <strong>%d days</strong> of activity history.', $site->plan->retention, 'stream' ), absint( $site->plan->retention ) ) ?></p>
				<p><a href="<?php echo esc_url( WP_Stream_Admin::account_url( sprintf( 'upgrade?site_uuid=%s', WP_Stream::$api->site_uuid ) ) ); ?>" class="button button-primary button-large"><?php _e( 'Upgrade to Pro', 'stream' ) ?></a></p>
			</div>
			<?php
		} else {
			_e( 'Sorry, no activity records were found.', 'stream' );
		}
	}

	function get_columns(){
		/**
		 * Allows devs to add new columns to table
		 *
		 * @param  array  default columns
		 *
		 * @return array  updated list of columns
		 */
		return apply_filters(
			'wp_stream_list_table_columns',
			array(
				'date'    => __( 'Date', 'stream' ),
				'summary' => __( 'Summary', 'stream' ),
				'author'  => __( 'Author', 'stream' ),
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
		$hidden = get_user_meta( $user->ID, 'manage' . $this->screen->id . 'columnshidden', true );

		// If user meta is not found; add the default hidden column 'id'
		if ( ! $hidden ) {
			$hidden = array( 'id' );
			update_user_meta( $user->ID, 'manage' . $this->screen->id . 'columnshidden', $hidden );
		}

		return $hidden;
	}

	function prepare_items() {
		$columns  = $this->get_columns();
		$sortable = $this->get_sortable_columns();
		$hidden   = $this->get_hidden_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->items = $this->get_records();

		$total_items = $this->get_total_found_rows();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->get_items_per_page( 'edit_stream_per_page', 20 ),
			)
		);
	}

	/**
	 * Render the checkbox column
	 *
	 * @param  array $item Contains all the data for the checkbox column
	 *
	 * @return string Displays a checkbox
	 */
	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			/*$1%s*/
			'wp_stream_checkbox',
			/*$2%s*/
			$item->ID
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

		// Filters
		$params = array(
			'search',
			'date',
			'date_from',
			'date_to',
			'record_after', // Deprecated, use date_after instead
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
			'author',
			'author_role',
			'ip',
			'object_id',
			'site_id',
			'blog_id',
			'connector',
			'context',
			'action',
		);

		// Add property fields to defaults, including their __in/__not_in variations
		foreach ( $properties as $property ) {
			$value = wp_stream_filter_input( INPUT_GET, $property );
			if ( $value ) {
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

		$args['aggregations'] = array( 'author', 'connector', 'context', 'action' );

		$items = wp_stream_query( $args );

		return $items;
	}

	/**
	 * Get last query found rows
	 *
	 * @return integer
	 */
	function get_total_found_rows() {
		return WP_Stream::$db->get_found_rows();
	}

	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'date' :
				$created     = date( 'Y-m-d H:i:s', strtotime( $item->created ) );
				$date_string = sprintf(
					'<time datetime="%s" class="relative-time record-created">%s</time>',
					wp_stream_get_iso_8601_extended_date( strtotime( $item->created ) ),
					get_date_from_gmt( $created, 'Y/m/d' )
				);
				$out  = $this->column_link( $date_string, 'date', get_date_from_gmt( $created, 'Y/m/d' ) );
				$out .= '<br />';
				$out .= get_date_from_gmt( $created, 'h:i:s A' );
				break;

			case 'summary' :
				$out           = $item->summary;
				$object_title  = wp_stream_get_object_title( $item );
				$view_all_text = $object_title ? sprintf( __( 'View all activity for "%s"', 'stream' ), esc_attr( $object_title ) ) : __( 'View all activity for this object', 'stream' );
				if ( $item->object_id ) {
					$out .= $this->column_link(
						'<span class="dashicons dashicons-search stream-filter-object-id"></span>',
						array(
							'object_id' => $item->object_id,
							'context'   => $item->context,
						),
						null,
						esc_attr( $view_all_text )
					);
				}
				$out .= $this->get_action_links( $item );
				break;

			case 'author' :
				$author = new WP_Stream_Author( (int) $item->author, (array) $item->author_meta );

				$out = sprintf(
					'<a href="%s">%s <span>%s</span></a>%s%s%s',
					$author->get_records_page_url(),
					$author->get_avatar_img( 80 ),
					$author->get_display_name(),
					$author->is_deleted() ? sprintf( '<br /><small class="deleted">%s</small>', esc_html__( 'Deleted User', 'stream' ) ) : '',
					$author->get_role() ? sprintf( '<br /><small>%s</small>', $author->get_role() ) : '',
					$author->get_agent() ? sprintf( '<br /><small>%s</small>', WP_Stream_Author::get_agent_label( $author->get_agent() ) ) : ''
				);
				break;

			case 'context':
				$connector_title = $this->get_term_title( $item->{'connector'}, 'connector' );
				$context_title   = $this->get_term_title( $item->{'context'}, 'context' );

				$out  = $this->column_link( $connector_title, 'connector', $item->{'connector'} );
				$out .= '<br />&#8627;&nbsp;';
				$out .= $this->column_link(
					$context_title,
					array(
						'connector' => $item->{'connector'},
						'context'   => $item->{'context'},
					)
				);
				break;

			case 'action':
				$out = $this->column_link( $this->get_term_title( $item->{$column_name}, $column_name ), $column_name, $item->{$column_name} );
				break;

			case 'ip' :
				$out = $this->column_link( $item->{$column_name}, 'ip', $item->{$column_name} );
				break;

			case 'blog_id':
				$blog = get_blog_details( $item->blog_id );
				$out  = sprintf(
					'<a href="%s"><span>%s</span></a>',
					add_query_arg( array( 'blog_id' => $blog->blog_id ), admin_url( 'admin.php?page=wp_stream' ) ),
					esc_html( $blog->blogname )
				);
				break;

			default :
				/**
				 * Registers new Columns to be inserted into the table.  The cell contents of this column is set
				 * below with 'wp_stream_inster_column_default-'
				 *
				 * @param array $new_columns Array of new column titles to add
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
						if ( $column_title == $column_name && has_action( "wp_stream_insert_column_default-{$column_title}" ) ) {
							/**
							 * Allows for the addition of content under a specified column.
							 *
							 * @since 1.0.0
							 *
							 * @param object $item Contents of the row
							 */
							$out = do_action( "wp_stream_insert_column_default-{$column_title}", $item );
						} else {
							$out = $column_name;
						}
					}
				} else {
					$out = $column_name; // xss ok
				}
		}

		echo $out; // xss ok
	}

	public static function get_action_links( $record ) {
		$out = '';

		/**
		 * Filter allows modification of action links for a specific connector
		 *
		 * @param  string  connector
		 * @param  array   array of action links for this connector
		 * @param  obj     record
		 *
		 * @return arrray  action links for this connector
		 */
		$action_links = apply_filters( 'wp_stream_action_links_' . $record->connector, array(), $record );

		/**
		 * Filter allows addition of custom links for a specific connector
		 *
		 * @param  string  connector
		 * @param  array   array of custom links for this connector
		 * @param  obj     record
		 *
		 * @return arrray  custom links for this connector
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
				'page' => WP_Stream_Admin::RECORDS_PAGE_SLUG,
			),
			self_admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
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
		if ( isset( WP_Stream_Connectors::$term_labels[ "stream_$type" ][ $term ] ) ) {
			return WP_Stream_Connectors::$term_labels[ "stream_$type" ][ $term ];
		} else {
			return $term;
		}
	}

	/**
	 * Assembles records for display in search filters
	 *
	 * Gathers list of all authors/connectors, then compares it to
	 * results of existing records.  All items that do not exist in records
	 * get assigned a disabled value of "true".
	 *
	 * @uses   wp_stream_existing_records (see class-wp-stream-query.php)
	 * @since  1.0.4
	 *
	 * @param  string  Column requested
	 * @param  string  Table to be queried
	 *
	 * @return array   options to be displayed in search filters
	 */
	function assemble_records( $column ) {
		$setting_key = self::get_column_excluded_setting_key( $column );

		// @todo eliminate special condition for authors, especially using a WP_User object as the value; should use string or stringifiable object
		if ( 'author' === $column ) {
			$all_records = array();

			// If the number of users exceeds the max authors constant value then return an empty array and use AJAX instead
			$user_count  = count_users();
			$total_users = $user_count['total_users'];

			if ( $total_users > WP_Stream_Admin::PRELOAD_AUTHORS_MAX ) {
				return array();
			}

			$authors = array_map(
				function ( $user_id ) {
					return new WP_Stream_Author( $user_id );
				},
				get_users( array( 'fields' => 'ID' ) )
			);

			$authors[] = new WP_Stream_Author( 0, array( 'is_wp_cli' => true ) );

			foreach ( $authors as $author ) {
				$all_records[ $author->id ] = $author->get_display_name();
			}
		} else {
			$prefixed_column = sprintf( 'stream_%s', $column );
			$all_records     = WP_Stream_Connectors::$term_labels[ $prefixed_column ];
		}

		$query_meta = WP_Stream::$db->get_query_meta();

		$values           = array();
		$existing_records = array();

		if ( isset( $query_meta->aggregations->$column->buckets ) ) {
			foreach ( $query_meta->aggregations->$column->buckets as $field ) {
				$values[ $field->key ] = $field->key;
			}

			if ( ! empty( $values ) ) {
				$existing_records = array_combine( $values, $values );
			}
		}

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

		$sort = function ( $a, $b ) use ( $column ) {
			$label_a = (string) $a['label'];
			$label_b = (string) $b['label'];

			if ( $label_a === $label_b ) {
				return 0;
			}

			return ( strtolower( $label_a ) < strtolower( $label_b ) ) ? -1 : 1;
		};

		uasort( $active_records, $sort );
		uasort( $disabled_records, $sort );

		// Not using array_merge() in order to preserve the array index for the Authors dropdown which uses the user_id as the key
		$all_records = $active_records + $disabled_records;

		return $all_records;
	}

	public function get_filters() {
		$filters = array();

		$date_interval = new WP_Stream_Date_Interval();

		$filters['date'] = array(
			'title' => __( 'dates', 'stream' ),
			'items' => $date_interval->intervals,
		);

		$authors_records = WP_Stream_Admin::get_authors_record_meta(
			$this->assemble_records( 'author' )
		);

		$filters['author'] = array(
			'title' => __( 'authors', 'stream' ),
			'items' => $authors_records,
			'ajax'  => count( $authors_records ) <= 0,
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
		 * @param  array  Array of filters
		 *
		 * @return array  Updated array of filters
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
					$connectors = $this->assemble_records( 'connector' );

					foreach ( $connectors as $connector => $item ) {
						$context_items[ $connector ]['label'] = $item['label'];

						foreach ( $data['items'] as $context_value => $context_item ) {
							if ( isset( WP_Stream_Connectors::$contexts[ $connector ] ) && array_key_exists( $context_value, WP_Stream_Connectors::$contexts[ $connector ] ) ) {
								$context_items[ $connector ]['children'][ $context_value ] = $context_item;
							}
						}
					}

					foreach ( $context_items as $context_value => $context_item ) {
						if ( ! isset( $context_item['children'] ) || empty( $context_item['children'] ) ) {
							unset( $context_items[ $context_value ] );
						}
					}

					$data['items'] = $context_items;

					ksort( $data['items'] );

					// Ouput a hidden input to handle the connector value
					$filters_string .= '<input type="hidden" name="connector" class="record-filter-connector" />';
				}
				$filters_string .= $this->filter_select( $name, $data['title'], $data['items'] );
			}
		}

		$filters_string .= sprintf( '<input type="submit" id="record-query-submit" class="button" value="%s" />', __( 'Filter', 'stream' ) );

		$url = self_admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE );

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
		$out = sprintf(
			'<p class="search-box">
				<label class="screen-reader-text" for="record-search-input">%1$s:</label>
				<input type="search" id="record-search-input" name="search" value="%2$s" />
				<input type="submit" name="" id="search-submit" class="button" value="%1$s" />
			</p>',
			esc_attr__( 'Search Records', 'stream' ),
			isset( $_GET['search'] ) ? esc_attr( wp_unslash( $_GET['search'] ) ) : null
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

			<select class="field-predefined hide-if-no-js" name="date_predefined" data-placeholder="<?php _e( 'All Time', 'stream' ); ?>">
				<option></option>
				<option value="custom" <?php selected( 'custom' === $date_predefined ); ?>><?php esc_attr_e( 'Custom', 'stream' ) ?></option>
				<?php
				foreach ( $items as $key => $interval ) {
					printf(
						'<option value="%s" data-from="%s" data-to="%s" %s>%s</option>',
						esc_attr( $key ),
						esc_attr( $interval['start']->format( 'Y/m/d' ) ),
						isset( $interval['end'] ) ? esc_attr( $interval['end']->format( 'Y/m/d' ) ) : '',
						selected( $key === $date_predefined ),
						esc_html( $interval['label'] )
					); // xss ok
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
		$url = self_admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE );

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
				 *
				 * @since 1.0.0
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

	static function set_screen_option( $dummy, $option, $value ) {
		if ( 'edit_stream_per_page' === $option ) {
			return $value;
		} else {
			return $dummy;
		}
	}

	static function set_live_update_option( $dummy, $option, $value ) {
		if ( WP_Stream_Live_Update::USER_META_KEY === $option ) {
			$value = $_POST[ WP_Stream_Live_Update::USER_META_KEY ];
			return $value;
		} else {
			return $dummy;
		}
	}

	public function screen_controls( $status, $args ) {
		$user_id   = get_current_user_id();
		$option    = get_user_meta( $user_id, WP_Stream_Live_Update::USER_META_KEY, true );
		$heartbeat = wp_script_is( 'heartbeat', 'done' ) ? 'true' : 'false';

		if ( 'on' === $option && 'false' === $heartbeat ) {
			$option = 'off';

			update_user_meta( $user_id, WP_Stream_Live_Update::USER_META_KEY, 'off' );
		}

		$nonce = wp_create_nonce( WP_Stream_Live_Update::USER_META_KEY . '_nonce' );

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
	 * @param $column string list table column name
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
			case 'author':
				$output = 'authors';
				break;
			default:
				$output = false;
		}

		return $output;
	}
}
