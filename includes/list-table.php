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
		add_action( 'wp_ajax_wp_stream_filters', array( __CLASS__, 'ajax_filters' ) );

		set_screen_options();
	}

	static function ajax_filters() {
		$results = array(
			array(
				'id'   => 1,
				'text' => 'Garfield',
			),
			array(
				'id'   => 2,
				'text' => 'Odie',
			),
		);
		echo json_encode( $results );
		die();
	}

	function extra_tablenav( $which ) {
		if ( 'top' === $which ){
			$this->filters_form();
		}
	}

	function get_columns(){
		/**
		 * Allows devs to add new columns to table
		 *
		 * @param  array  default columns
		 * @return array  updated list of columns
		 */
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
			'id'   => array( 'ID', false ),
			'date' => array( 'date', false ),
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
		if ( ! $order = wp_stream_filter_input( INPUT_GET, 'order' ) ) {
			$order = 'DESC';
		}
		if ( ! $orderby = wp_stream_filter_input( INPUT_GET, 'orderby' ) ) {
			$orderby = '';
		}
		$args['order']   = $order;
		$args['orderby'] = $orderby;

		// Filters
		$allowed_params = array(
			'connector', 'context', 'action',
			'author', 'object_id', 'search',
			'date', 'date_from', 'date_to',
			'record__in', 'ip',
		);

		foreach ( $allowed_params as $param ) {
			if ( $paramval = wp_stream_filter_input( INPUT_GET, $param ) ) {
				$args[ $param ] = $paramval;
			}
		}
		$args['paged'] = $this->get_pagenum();

		if ( ! isset( $args['records_per_page'] ) ) {
			$args['records_per_page'] = $this->get_items_per_page( 'edit_stream_per_page', 20 );
		}

		// Remove excluded records as per settings
		add_filter( 'stream_query_args', array( 'WP_Stream_Settings', 'remove_excluded_record_filter' ), 10, 1 );

		$items = stream_query( $args );

		// Remove filter added before
		remove_filter( 'stream_query_args', array( 'WP_Stream_Settings', 'remove_excluded_record_filter' ), 10, 1 );

		return $items;
	}

	function get_total_found_rows() {
		global $wpdb;
		return $wpdb->get_var( 'SELECT FOUND_ROWS()' );
	}

	function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'date' :
				$date_string = sprintf(
					'<time datetime="%s" class="relative-time">%s</time>',
					$item->created,
					get_date_from_gmt( $item->created, 'Y/m/d' )
				);
				$out = $this->column_link( $date_string, 'date', date( 'Y/m/d', strtotime( $item->created ) ) );
				$out .= '<br />';
				$out .= get_date_from_gmt( $item->created, 'h:i:s A' );
				break;

			case 'summary' :
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

			case 'author' :
				$user = get_user_by( 'id', $item->author );
				if ( $user ) {
					global $wp_roles;

					$author_ID   = isset( $user->ID ) ? $user->ID : 0;
					$author_name = isset( $user->display_name ) ? $user->display_name : null;
					$author_role = isset( $user->roles[0] ) ? $wp_roles->role_names[ $user->roles[0] ] : null;

					$out = sprintf(
						'<a href="%s">%s <span>%s</span></a><br /><small>%s</small>',
						add_query_arg(
							array(
								'page'   => WP_Stream_Admin::RECORDS_PAGE_SLUG,
								'author' => absint( $author_ID ),
							),
							admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
						),
						get_avatar( $author_ID, 40 ),
						$author_name,
						$author_role
					);
				} else {
					$out = __( 'N/A', 'stream' );
				}
				break;

			case 'connector':
			case 'context':
			case 'action':
				$out = $this->column_link( $this->get_term_title( $item->{$column_name}, $column_name ), $column_name, $item->{$column_name} );
				break;

			case 'ip' :
				$out = $this->column_link( $item->{$column_name}, 'ip', $item->{$column_name} );
				break;

			case 'id' :
				$out = absint( $item->ID );
				break;

			default :
				/**
				 * Registers new Columns to be inserted into the table.  The cell contents of this column is set
				 * below with 'wp_stream_inster_column_default-'
				 *
				 * @param array  $new_columns  Array of new column titles to add
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
						if ( $column_title == $column_name && has_action( 'wp_stream_insert_column_default-' . $column_title ) ) {
							/**
							 * This action allows for the addition of content under the specified column ($column_title)
							 *
							 * @param  string  $column_title  Title of the column (set in wp_stream_register_column_defaults)
							 * @param  obj     $item          Contents of the row
							 */
							$out = do_action( 'wp_stream_insert_column_default-' . $column_title, $item );
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


	public static function get_action_links( $record ){
		$out = '';

		/**
		 * Filter allows modification of action links for a specific connector
		 *
		 * @param  string  connector
		 * @param  array   array of action links for this connector
		 * @param  obj     record
		 * @return arrray  action links for this connector
		 */
		$action_links = apply_filters( 'wp_stream_action_links_' . $record->connector, array(), $record );

		/**
		 * Filter allows addition of custom links for a specific connector
		 *
		 * @param  string  connector
		 * @param  array   array of custom links for this connector
		 * @param  obj     record
		 * @return arrray  custom links for this connector
		 */
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
					( count( $action_links ) === $i ) ? null : ' | '
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
				if ( $key !== $last_link ) {
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
		$url = add_query_arg(
			array(
				'page' => WP_Stream_Admin::RECORDS_PAGE_SLUG,
			),
			admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
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
	 * @uses   existing_records (see query.php)
	 * @since  1.0.4
	 * @param  string  Column requested
	 * @param  string  Table to be queried
	 * @return array   options to be displayed in search filters
	 */
	function assemble_records( $column, $table = '' ) {
		if ( 'author' === $column ) {
			$all_records = array();
			$authors     = get_users();
			foreach ( $authors as $author ) {
				$author = get_user_by( 'id', $author->ID );
				if ( $author ) {
					$all_records[ $author->ID ] = $author->display_name;
				}
			}
		} else {
			$prefixed_column = sprintf( 'stream_%s', $column );
			$all_records     = WP_Stream_Connectors::$term_labels[ $prefixed_column ];

			if ( 'connector' === $column ) {
				/**
				 * Toggle visibility of disabled connectors on list table filter dropdown
				 *
				 * @param bool $hidden Visibility status, hidden by default.
				 */
				$hide_disabled_connectors_filter = apply_filters( 'wp_stream_list_table_hide_disabled_connectors', true );

				if ( true === $hide_disabled_connectors_filter ) {
					$excluded_connectors = WP_Stream_Settings::get_excluded_by_key( 'connectors' );
					foreach ( array_keys( $all_records ) as $_connector ) {
						if ( in_array( $_connector, $excluded_connectors ) ) {
							unset( $all_records[ $_connector ] );
						}
					}
				}
			}
		}

		$existing_records = existing_records( $column, $table );
		$active_records   = array();
		$disabled_records = array();

		foreach ( $all_records as $record => $label ) {
			if ( array_key_exists( $record , $existing_records ) ) {
				$active_records[ $record ] = array( 'label' => $label, 'disabled' => '' );
			} else {
				$disabled_records[ $record ] = array( 'label' => $label, 'disabled' => 'disabled="disabled"' );
			}
		}

		asort( $active_records );
		asort( $disabled_records );

		// Not using array_merge() in order to preserve the array index for the Authors dropdown which uses the user_id as the key
		$all_records = $active_records + $disabled_records;

		return $all_records;
	}

	function filters_form() {
		$filters = array();

		$filters_string = sprintf( '<input type="hidden" name="page" value="%s"/>', 'wp_stream' );

		$authors_records = $this->assemble_records( 'author', 'stream' );

		foreach ( $authors_records as $user_id => $user ) {
			if ( preg_match( '# src=[\'" ]([^\'" ]*)#', get_avatar( $user_id, 16 ), $gravatar_src_match ) ) {
				list( $gravatar_src, $gravatar_url ) = $gravatar_src_match;
				$authors_records[ $user_id ]['icon'] = $gravatar_url;
			}
		}

		$filters['author'] = array();
		$filters['author']['title'] = __( 'authors', 'stream' );

		if ( count( $authors_records ) <= WP_Stream_Admin::PRELOAD_AUTHORS_MAX ) {
			$filters['author']['items'] = $authors_records;
		} else {
			$filters['author']['ajax'] = true;
		}

		$filters['connector'] = array(
			'title' => __( 'connectors', 'stream' ),
			'items' => $this->assemble_records( 'connector' ),
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
		 * @return array  Updated array of filters
		 */
		$filters = apply_filters( 'wp_stream_list_table_filters', $filters );

		$filters_string .= $this->filter_date();

		foreach ( $filters as $name => $data ) {
			$filters_string .= $this->filter_select( $name, $data['title'], isset( $data['items'] ) ? $data['items'] : array(), isset( $data['ajax'] ) && $data['ajax'] );
		}

		$filters_string .= sprintf( '<input type="submit" id="record-query-submit" class="button" value="%s">', __( 'Filter', 'stream' ) );
		$url = admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE );

		echo sprintf( '<div class="alignleft actions">%s</div>', $filters_string ); // xss ok
	}

	function filter_select( $name, $title, $items, $ajax ) {
		if ( $ajax ) {
			$out = sprintf(
				'<select name="%s" class="chosen-select" data-placeholder="%s">%s</select>',
				esc_attr( $name ),
				esc_attr( wp_stream_filter_input( INPUT_GET, $name ) ),
				esc_html( $title )
			);
		} else {
			$options  = array( '<option value=""></option>' );
			$selected = wp_stream_filter_input( INPUT_GET, $name );
			foreach ( $items as $v => $label ) {
				$options[ $v ] = sprintf(
					'<option value="%s" %s %s %s>%s</option>',
					$v,
					selected( $v, $selected, false ),
					$label['disabled'],
					isset( $label['icon'] ) ? sprintf( ' data-icon="%s"', esc_attr( $label['icon'] ) ) : '',
					$label['label']
				);
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
		wp_enqueue_style( 'jquery-ui' );
		wp_enqueue_style( 'wp-stream-datepicker' );

		wp_enqueue_script( 'jquery-ui-datepicker' );

		$out = sprintf(
			'<div id="filter-date-range">
				<label class="screen-reader-text" for="date_from">%1$s:</label>
				<input type="text" name="date_from" id="date_from" class="date-picker" placeholder="%1$s" size="14" value="%2$s" />
				<label class="screen-reader-text" for="date_to">%3$s:</label>
				<input type="text" name="date_to" id="date_to" class="date-picker" placeholder="%3$s" size="14" value="%4$s" />
			</div>',
			esc_attr__( 'Start date', 'stream' ),
			isset( $_GET['date_from'] ) ? esc_attr( $_GET['date_from'] ) : null,
			esc_attr__( 'End date', 'stream' ),
			isset( $_GET['date_to'] ) ? esc_attr( $_GET['date_to'] ) : null
		);

		return $out;
	}

	function display() {
		echo '<form method="get" action="' . admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE ) . '">';
		echo $this->filter_search(); // xss ok
		parent::display();
		echo '</form>';
	}

	function display_tablenav( $which ) {
		if ( 'top' === $which ) : ?>
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
				/**
				 * Action allows for mods after the list table display
				 */
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
		if ( 'edit_stream_per_page' === $option ) {
			return $value;
		} else {
			return $dummy;
		}
	}

	static function set_live_update_option( $dummy, $option, $value ) {
		if ( 'stream_live_update_records' === $option ) {
			$value = $_POST['stream_live_update_records'];
			return $value;
		} else {
			return $dummy;
		}
	}

	static function live_update_checkbox( $status, $args ) {
		$user_id = get_current_user_id();
		$option  = ( 'off' !== get_user_meta( $user_id, 'stream_live_update_records', true ) );
		$nonce   = wp_create_nonce( 'stream_live_update_nonce' );
		ob_start();
		?>
		<fieldset>
			<h5><?php esc_html_e( 'Live updates', 'stream' ) ?></h5>
			<div><input type="hidden" name="enable_live_update_nonce" id="enable_live_update_nonce" value="<?php echo esc_attr( $nonce ) ?>" /></div>
			<div><input type="hidden" name="enable_live_update_user" id="enable_live_update_user" value="<?php echo absint( $user_id ) ?>" /></div>
			<div class="metabox-prefs stream-live-update-checkbox">
				<label for="enable_live_update">
					<input type="checkbox" value="on" name="enable_live_update" id="enable_live_update" <?php checked( $option ) ?> />
					<?php esc_html_e( 'Enabled', 'stream' ) ?><span class="spinner"></span>
				</label>
			</div>
		</fieldset>
		<?php
		return ob_get_clean();
	}

}
