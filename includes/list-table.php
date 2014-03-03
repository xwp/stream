<?php

class WP_Stream_Notifications_List_Table extends WP_List_Table {

	function __construct( $args = array() ) {
		$view = wp_stream_filter_input( INPUT_GET, 'view' );

		parent::__construct(
			array(
				'post_type' => 'stream_notifications',
				'plural'    => 'rules',
				'screen'    => isset( $args['screen'] ) ? $args['screen'] : null,
			)
		);

		if ( null === $view ) {
			add_screen_option(
				'per_page',
				array(
					'default' => 20,
					'label'   => esc_html__( 'Rules per page', 'stream-notifications' ),
					'option'  => 'edit_stream_notifications_per_page',
				)
			);
		}

		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );
		add_filter( 'stream_query_args', array( __CLASS__, 'register_occurrences_for_sorting' ) );
		add_filter( 'wp_stream_query',   array( __CLASS__, 'include_null_occurrences' ), 10, 2 );
		set_screen_options();
	}

	function extra_tablenav( $which ) {
		$this->filters_form( $which );
	}

	function get_columns(){
		$view = wp_stream_filter_input( INPUT_GET, 'view' );

		if ( null !== $view ) {
			return array();
		}

		return apply_filters(
			'wp_stream_notifications_list_table_columns',
			array(
				'cb'          => '<span class="check-column"><input type="checkbox" /></span>',
				'name'        => esc_html__( 'Name', 'stream-notifications' ),
				'type'        => esc_html__( 'Type', 'stream-notifications' ),
				'occurrences' => esc_html__( 'Occurrences', 'stream-notifications' ),
				'date'        => esc_html__( 'Date', 'stream-notifications' ),
			)
		);
	}

	function get_sortable_columns() {
		return array(
			'name'        => array( 'summary', false ),
			'occurrences' => array( 'occurrences', true ),
			'date'        => array( 'created', false ),
		);
	}

	function prepare_items() {
		$columns  = $this->get_columns();
		$sortable = $this->get_sortable_columns();
		$hidden   = get_hidden_columns( $this->screen );

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->items = $this->get_records();

		$total_items = $this->get_total_found_rows();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->get_items_per_page( 'edit_stream_notifications_per_page', 20 ),
			)
		);
	}

	/**
	 * Render the checkbox column
	 *
	 * @param  array $item Contains all the data for the checkbox column
	 * @return string Displays a checkbox
	 */
	function column_cb( $item ) {
			return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			'wp_stream_notifications_checkbox',
			$item->ID
		);
	}

	function count_records( $args = array() ) {
		$defaults = array(
			'records_per_page'  => 1,
			'ignore_url_params' => true,
		);

		$args    = wp_parse_args( $args, $defaults );
		$records = $this->get_records( $args );

		return $this->get_total_found_rows();
	}

	function get_records( $args = array() ) {

		$defaults = array(
			'ignore_url_params' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		// Parse sorting params
		if ( ! $order = wp_stream_filter_input( INPUT_GET, 'order' ) ) {
			$order = 'DESC';
		}
		if ( ! $orderby = wp_stream_filter_input( INPUT_GET, 'orderby' ) ) {
			$orderby = '';
		}
		$args['order']          = $order;
		$args['orderby']        = $orderby;
		$args['paged']          = $this->get_pagenum();
		$args['type']           = 'notification_rule';
		$args['ignore_context'] = true;

		if ( ! $args['ignore_url_params'] ) {
			$allowed_params = array(
				'search',
				'visibility',
				// filters
				'date',
			);

			foreach ( $allowed_params as $param ) {
				if ( $paramval = wp_stream_filter_input( INPUT_GET, $param ) ) {
					$args[ $param ] = $paramval;
				}
			}
		}

		if ( ! isset( $args['records_per_page'] ) ) {
			$args['records_per_page'] = $this->get_items_per_page( 'edit_stream_notifications_per_page', 20 );
		}

		$items = stream_query( $args );
		return $items;
	}

	function get_total_found_rows() {
		global $wpdb;
		return $wpdb->get_var( 'SELECT FOUND_ROWS()' ); // db call ok, cache ok
	}

	function column_default( $item, $column_name ) {
		switch ( $column_name ) {

			case 'name':
				$name = strlen( $item->summary ) ? $item->summary : sprintf( '(%s)', esc_html__( 'no title', 'stream-notifications' ) );
				$out  = sprintf(
					'<strong style="display:block;margin-bottom:.2em;font-size:14px;"><a href="%s" class="%s" title="%s">%s</a>%s</strong>', // TODO: Add these styles to a CSS file
					add_query_arg(
						array(
							'page'   => WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
							'view'   => 'rule',
							'action' => 'edit',
							'id'     => absint( $item->ID ),
						),
						admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
					),
					'row-title',
					esc_attr( $name ),
					esc_html( $name ),
					'inactive' === $item->visibility ? sprintf( ' - <span class="post-state">%s</span>', esc_html__( 'Inactive', 'stream-notifications' ) ) : ''
				);

				$out .= $this->get_action_links( $item );
				break;

			case 'type':
				$out = $this->get_rule_types( $item );
				break;

			case 'occurrences':
				$out = absint( get_stream_meta( $item->ID, 'occurrences', true ) );
				break;

			case 'date':
				$out  = $this->column_link( get_date_from_gmt( $item->created, 'Y/m/d' ), 'date', date( 'Y/m/d', strtotime( $item->created ) ) );
				$out .= '<br />';
				$out .= ( 'active' === $item->visibility ) ? esc_html__( 'Active', 'stream-notifications' ) : esc_html__( 'Last Modified', 'stream-notifications' );
				break;

			default:
				// Register inserted column defaults. Must match a column header from get_columns.
				$inserted_columns = apply_filters( 'wp_stream_notifications_register_column_defaults', $new_columns = array() );

				if ( ! empty( $inserted_columns ) && is_array( $inserted_columns ) ) {
					foreach ( $inserted_columns as $column_title ) {
						/**
						 * If column title inserted via wp_stream_notifications_register_column_defaults ($column_title) exists
						 * among columns registered with get_columns ($column_name) and there is an action associated
						 * with this column, do the action
						 *
						 * Also, note that the action name must include the $column_title registered
						 * with wp_stream_notifications_register_column_defaults
						 */
						if ( $column_title === $column_name && has_action( 'wp_stream_notifications_insert_column_default-' . $column_title ) ) {
							$out = do_action( 'wp_stream_notifications_insert_column_default-' . $column_title, $item );
						} else {
							$out = $column_name;
						}
					}
				} else {
					$out = $column_name; // xss okay
				}
				break;
		}

		echo $out; // xss okay
	}


	public static function get_action_links( $record ){
		$out          = '';
		$custom_links = apply_filters( 'wp_stream_notifications_custom_action_links_' . $record->ID, array(), $record );

		$out .= '<div class="row-actions">';

		$activation_nonce = wp_create_nonce( "activate-record_$record->ID" );
		$deletion_nonce   = wp_create_nonce( "delete-record_$record->ID" );

		$action_links = array();
		$action_links[ esc_html__( 'Edit', 'stream-notifications' ) ] = array(
			'href' => add_query_arg(
				array(
					'page'   => WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
					'view'   => 'rule',
					'action' => 'edit',
					'id'     => absint( $record->ID ),
				),
				admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
			),
			'class' => null,
		);

		if ( 'active' === $record->visibility ) {
			$action_links[ esc_html__( 'Deactivate', 'stream-notifications' ) ] = array(
				'href' => add_query_arg(
					array(
						'page'            => WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
						'action'          => 'deactivate',
						'id'              => absint( $record->ID ),
						'wp_stream_nonce' => $activation_nonce,
					),
					admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
				),
				'class' => null,
			);
		} elseif ( 'inactive' === $record->visibility ) {
			$action_links[ esc_html__( 'Activate', 'stream-notifications' ) ] = array(
				'href' => add_query_arg(
					array(
						'page'            => WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
						'action'          => 'activate',
						'id'              => absint( $record->ID ),
						'wp_stream_nonce' => $activation_nonce,
					),
					admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
				),
				'class' => null,
			);

			$visibility = wp_stream_filter_input( INPUT_GET, 'visibility' );

			$action_links[ esc_html__( 'Delete Permanently', 'stream-notifications' ) ] = array(
				'href' => add_query_arg(
					array(
						'page'            => WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
						'action'          => 'delete',
						'id'              => absint( $record->ID ),
						'wp_stream_nonce' => $deletion_nonce,
						'visibility'      => $visibility,
					),
					admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
				),
				'class' => 'delete',
			);
		}

		if ( $action_links ) {
			$links = array();
			$i     = 0;
			foreach ( $action_links as $link_title => $link_options ) {
				$i++;
				$links[] = sprintf(
					'<span class="%s"><a href="%s" class="action-link">%s</a>%s</span>',
					$link_options['class'],
					$link_options['href'],
					$link_title,
					( $i === count( $action_links ) ) ? null : ' | '
				);
			}
			$out .= implode( '', $links );
		}

		if ( $custom_links ) {
			$out .= ' | ';
		}

		if ( $custom_links && is_array( $custom_links ) ) {
			$last_link = end( $custom_links );
			foreach ( $custom_links as $key => $link ) {
				$out .= $link;
				if ( $key !== $last_link ) {
					$out .= ' | ';
				}
			}
		}

		$out .= '</div>';

		return $out;
	}

	function column_link( $display, $key, $value = null, $title = null, $class = null ) {
		$url = add_query_arg(
			array(
				'page' => WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
			),
			admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
		);

		$args = ! is_array( $key ) ? array( $key => $value ) : $key;

		foreach ( $args as $k => $v ) {
			$url = add_query_arg( $k, $v, $url );
		}

		return sprintf(
			'<a href="%s" class="%s" title="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $class ),
			esc_attr( $title ),
			esc_html( $display )
		);
	}

	function filters_form( $which ) {
		if ( 'top' === $which ) {
			$visibility = wp_stream_filter_input( INPUT_GET, 'visibility' );
			$filters_string = sprintf(
				'<input type="hidden" name="page" value="%s"/>
				%s
				<input type="hidden" name="wp_stream_nonce" value="%s"/>',
				WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
				( null !== $visibility ) ? sprintf( '<input type="hidden" name="visibility" value="%s"/>', $visibility ) : '',
				wp_create_nonce( 'wp_stream_notifications_bulk_actions' )
			);

			echo sprintf(
				'%s
				<div class="alignleft actions bulkactions">
					%s
				</div>
				<div class="alignleft actions">
					%s
				</div>',
				$this->filter_search(),
				$this->stream_notifications_bulk_actions( $which ),
				$filters_string
			); // xss okay
		} else {
			echo sprintf(
					'<div class="alignleft actions bulkactions">
						%s
					</div>
				</form>',
				$this->stream_notifications_bulk_actions( $which )
			); // xss okay
		}
	}

	/**
	 * Return the bulk actions select box, context aware
	 *
	 * @todo   Should we utilize WP_List_Table->bulk_actions()?
	 * @param  string $which Indicates whether to display the box over or under the list [top|bottom]
	 * @return string Bulk actions select box and a respective submit
	 */
	function stream_notifications_bulk_actions( $which ) {
		$dropdown_name = ( 'top' === $which ) ? 'action' : 'action2';
		$visibility    = wp_stream_filter_input( INPUT_GET, 'visibility', FILTER_DEFAULT );
		$options       = array();

		$options[] = sprintf(
			'<option value="-1" selected="selected">%s</option>',
			esc_html__( 'Bulk Actions', 'stream-notifications' )
		);

		if ( 'active' !== $visibility ) {
			$options[] = sprintf(
				'<option value="activate">%s</option>',
				esc_html__( 'Activate', 'stream-notifications' )
			);
		}
		if ( 'inactive' !== $visibility ) {
			$options[] = sprintf(
				'<option value="deactivate">%s</option>',
				esc_html__( 'Deactivate', 'stream-notifications' )
			);
		}
		if ( 'inactive' === $visibility ) {
			$options[] = sprintf(
				'<option value="delete">%s</option>',
				esc_html__( 'Delete Permanently', 'stream-notifications' )
			);
		}

		$options      = apply_filters( 'wp_stream_notifications_bulk_action_options', $options, $which, $visibility );
		$options_html = implode( '', $options );

		$html = sprintf(
			'<select name="%1$s">
				%2$s
			</select>
			<input type="submit" name="" id="do%1$s" class="button action" value="%3$s">',
			$dropdown_name,
			$options_html,
			esc_attr__( 'Apply', 'stream-notifications' )
		);

		return apply_filters( 'wp_stream_notifications_bulk_actions_html', $html );
	}

	function list_navigation() {
		$navigation_items = array(
			'all' => array(
				'link_text' => esc_html__( 'All', 'stream-notifications' ),
				'url'       => add_query_arg(
					array(
						'page' => WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
					),
					admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
				),
				'link_class' => null,
				'li_class'   => null,
				'count'      => $this->count_records(),
			),
			'active' => array(
				'link_text' => esc_html__( 'Active', 'stream-notifications' ),
				'url'       => add_query_arg(
					array(
						'page'       => WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
						'visibility' => 'active',
					),
					admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
				),
				'link_class' => null,
				'li_class'   => null,
				'count'      => $this->count_records( array( 'visibility' => 'active' ) ),
			),
			'inactive' => array(
				'link_text' => esc_html__( 'Inactive', 'stream-notifications' ),
				'url'       => add_query_arg(
					array(
						'page'       => WP_Stream_Notifications::NOTIFICATIONS_PAGE_SLUG,
						'visibility' => 'inactive',
					),
					admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
				),
				'link_class' => null,
				'li_class'   => null,
				'count'      => $this->count_records( array( 'visibility' => 'inactive' ) ),
			),
		);

		$navigation_items = apply_filters( 'wp_stream_notifications_list_navigation_array', $navigation_items );

		$navigation_links = array();
		$navigation_html  = '';
		$visibility       = wp_stream_filter_input( INPUT_GET, 'visibility', FILTER_DEFAULT, array( 'options' => array( 'default' => 'all' ) ) );

		$i = 0;

		foreach ( $navigation_items as $visibility_filter => $item ) {
			$i++;
			$navigation_links[] = sprintf(
				'<li class="%s"><a href="%s" class="%s">%s%s</a>%s</li>',
				esc_attr( $item[ 'li_class' ] ),
				esc_attr( $item[ 'url' ] ),
				( $visibility === $visibility_filter ) ? sprintf( 'current %s', esc_attr( $item[ 'link_class' ] ) ) : esc_attr( $item[ 'link_class' ] ),
				esc_html( $item[ 'link_text' ] ),
				( null !== $item[ 'count' ] ) ? sprintf( ' <span class="count">(%s)</span>', esc_html( $item[ 'count' ] ) ) : '',
				( $i === count( $navigation_items ) ) ? '' : ' | '
			);
		}

		$navigation_links = apply_filters( 'wp_stream_notifications_list_navigation_links', $navigation_links );
		$navigation_html  = is_array( $navigation_links ) ? implode( "\n", $navigation_links ) : $navigation_links;

		$out = sprintf(
			'<ul class="subsubsub">
				%s
			</ul>',
			$navigation_html
		);

		return apply_filters( 'wp_stream_notifications_list_navigation_html', $out );
	}

	function filter_search() {
		$out = sprintf(
			'<p class="search-box">
				<label class="screen-reader-text" for="record-search-input">%1$s:</label>
				<input type="search" id="record-search-input" name="search" value="%2$s" />
				<input type="submit" name="" id="search-submit" class="button" value="%1$s" />
			</p>',
			esc_attr__( 'Search Notification Rules', 'stream-notifications' ),
			isset( $_GET['search'] ) ? esc_attr( $_GET['search'] ) : null
		);

		return $out;
	}

	function display() {
		echo $this->list_navigation(); // xss ok
		echo '<form method="get" action="' . admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE ) . '">';
		parent::display();
	}

	function display_tablenav( $which ) {
		if ( 'top' === $which ) : ?>
			<div class="tablenav <?php echo esc_attr( $which ) ?>">
				<?php
				$this->extra_tablenav( $which );
				$this->pagination( $which );
				?>

				<br class="clear" />
			</div>
		<?php else : ?>
			<div class="tablenav <?php echo esc_attr( $which ) ?>">
				<?php
				do_action( 'wp_stream_notifications_after_list_table' );
				$this->extra_tablenav( $which );
				$this->pagination( $which );
				?>

				<br class="clear" />
			</div>
		<?php
		endif;
	}

	function single_row( $item ) {
		static $row_classes = array();

		$row_classes[ 'alternating' ] = in_array( 'alternate', $row_classes ) ? '' : 'alternate';
		$row_classes[ 'visibility' ]  = $item->visibility;

		$row_class = sprintf( 'class="%s"', implode( ' ', $row_classes ) );

		echo sprintf( '<tr %s>', $row_class ); // xss ok
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	static function set_screen_option( $dummy, $option, $value ) {
		if ( 'edit_stream_notifications_per_page' === $option ) {
			return $value;
		} else {
			return $dummy;
		}
	}

	function get_rule_types( $item ) {
		$rule = get_option( sprintf( 'stream_notifications_%d', $item->ID ) );
		if ( empty( $rule['alerts'] ) ) {
			return esc_html__( 'N/A', 'stream-notifications' );
		}
		$types  = wp_list_pluck( $rule['alerts'], 'type' );
		$titles = wp_list_pluck(
			array_intersect_key(
				WP_Stream_Notifications::$adapters,
				array_flip( $types )
			),
			'title'
		);
		return implode( ', ', $titles );
	}

	/**
	 * @filter stream_query_args
	 */
	static function register_occurrences_for_sorting( $args ) {
		if ( 'occurrences' === $args['orderby'] ) {
			$args['meta_key'] = $args['orderby'];
			$args['orderby']  = 'meta_value_num';
		}

		return $args;
	}

	/**
	 * @filter wp_stream_query
	 */
	static function include_null_occurrences( $sql, $args ) {
		$meta_key = 'occurrences';

		if( preg_match( sprintf( '#`?%s`?\.`?meta_key`?\s+=\s+\'%s\'#', WP_Stream_DB::$table_meta, $meta_key ), $sql ) ) {
			// replace INNER JOIN with LEFT JOIN
			$sql = preg_replace( sprintf( '#INNER(\s+JOIN\s+`?%s`?)#', WP_Stream_DB::$table_meta ), 'LEFT\1', $sql );

			// replace .meta_key = 'occurrences' with .meta_key = 'occurrences' OR .meta_key IS NULL
			$sql = preg_replace( sprintf( '#(`?%s`?\.`?meta_key`?) = \'%s\'#', WP_Stream_DB::$table_meta, $meta_key ), '\0 OR \1 IS NULL', $sql );
		}

		return $sql;
	}

}
