<?php

class WP_Stream_List_Table extends WP_List_Table {

	public $perpage;

	function __construct( $args = array() ) {
		parent::__construct(
			array(
				'plural' => 'records',
				'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
				)
			);

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
				'user'      => __( 'User', 'stream' ),
				'context'   => __( 'Context', 'stream' ),
				'action'    => __( 'Action', 'stream' ),
				'connector' => __( 'Connector', 'stream' ),
				'ip'        => __( 'IP Address', 'stream' ),
				'id'        => __( 'ID', 'stream' ),
				)
			);
	}

	function get_sortable_columns() {
		return array(
			'id' => 'id',
			'date' => 'date',
			);
	}

	function prepare_items() {
		$screen = get_current_screen();

		$columns  = $this->get_columns();
		$sortable = $this->get_sortable_columns();
		$hidden   = get_hidden_columns( $screen );

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->items = $this->get_records();

		$this->perpage = apply_filters( 'wp_stream_list_table_perpage', 10 );

		$total_items = $this->get_total_found_rows();

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page' => $this->perpage,
				)
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
			);
		foreach ( $allowed_params as $param ) {
			if ( $paramval = filter_input( INPUT_GET, $param ) ) {
				$args[$param] = $paramval;
			}
		}

		$args['paged'] = $this->get_pagenum();

		if ( ! isset( $args['record_per_page'] ) ) {
			$args['record_per_page'] = $this->perpage;
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
				$out  = $this->column_link( date( 'Y/m/d', strtotime( $item->created ) ), 'date', date( 'Y/m/d', strtotime( $item->created ) ) );
				$out .= '<br />';
				$out .= date( "\nh:i:s A", strtotime( $item->created ) );
				break;

			case 'summary':
				if ( $item->object_id ) {
					$out = $this->column_link(
						$item->summary,
						array(
							'object_id' => $item->object_id,
							'context' => $item->context,
							)
						);
				} else {
					$out = $item->summary;
				}
				break;

			case 'user':
				$user = get_user_by( 'id', $item->author );
				if ( $user ) {
					global $wp_roles;
					$author_ID   = isset( $user->ID ) ? $user->ID : 0;
					$author_name = isset( $user->display_name ) ? $user->display_name : null;
					$author_role = isset( $user->roles[0] ) ? $wp_roles->role_names[$user->roles[0]] : null;
					$out = sprintf(
						'<a style="vertical-align:top" href="%s"><span style="float:left;padding-right:5px;">%s</span> %s</a><br /><small>%s</small>',
						add_query_arg( array( 'author' => $author_ID ), admin_url( 'admin.php?page=wp_stream' ) ),
						get_avatar( $author_ID, 36 ),
						$author_name,
						$author_role
					);
				} else {
					$out = 'NA';
				}
				break;

			case 'context':
			case 'action':
				$out = $this->column_link( WP_Stream_Connectors::$term_labels['stream_'.$column_name][$item->{$column_name}], $column_name, $item->{$column_name} );
				break;

			case 'id':
				$out = intval( $item->ID );
				break;

			case 'ip':
				$out = $this->column_link( $item->{$column_name}, 'ip', $item->{$column_name} );
				break;

			case 'connector':
				$out = $this->column_link( WP_Stream_Connectors::$term_labels['stream_connector'][$item->connector], 'connector', $item->connector );
				break;

			default:
				$out = $column_name; // xss okay
				break;
		}
		echo $out; //xss okay
	}

	function column_link( $display, $key, $value = null ) {
		$url = admin_url( 'admin.php?page=wp_stream' );

		if ( ! is_array( $key ) ) {
			$args = array( $key => $value );
		} else {
			$args = $key;
		}
		foreach ( $args as $k => $v ) {
			$url = add_query_arg( $k, $v, $url );
		}

		return sprintf(
			'<a href="%s">%s</a>',
			$url,
			$display
			); // xss okay
	}

	function filters_form() {
		$filters = array();

		$filters_string = sprintf( '<input type="hidden" name="page" value="%s"/>', 'wp_stream' );

		$users = array();
		foreach ( get_users() as $user ) {
			$users[ $user->ID ] = $user->display_name;
		}
		$filters['author'] = array(
			'title' => __( 'users', 'stream' ),
			'items' => $users,
			);

		$filters['connector'] = array(
			'title' => __( 'connectors', 'stream' ),
			'items' => WP_Stream_Connectors::$term_labels['stream_connector'],
			);

		$filters['context'] = array(
			'title' => __( 'contexts', 'stream' ),
			'items' => WP_Stream_Connectors::$term_labels['stream_context'],
			);

		$filters['action'] = array(
			'title' => __( 'actions', 'stream' ),
			'items' => WP_Stream_Connectors::$term_labels['stream_action'],
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
		$options  = array( sprintf( __( '<option value="">Show all %s</option>', 'stream' ), $title ) );
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
			'<select name="%s">%s</select>',
			$name,
			implode( '', $options )
			);
		return $out;
	}

	function filter_search() {
		$out = sprintf(
			'<p class="search-box">
			<label class="screen-reader-text" for="record-search-input">%1$s:</label>
			<input type="search" id="record-search-input" name="search" value="%2$s">
			<input type="submit" name="" id="search-submit" class="button" value="%1$s">
			</p>',
			__( 'Search Records', 'stream' ),
			isset( $_GET['search'] ) ? esc_attr( $_GET['search'] ) : null
			);
		return $out;
	}

	function filter_date() {
		wp_register_style( 'jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
		wp_enqueue_style( 'jquery-ui' );

		wp_enqueue_script( 'jquery-ui-datepicker' );
		$out  = '<input type="text" name="date_from" class="date-picker" placeholder="Date from:" style="float:left" size="9"/>';
		$out .= '<input type="text" name="date_to" class="date-picker" placeholder="Date to:" style="float:left" size="9"/>';
		return $out;
	}

	function display() {
		echo '<form method="get" action="', esc_attr( admin_url( 'admin.php' ) ), '">';
		echo $this->filter_search(); // xss okay
		parent::display();
		echo '</form>';
	}

	function display_tablenav( $which ) {
		if ( 'top' == $which )
	?>
	<div class="tablenav <?php echo esc_attr( $which ); ?>">
		<?php
		$this->extra_tablenav( $which );
		$this->pagination( $which );
		?>

		<br class="clear" />
		</div>
	<?php
	}
}