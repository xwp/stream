<?php

class WP_Stream_List_Table extends WP_List_Table {

	function __construct( $args = array() ) {
		parent::__construct(
			array(
				'post_type' => 'stream',
				'plural' => 'records',
				'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
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

		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );
		add_filter( 'screen_settings', array( __CLASS__, 'live_update_checkbox' ), 10, 2 );
		set_screen_options();
	}

	function extra_tablenav( $which ) {
		if ( $which == 'top' ){
			$this->filters_form();
		}
	}

	function get_columns(){
		return apply_filters(
			'wp_stream_list_table_columns',
			array(
				'date'      => __( 'Date', 'stream' ),
				'summary'   => __( 'Summary', 'stream' ),
				'author'    => __( 'Author', 'stream' ),
				'connector' => __( 'Connector', 'stream' ),
				'context'   => __( 'Context', 'stream' ),
				'action'    => __( 'Action', 'stream' ),
				'ip'        => __( 'IP Address', 'stream' ),
				'id'        => __( 'ID', 'stream' ),
			)
		);
	}

	function get_sortable_columns() {
		return array(
			'id'   => 'id',
			'date' => 'date',
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
				'per_page' => $this->get_items_per_page( 'edit_stream_per_page', 20 ),
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
			/*$1%s*/ 'wp_stream_checkbox',
			/*$2%s*/ $item->ID
		);
	}

	function get_records() {
		$args = array();

		// Parse sorting params
		if ( ! $order = filter_input( INPUT_GET, 'order' ) ) {
			$order = 'DESC';
		}
		if ( ! $orderby = filter_input( INPUT_GET, 'orderby' ) ) {
			$orderby = '';
		}
		$args['order']   = $order;
		$args['orderby'] = $orderby;

		// Filters
		$allowed_params = array(
			'connector', 'context', 'action',
			'author', 'object_id', 'search',
			'date', 'date_from', 'date_to',
			'record__in',
		);

		foreach ( $allowed_params as $param ) {
			if ( $paramval = filter_input( INPUT_GET, $param ) ) {
				$args[$param] = $paramval;
			}
		}
		$args['paged'] = $this->get_pagenum();

		if ( ! isset( $args['records_per_page'] ) ) {
			$args['records_per_page'] = $this->get_items_per_page( 'edit_stream_per_page', 20 );
		}

		$items = stream_query( $args );
		return $items;
	}

	function get_total_found_rows() {
		global $wpdb;
		return $wpdb->get_var( 'SELECT FOUND_ROWS()' );
	}

	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'date':
				$out  = sprintf( '<strong>' . __( '%s ago', 'stream' ) . '</strong>', human_time_diff( strtotime( $item->created ) ) );
				$out .= '<br />';
				$out .= $this->column_link( get_date_from_gmt( $item->created, 'Y/m/d' ), 'date', date( 'Y/m/d', strtotime( $item->created ) ) );
				$out .= '<br />';
				$out .= get_date_from_gmt( $item->created, 'h:i:s A' );
				break;

			case 'summary':
				if ( $item->object_id ) {
					$out = $this->column_link(
						$item->summary,
						array(
							'object_id' => $item->object_id,
							'context'   => $item->context,
						),
						null,
						__( 'View all records for this object', 'stream' )
					);
				} else {
					$out = $item->summary;
				}
				$out .= $this->get_action_links( $item );
				break;

			case 'author':
				$user = get_user_by( 'id', $item->author );
				if ( $user ) {
					global $wp_roles;
					$author_ID   = isset( $user->ID ) ? $user->ID : 0;
					$author_name = isset( $user->display_name ) ? $user->display_name : null;
					$author_role = isset( $user->roles[0] ) ? $wp_roles->role_names[$user->roles[0]] : null;
					$out = sprintf(
						'<a href="%s">%s <span>%s</span></a><br /><small>%s</small>',
						add_query_arg( array( 'author' => $author_ID ), admin_url( 'admin.php?page=wp_stream' ) ),
						get_avatar( $author_ID, 40 ),
						$author_name,
						$author_role
					);
				} else {
					$out = 'N/A';
				}
				break;

			case 'connector':
				$out = $this->column_link( WP_Stream_Connectors::$term_labels['stream_connector'][$item->connector], 'connector', $item->connector );
				break;

			case 'context':
			case 'action':
				$display_col = isset( WP_Stream_Connectors::$term_labels['stream_'.$column_name][$item->{$column_name}] )
					? WP_Stream_Connectors::$term_labels['stream_'.$column_name][$item->{$column_name}]
					: $item->{$column_name};
				$out = $this->column_link( $display_col, $column_name, $item->{$column_name} );
				break;

			case 'ip':
				$out = $this->column_link( $item->{$column_name}, 'ip', $item->{$column_name} );
				break;

			case 'id':
				$out = intval( $item->ID );
				break;

			default:
				// Register inserted column defaults. Must match a column header from get_columns.
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
						if ( $column_title == $column_name && has_action( 'wp_stream_insert_column_default-' . $column_title ) ) {
							$out = do_action( 'wp_stream_insert_column_default-' . $column_title, $item );
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
		$action_links = apply_filters( 'wp_stream_action_links_' . $record->connector, array(), $record );
		$custom_links = apply_filters( 'wp_stream_custom_action_links_' . $record->connector, array(), $record );

		if ( $action_links || $custom_links ) {
			$out .= '<div class="row-actions">';
		}

		if ( $action_links ) {

			$links = array();
			$i     = 0;
			foreach ( $action_links as $al_title => $al_href ) {
				$i++;
				$links[] = sprintf(
					'<span><a href="%s" class="action-link">%s</a>%s</span>',
					$al_href,
					$al_title,
					( $i === count( $action_links ) ) ? null : ' | '
				);
			}
			$out .= implode( '', $links );
		}

		if ( $action_links && $custom_links ) {
			$out .= ' | ';
		}

		if ( $custom_links && is_array( $custom_links ) ) {
			$last_link = end( $custom_links );
			foreach ( $custom_links as $key => $link ) {
				$out .= $link;
				if ( $key != $last_link ) {
					$out .= ' | ';
				}
			}
		}

		if ( $action_links || $custom_links ) {
			$out .= '</div>';
		}

		return $out;
	}

	function column_link( $display, $key, $value = null, $title = null ) {
		$url = admin_url( 'admin.php?page=wp_stream' );

		$args = ! is_array( $key ) ? array( $key => $value ) : $key;

		foreach ( $args as $k => $v ) {
			$url = add_query_arg( $k, $v, $url );
		}

		return sprintf(
			'<a href="%s" title="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $title ),
			esc_html( $display )
		);
	}

	function filters_form() {
		$filters = array();

		$filters_string = sprintf( '<input type="hidden" name="page" value="%s"/>', 'wp_stream' );

		$users = array();
		foreach ( (array) get_users( array( 'orderby' => 'display_name' ) ) as $user ) {
			$users[$user->ID] = $user->display_name;
		}

		$filters['author'] = array(
			'title' => __( 'authors', 'stream' ),
			'items' => $users,
		);

		$connectors = WP_Stream_Connectors::$term_labels['stream_connector'];
		asort( $connectors );

		$filters['connector'] = array(
			'title' => __( 'connectors', 'stream' ),
			'items' => WP_Stream_Connectors::$term_labels['stream_connector'],
		);

		$contexts = WP_Stream_Connectors::$term_labels['stream_context'];
		asort( $contexts );

		$filters['context'] = array(
			'title' => __( 'contexts', 'stream' ),
			'items' => $contexts,
		);

		$actions = WP_Stream_Connectors::$term_labels['stream_action'];
		asort( $actions );

		$filters['action'] = array(
			'title' => __( 'actions', 'stream' ),
			'items' => $actions,
		);

		$filters = apply_filters( 'wp_stream_list_table_filters', $filters );

		$filters_string .= $this->filter_date();

		foreach ( $filters as $name => $data ) {
			$filters_string .= $this->filter_select( $name, $data['title'], $data['items'] );
		}

		$filters_string .= sprintf( '<input type="submit" id="record-query-submit" class="button" value="%s">', __( 'Filter', 'stream' ) );
		$url = admin_url( 'admin.php' );
		echo sprintf( '<div class="alignleft actions">%s</div>', $filters_string ); // xss okay
	}

	function filter_select( $name, $title, $items ) {
		$options  = array( sprintf( __( '<option value=""></option>', 'stream' ), $title ) );
		$selected = filter_input( INPUT_GET, $name );
		foreach ( $items as $v => $label ) {
			$options[$v] = sprintf(
				'<option value="%s" %s>%s</option>',
				$v,
				selected( $v, $selected, false ),
				$label
			);
		}
		$out = sprintf(
			'<select name="%s" class="chosen-select" data-placeholder="Show all %s">%s</select>',
			$name,
			$title,
			implode( '', $options )
		);
		return $out;
	}

	function filter_search() {
		$out = sprintf(
			'<p class="search-box">
				<label class="screen-reader-text" for="record-search-input">%1$s:</label>
				<input type="search" id="record-search-input" name="search" value="%2$s" />
				<input type="submit" name="" id="search-submit" class="button" value="%1$s" />
			</p>',
			esc_attr__( 'Search Records', 'stream' ),
			isset( $_GET['search'] ) ? esc_attr( $_GET['search'] ) : null
		);

		return $out;
	}

	function filter_date() {
		wp_register_style( 'jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
		wp_enqueue_style( 'jquery-ui' );

		wp_enqueue_script( 'jquery-ui-datepicker' );

		$out = sprintf(
			'<div id="filter-date-range">
				<label class="screen-reader-text" for="date_from">%1$s:</label>
				<input type="text" name="date_from" id="date_from" class="date-picker" placeholder="%1$s" size="9" value="%2$s" />
				<label class="screen-reader-text" for="date_to">%3$s:</label>
				<input type="text" name="date_to" id="date_to" class="date-picker" placeholder="%3$s" size="9" value="%4$s" />
			</div>',
			esc_attr__( 'Date start', 'stream' ),
			isset( $_GET['date_from'] ) ? esc_attr( $_GET['date_from'] ) : null,
			esc_attr__( 'Date end', 'stream' ),
			isset( $_GET['date_to'] ) ? esc_attr( $_GET['date_to'] ) : null
		);

		return $out;
	}

	function display() {
		echo '<form method="get" action="', esc_attr( admin_url( 'admin.php' ) ), '">';
		echo $this->filter_search(); // xss okay
		parent::display();
		echo '</form>';
	}

	function display_tablenav( $which ) {
		if ( 'top' == $which ) : ?>
			<div class="tablenav <?php echo esc_attr( $which ); ?>">
				<?php
				$this->extra_tablenav( $which );
				$this->pagination( $which );
				?>

				<br class="clear" />
			</div>
		<?php  else : ?>
			<div class="tablenav <?php echo esc_attr( $which ); ?>">
				<?php
				do_action( 'wp_stream_after_list_table' );
				$this->extra_tablenav( $which );
				$this->pagination( $which );
				?>

				<br class="clear" />
			</div>
		<?php
		endif;
	}


	static function set_screen_option( $dummy, $option, $value ) {
		if ( $option == 'edit_stream_per_page' ) {
			return $value;
		} else {
			return $dummy;
		}
	}

	static function set_live_update_option( $dummy, $option, $value ) {
		if ( $option == 'stream_live_update_records' ) {
			$value = $_POST['stream_live_update_records'];
			return $value;
		} else {
			return $dummy;
		}
	}

	static function live_update_checkbox( $status, $args ) {
		$user_id = get_current_user_id();
		$option  = get_user_meta( $user_id, 'stream_live_update_records', true );
		$value   = isset( $option ) ? $option : 'on';
		$nonce   = wp_create_nonce( 'stream_live_update_nonce' );
		ob_start();
		?>
		<fieldset>
			<h5><?php esc_html_e( 'Live updates', 'stream' ) ?></h5>
			<div><input type="hidden" name="enable_live_update_nonce" id="enable_live_update_nonce" value="<?php echo esc_attr( $nonce ) ?>" /></div>
			<div><input type="hidden" name="enable_live_update_user" id="enable_live_update_user" value="<?php echo absint( $user_id ) ?>" /></div>
			<div class="metabox-prefs stream-live-update-checkbox">
				<label for="enable_live_update">
					<input type="checkbox" value="on" name="enable_live_update" id="enable_live_update" <?php checked( 'on', $value ) ?> />
					<?php esc_html_e( 'Enabled', 'stream' ) ?><span class="spinner"></span>
				</label>
			</div>
		</fieldset>
		<?php
		return ob_get_clean();
	}
}
