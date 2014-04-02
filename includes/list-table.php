<?php

class WP_Stream_List_Table extends WP_List_Table {

	function __construct( $args = array() ) {
		parent::__construct(
			array(
				'post_type' => 'stream',
				'plural'    => 'records',
				'screen'    => isset( $args['screen'] ) ? $args['screen'] : null,
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

		add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );
		add_filter( 'screen_settings', array( __CLASS__, 'screen_controls' ), 10, 2 );
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
		if ( 'top' === $which ) {
			$this->filters_form();
		}
	}

	function get_columns() {
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

	function get_hidden_columns() {
		if ( ! $user = wp_get_current_user() ) {
			return array();
		}

		// Directly checking the user meta; to check whether user has changed screen option or not
		$hidden = get_user_meta( $user->ID, 'manage' . $this->screen->id . 'columnshidden', true );

		// If user meta is not found; add the default hidden column 'id'
		if ( false === $hidden ) {
			$hidden = array( 'id' );
			update_user_meta( $user->ID, 'manage' . $this->screen->id . 'columnshidden', $hidden );
		}

		return $hidden;
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
			'connector',
			'context',
			'action',
			'author',
			'object_id',
			'search',
			'date',
			'date_from',
			'date_to',
			'record__in',
			'ip',
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

		$items = stream_query( $args );

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
				$out         = $this->column_link( $date_string, 'date', date( 'Y/m/d', strtotime( $item->created ) ) );
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
						if ( $column_title == $column_name && has_action( 'wp_stream_insert_column_default-' . $column_title ) ) {
							/**
							 * This action allows for the addition of content under the specified column ($column_title)
							 *
							 * @param  string $column_title Title of the column (set in wp_stream_register_column_defaults)
							 * @param  obj    $item         Contents of the row
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

		if ( $action_links ) {

			$links = array();
			$i     = 0;
			foreach ( $action_links as $al_title => $al_href ) {
				$i ++;
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
		if ( isset( WP_Stream_Connectors::$term_labels["stream_$type"][ $term ] ) ) {
			return WP_Stream_Connectors::$term_labels["stream_$type"][ $term ];
		}
		else {
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
	 *
	 * @param  string  Column requested
	 * @param  string  Table to be queried
	 *
	 * @return array   options to be displayed in search filters
	 */
	function assemble_records( $column, $table = '' ) {

		$setting_key = self::get_column_excluded_setting_key( $column );

		/**
		 * Toggle visibility of disabled connectors/actions/contexts on list table filter dropdown
		 *
		 * @param bool $hidden Visibility status, default is Hide Previous Record value set in Exclude setting.
		 */
		$hide_disabled_column_filter = apply_filters( 'wp_stream_list_table_hide_disabled_ ' . $setting_key, ( WP_Stream_Settings::$options[ 'exclude_hide_previous_records' ] === 0 ) ? false : true );

		if ( 'author' === $column ) {
			$all_records = array();
			$authors     = get_users();
			if ( $hide_disabled_column_filter ) {
				$excluded_records = WP_Stream_Settings::get_excluded_by_key( $setting_key );
			}

			foreach ( $authors as $author ) {
				if ( $hide_disabled_column_filter && in_array( $author->ID, $excluded_records ) ) {
					continue;
				}
				$author = get_user_by( 'id', $author->ID );
				if ( $author ) {
					$all_records[ $author->ID ] = $author;
				}
			}
		} else {
			$prefixed_column = sprintf( 'stream_%s', $column );
			$all_records     = WP_Stream_Connectors::$term_labels[ $prefixed_column ];

			if ( true === $hide_disabled_column_filter ) {
				$excluded_records = WP_Stream_Settings::get_excluded_by_key( $setting_key );
				foreach ( array_keys( $all_records ) as $_connector ) {
					if ( in_array( $_connector, $excluded_records ) ) {
						unset( $all_records[ $_connector ] );
					}
				}
			}
		}

		$existing_records = existing_records( $column, $table );
		$active_records   = array();
		$disabled_records = array();

		foreach ( $all_records as $record => $label ) {
			if ( array_key_exists( $record, $existing_records ) ) {
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

		$user_id = get_current_user_id();

		$filters_option = get_user_meta( $user_id, 'stream_toggle_filters', true );

		$filters = array();

		$filters_string = sprintf( '<input type="hidden" name="page" value="%s"/>', 'wp_stream' );

		$filters_string .= sprintf( __( '%1$sShow filter controls via the screen options tab above%2$s', 'stream' ), '<span class="filter_info" style="display:none">', '</span>' );

		$authors_records = $this->assemble_records( 'author', 'stream' );

		foreach ( $authors_records as $user_id => $user ) {
			$user = $user['label'];
			if ( preg_match( '# src=[\'" ]([^\'" ]*)#', get_avatar( $user_id, 16 ), $gravatar_src_match ) ) {
				list( $gravatar_src, $gravatar_url ) = $gravatar_src_match;
				$authors_records[ $user_id ]['icon'] = $gravatar_url;
			}
			$user_roles = array_map( 'ucwords', $user->roles );
			$authors_records[ $user_id ]['label']   = $user->display_name;
			$authors_records[ $user_id ]['tooltip'] = sprintf(
				__( "ID: %d\nUser: %s\nEmail: %s\nRole: %s", 'stream' ),
				$user->ID,
				$user->user_login,
				$user->user_email,
				implode( ', ', $user_roles )
			);
		}

		$filters['author']          = array();
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
		 *
		 * @return array  Updated array of filters
		 */
		$filters = apply_filters( 'wp_stream_list_table_filters', $filters );

		$filters_string .= $this->filter_date();

		foreach ( $filters as $name => $data ) {
			$filters_string .= $this->filter_select( $name, $data['title'], isset( $data['items'] ) ? $data['items'] : array(), isset( $data['ajax'] ) && $data['ajax'], $filters_option[ $name ] );
		}

		$filters_string .= sprintf( '<input type="submit" id="record-query-submit" class="button" value="%s">', __( 'Filter', 'stream' ) );
		$url = admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE );

		printf( '<div class="alignleft actions">%s</div>', $filters_string ); // xss ok
	}

	function filter_select( $name, $title, $items, $ajax, $enabled = true ) {


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
					'<option value="%s" %s %s %s title="%s">%s</option>',
					$v,
					selected( $v, $selected, false ),
					$label['disabled'],
					isset( $label['icon'] ) ? sprintf( ' data-icon="%s"', esc_attr( $label['icon'] ) ) : '',
					isset( $label['tooltip'] ) ? esc_attr( $label['tooltip'] ) : '',
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

		require_once WP_STREAM_INC_DIR . 'date-interval.php';

		wp_enqueue_style( 'jquery-ui' );
		wp_enqueue_style( 'wp-stream-datepicker' );

		wp_enqueue_script( 'jquery-ui-datepicker' );

		$date_interval = new WP_Stream_Date_Interval();

		$date_predefined = wp_stream_filter_input( INPUT_GET, 'date_predefined' );
		$date_from       = wp_stream_filter_input( INPUT_GET, 'date_from' );
		$date_to         = wp_stream_filter_input( INPUT_GET, 'date_to' );

		ob_start();
		?>
		<div class="date-interval">

			<select class="field-predefined hide-if-no-js" name="date_predefined" data-placeholder="<?php _e( 'All Time', 'stream' ); ?>">
				<option></option>
				<option value="custom" <?php selected( 'custom' === $date_predefined ); ?>><?php esc_attr_e( 'Custom', 'stream' ) ?></option>
				<?php foreach ( $date_interval->intervals as $key => $interval ) {
					printf(
						'<option value="%s" data-from="%s" data-to="%s" %s>%s</option>',
						esc_attr( $key ),
						esc_attr( $interval['start']->format( 'Y/m/d' ) ),
						esc_attr( $interval['end']->format( 'Y/m/d' ) ),
						selected( $key === $date_predefined ),
						esc_html( $interval['label'] )
					); // xss ok
				} ?>
			</select>

			<div class="date-inputs">
				<div class="box">
					<i class="date-remove dashicons"></i>
					<input type="text"
						   name="date_from"
						   class="date-picker field-from"
						   placeholder="<?php esc_attr_e( 'Start date', 'stream' ) ?>"
						   value="<?php echo esc_attr( $date_from ) ?>">
				</div>
				<span class="connector dashicons"></span>

				<div class="box">
					<i class="date-remove dashicons"></i>
					<input type="text"
						   name="date_to"
						   class="date-picker field-to"
						   placeholder="<?php esc_attr_e( 'End date', 'stream' ) ?>"
						   value="<?php echo esc_attr( $date_to ) ?>">
				</div>
			</div>

		</div>
		<?php

		return ob_get_clean();
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
				$this->pagination( $which );
				$this->extra_tablenav( $which );
				?>

				<br class="clear" />
			</div>
		<?php else : ?>
			<div class="tablenav <?php echo esc_attr( $which ); ?>">
				<?php
				/**
				 * Action allows for mods after the list table display
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
		if ( 'stream_live_update_records' === $option ) {
			$value = $_POST['stream_live_update_records'];

			return $value;
		} else {
			return $dummy;
		}
	}

	static function screen_controls( $status, $args ) {

		$user_id                 = get_current_user_id();
		$option                  = get_user_meta( $user_id, 'enable_live_update', true );
		$filters_option_defaults = array(
			'date_range' => true,
			'author'     => true,
			'connector'  => true,
			'context'    => true,
			'action'     => true,
		);
		$filters_option = get_user_meta( $user_id, 'stream_toggle_filters', true );
		if ( empty( $filters_option ) ) {
			update_user_meta( $user_id, 'stream_toggle_filters', $filters_option_defaults );
			$filters_option = get_user_meta( $user_id, 'stream_toggle_filters', true );
		}
		$stream_live_update_records_nonce = wp_create_nonce( 'stream_live_update_records_nonce' );
		$stream_toggle_filters_nonce      = wp_create_nonce( 'stream_toggle_filters_nonce' );
		ob_start();
		?>
		<fieldset>
			<h5><?php esc_html_e( 'Live updates', 'stream' ) ?></h5>

			<div>
				<input type="hidden" name="stream_live_update_nonce" id="stream_live_update_nonce" value="<?php echo esc_attr( $stream_live_update_records_nonce ) ?>" />
			</div>
			<div>
				<input type="hidden" name="enable_live_update_user" id="enable_live_update_user" value="<?php echo absint( $user_id ) ?>" />
			</div>
			<div class="metabox-prefs stream-live-update-checkbox">
				<label for="enable_live_update">
					<input type="checkbox" value="on" name="enable_live_update" id="enable_live_update" <?php checked( $option ) ?> />
					<?php esc_html_e( 'Enabled', 'stream' ) ?><span class="spinner"></span>
				</label>
			</div>
		</fieldset>
		<fieldset>
			<h5><?php esc_html_e( 'Show Filters', 'stream' ) ?></h5>

			<div>
				<input type="hidden" name="toggle_filters_nonce" id="toggle_filters_nonce" value="<?php echo esc_attr( $stream_toggle_filters_nonce ) ?>" />
			</div>
			<div>
				<input type="hidden" name="toggle_filters_user" id="toggle_filters_user" value="<?php echo absint( $user_id ) ?>" />
			</div>
			<div class="metabox-prefs stream-toggle-filters">
				<?php
				$filters = apply_filters(
					'stream_toggle_filters', array(
						'date_range' => __( 'Date Range', 'stream' ),
						'author'     => __( 'Authors', 'stream' ),
						'connector'  => __( 'Connectors', 'stream' ),
						'context'    => __( 'Contexts', 'stream' ),
						'action'     => __( 'Actions', 'stream' ),
					)
				);

		foreach ( $filters as $key => $val ) : ?>
					<label for="<?php echo esc_attr( $key ); ?>">
						<input type="hidden" name="stream_toggle_filters[<?php echo esc_attr( $key ); ?>]" value="0" />
						<input type="checkbox" value="1" name="stream_toggle_filters[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>" <?php checked( $filters_option[ $key ] ) ?> />
						<?php echo esc_html( $val ); ?><span class="spinner"></span>
					</label>
				<?php endforeach; ?>

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
				$output = 'authors_and_roles';
				break;
			default:
				$output = false;
		}

		return $output;
	}

}
