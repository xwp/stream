<?php
/**
 * Section class for Stream Reports
 *
 * @author X-Team <x-team.com>
 * @author Jonathan Bardo <jonathan.bardo@x-team.com>
 */
class WP_Stream_Reports_Metaboxes {

	/**
	 * Hold Stream Reports Section instance
	 *
	 * @var string
	 */
	public static $instance;

	/**
	 * Hold all the available sections on the page.
	 *
	 * @var array
	 */
	public static $sections;

	/**
	 * Holds the meta box id prefix
	 */
	const META_PREFIX = 'wp-stream-reports-';

	/**
	 * Public constructor
	 */
	public function __construct() {

		// Get all sections from the db
		self::$sections = WP_Stream_Reports_Settings::get_user_options( 'sections' );

		$ajax_hooks = array(
			'stream_reports_add_metabox'           => 'add_metabox',
			'stream_reports_delete_metabox'        => 'delete_metabox',
			'stream_report_save_metabox_config'    => 'save_metabox_config',
			'stream_report_update_metabox_display' => 'update_metabox_display',
		);

		// Register all ajax action and check referer for this class
		WP_Stream_Reports::handle_ajax_request( $ajax_hooks, $this );
	}

	/**
	 * Runs on a user's first visit to setup sample data
	 */
	protected function setup_user() {

		$sections = array(
			array(
				'id'            => 0,
				'title'         => __( 'All Activity by Author', 'stream-reports' ),
				'chart_type'    => 'line',
				'data_group'    => 'other',
				'data_type'     => 'all',
				'selector_type' => 'author',
			),
			array(
				'id'            => 1,
				'title'         => __( 'All Activity by Action', 'stream-reports' ),
				'chart_type'    => 'line',
				'data_group'    => 'other',
				'data_type'     => 'all',
				'selector_type' => 'action',
			),
			array(
				'id'            => 2,
				'title'         => __( 'All Activity by Author Role', 'stream-reports' ),
				'chart_type'    => 'multibar',
				'data_group'    => 'other',
				'data_type'     => 'all',
				'selector_type' => 'author_role',
			),
			array(
				'id'            => 3,
				'title'         => __( 'Comments Activity by Action', 'stream-reports' ),
				'chart_type'    => 'pie',
				'data_group'    => 'connector',
				'data_type'     => 'comments',
				'selector_type' => 'action',
			),
		);

		WP_Stream_Reports_Settings::update_user_option( 'sections', $sections );
		
		$interval = array(
			'key' => 'last-30-days',
			'start' => '',
			'end' => '',
		);

		WP_Stream_Reports_Settings::update_user_option_and_redirect( 'interval', $interval );

	}

	public function load_page() {
		
		if ( is_admin() && WP_Stream_Reports_Settings::is_first_visit() ) {
			$this->setup_user();
		}

		// Enqueue all core scripts required for this page to work
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 1 ) );

		// Add all metaboxes
		foreach ( self::$sections as $key => $section ) {
			$delete_url = add_query_arg(
				array_merge(
					array(
						'action' => 'stream_reports_delete_metabox',
						'key'    => $key,
					),
					WP_Stream_Reports::$nonce
				),
				admin_url( 'admin-ajax.php' )
			);

			//  Configure button
			$configure = sprintf(
				'<span class="postbox-title-action">
					<a href="javascript:void(0);" class="edit-box open-box">%3$s</a>
				</span>
				<span class="postbox-title-action postbox-delete-action">
					<a href="%1$s">
						%2$s
					</a>
				</span>',
				esc_url( $delete_url ),
				esc_html__( 'Delete', 'stream-reports' ),
				esc_html__( 'Configure', 'stream-reports' )
			);

			// Default metabox argument
			$default = array(
				'title'      => sprintf( esc_html__( 'Report %d', 'stream-reports' ), absint( $key + 1 ) ),
				'priority'   => 'default',
				'context'    => 'normal',
				'chart_type' => 'bar',
			);

			// Parse default argument
			$section = wp_parse_args( $section, $default );

			// Set the key for template use
			$section['key'] = $key;

			// Add the actual metabox
			add_meta_box(
				self::META_PREFIX . $key,
				sprintf( '<span class="title">%s</span>%s', esc_html( $section['title'] ), $configure ), // xss ok
				array( $this, 'metabox_content' ),
				WP_Stream_Reports::$screen_id,
				$section['context'],
				$section['priority'],
				$section
			);
		}
	}

	/**
	 * This is the content of the metabox
	 *
	 * @param $object
	 * @param $section
	 */
	public function metabox_content( $object, $section ) {
		$args = $section['args'];

		// Assigning template vars
		$key = $section['args']['key'];

		$chart_type    = isset( $args['chart_type'] ) ? $args['chart_type'] : 'line';
		$data_type     = isset( $args['data_type'] ) ? $args['data_type'] : null;
		$data_group    = isset( $args['data_group'] ) ? $args['data_group'] : null;
		$selector_type = isset( $args['selector_type'] ) ? $args['selector_type'] : '';

		$chart_types = array(
			'multibar' => 'dashicons-chart-bar',
			'pie'      => 'dashicons-chart-pie',
			'line'     => 'dashicons-chart-area',
		);

		if ( array_key_exists( $chart_type, $chart_types ) ) {
			$chart_types[ $args['chart_type'] ] .= ' active';
		} else {
			$chart_type = 'line';
		}

		// Get records sorted grouped by original sort
		$date = new WP_Stream_Date_Interval();
		$default_interval = array(
			'key'   => 'all-time',
			'start' => '',
			'end'   => '',
		);

		$user_interval       = WP_Stream_Reports_Settings::get_user_options( 'interval', $default_interval );
		$user_interval_key   = $user_interval['key'];
		$available_intervals = $date->get_predefined_intervals();

		if ( array_key_exists( $user_interval_key, $available_intervals ) ) {
			$user_interval['start'] = $available_intervals[ $user_interval_key ]['start'];
			$user_interval['end']   = $available_intervals[ $user_interval_key ]['end'];
		}

		$records = $this->load_metabox_records( $args, $user_interval );
		$records = $this->sort_by_count( $records );

		$limit   = apply_filters( 'stream_reports_record_limit', 10 );
		$records = $this->limit_records( $records, $limit );

		switch ( $chart_type ) {
			case 'pie':
				$coordinates = $this->get_pie_chart_coordinates( $records, $selector_type );
				break;
			case 'multibar' :
				$coordinates = $this->get_bar_chart_coordinates( $records, $selector_type );
				break;
			default:
				$coordinates = $this->get_line_chart_coordinates( $records, $selector_type );
		}

		$data_types = array(
			'all' => __( 'All Activity', 'stream-reports' ),
			'connector' => array(
				'title'   => __( 'Connector Activity', 'stream-reports' ),
				'group'   => 'connector',
				'options' => WP_Stream_Connectors::$term_labels['stream_connector'],
				'disable' => array(),
			),
			'context' => array(
				'title'   => __( 'Context Activity', 'stream-reports' ),
				'group'   => 'context',
				'options' => WP_Stream_Connectors::$term_labels['stream_context'],
				'disable' => array(
					'context'
				),
			),
			'action' => array(
				'title'   => __( 'Actions Activity', 'stream-reports' ),
				'group'   => 'action',
				'options' => WP_Stream_Connectors::$term_labels['stream_action'],
				'disable' => array(
					'action'
				),
			),
		);

		$selector_types = array(
			'action'      => __( 'Action', 'stream-reports' ),
			'author'      => __( 'Author', 'stream-reports' ),
			'author_role' => __( 'Author Role', 'stream-reports' ),
			'context'     => __( 'Context', 'stream-reports' ),
		);
		
		include WP_STREAM_REPORTS_VIEW_DIR . 'meta-box.php';
	}

	public function get_line_chart_coordinates( $records, $grouping ) {
		$sorted = array();

		// Get date count for each sort
		foreach ( $records as $type => $items ) {
			$sorted[ $type ] = $this->count_by_field( 'created', $items, array( $this, 'collapse_dates' ) );
		}

		$sorted = $this->pad_fields( $sorted );

		foreach ( $sorted as $type => &$items ) {
			ksort( $items );
		}

		$coordinates = array();

		foreach ( $sorted as $line_name => $points ) {
			$line_data = array(
				'key'    => $this->get_label( $line_name, $grouping ),
				'values' => array(),
			);

			foreach ( $points as $x => $y ) {
				$line_data['values'][] = array(
					'x' => $x,
					'y' => $y,
				);
			}

			$coordinates[] = $line_data;
		}

		return $coordinates;
	}

	public function get_pie_chart_coordinates( $records, $grouping ) {
		$counts = array();

		foreach ( $records as $type => $items ) {
			$counts[] = array(
				'key'   => $this->get_label( $type, $grouping ),
				'value' => count( $items ),
			);
		}

		return $counts;
	}

	public function get_bar_chart_coordinates( $records, $grouping ) {
		$sorted = array();

		// Get date count for each sort
		foreach ( $records as $type => $items ) {
			$sorted[ $type ] = $this->count_by_field( 'created', $items, array( $this, 'collapse_dates' ) );
		}

		$sorted = $this->pad_fields( $sorted );

		foreach ( $sorted as $type => &$items ) {
			ksort( $items );
		}

		$coordinates = array();

		foreach ( $sorted as $line_name => $points ) {
			$line_data = array(
				'key'    => $this->get_label( $line_name, $grouping ),
				'values' => array(),
			);

			foreach ( $points as $x => $y ) {
				$line_data['values'][] = array(
					'x' => $x,
					'y' => $y,
				);
			}

			$coordinates[] = $line_data;
		}

		return $coordinates;
	}

	protected function get_label( $value, $grouping ) {
		if ( 'report-others' === $value ) {
			return __( 'All Others', 'stream-reports' );
		}

		switch ( $grouping ) {
			case 'action':
				$output = isset( WP_Stream_Connectors::$term_labels['stream_action'][ $value ] ) ? WP_Stream_Connectors::$term_labels['stream_action'][ $value ] : $value;
				break;
			case 'author':
				$user_info = get_userdata( $value );
				$output    = isset( $user_info->display_name ) ? $user_info->display_name : __( 'N/A', 'stream-reports' );
				break;
			case 'author_role':
				$output = ucfirst( $value );
				break;
			case 'context':
				$output = isset( WP_Stream_Connectors::$term_labels['stream_context'][ $value ] ) ? WP_Stream_Connectors::$term_labels['stream_context'][ $value ] : $value;
				break;
			default:
				$output = $value;
		}

		return $output;
	}

	public function load_metabox_records( $args, $date_interval ) {
		$query_args = array(
			'records_per_page' => -1,
			'date_from'        => $date_interval['start'],
			'date_to'          => $date_interval['end'],
		);

		$data_type  = isset( $args['data_type'] ) ? $args['data_type'] : null;
		$data_group = isset( $args['data_group'] ) ? $args['data_group'] : null;

		switch ( $data_group ) {
			case 'connector':
				$query_args['connector'] = $data_type;
				break;
			case 'context':
				$query_args['context'] = $data_type;
				break;
			case 'action':
				$query_args['action'] = $data_type;
				break;
			case 'other':
				// all selector requires no query arg modifications
				break;
			default:
				return array();
		}

		$grouping_field   = $args['selector_type'];
		$available_fields = array( 'author', 'author_role', 'action', 'context', 'connector', 'ip' );

		if ( ! in_array( $grouping_field, $available_fields ) ) {
			return array();
		}

		$unsorted = stream_query( $query_args );
		if ( 'author_role' === $grouping_field ) {
			$users = array();
			foreach ( $unsorted as $key => $record ) {

				if ( ! array_key_exists( $record->author, $users ) ) {
					$users[ $record->author ] = get_userdata( $record->author );
				}

				$record->author_role = join( ',', $users[ $record->author ]->roles );

			}
		}
		$sorted = $this->group_by_field( $grouping_field, $unsorted );

		return $sorted;
	}

	/**
	 * Sorts each set of data by the number of records in them
	 */
	protected function sort_by_count( $records ) {

		$counts = array();
		foreach ( $records as $field => $data ){

			$count = count( $data );
			if ( ! array_key_exists( $count, $counts ) ) {
				$counts[ $count ] = array();
			}

			$counts[ $count ][] = array(
				'key' => $field,
				'data' => $data,
			);
		}

		krsort( $counts );

		$output = array();
		foreach ( $counts as $count => $element ) {

			foreach ( $element as $element_data ) {
				$output[ $element_data['key'] ] = $element_data['data'];
			}
		}

		return $output;
	}

	/**
	 * Merges all records past limit into single record
	 */
	protected function limit_records( $records, $limit ) {
		$top_elements      = array_slice( $records, 0, $limit, true );
		$leftover_elements = array_slice( $records, $limit );

		if ( ! $leftover_elements ) {
			return $top_elements;
		}

		$other_element = array();
		foreach ( $leftover_elements as $data ) {
			$other_element = array_merge( $other_element, $data );
		}

		$top_elements['report-others'] = $other_element;

		return $top_elements;
	}

	/**
	 * Groups objects with similar field properties into arrays
	 * @return array
	 */
	protected function group_by_field( $field, $records, $callback = '' ) {
		$sorted = array();

		foreach ( $records as $record ) {
			$key = $record->$field;

			if ( is_callable( $callback ) ) {
				$key = $callback( $key );
			}

			if ( array_key_exists( $key, $sorted ) && is_array( $sorted[ $key ] ) ) {
				$sorted[ $key ][] = $record;
			} else {
				$sorted[ $key ] = array( $record );
			}
		}

		return $sorted;
	}

	/**
	 * Counts the number of objects with similar field properties in an array
	 * @return array
	 */
	protected function count_by_field( $field, $records, $callback = '' ) {
		$sorted = $this->group_by_field( $field, $records, $callback );
		$counts = array();

		foreach ( array_keys( $sorted ) as $key ) {
			$counts[ $key ] = count( $sorted[ $key ] );
		}

		return $counts;
	}

	/**
	 * Adds blank fields for all keys present in any array
	 * @return array
	 */
	protected function pad_fields( $records ) {
		$keys = array();

		foreach ( $records as $dataset ) {
			$keys = array_unique( array_merge( $keys, array_keys( $dataset ) ) );
		}

		$new_records = array();

		foreach ( $keys as $key ) {
			foreach ( $records as $data_key => $dataset ) {
				if ( ! array_key_exists( $data_key, $new_records ) ) {
					$new_records[ $data_key ] = array();
				}

				$new_records[ $data_key ][ $key ] = isset( $records[ $data_key ][ $key ] ) ? $records[ $data_key ][ $key ] : 0;
			}
		}

		return $new_records;
	}

	/**
	 * Used to group data points by day
	 */
	protected function collapse_dates( $date ) {
		return strtotime( date( 'Y-m-d', strtotime( $date ) ) );
	}

	/**
	 * Update configuration array from ajax call and save this to the user option
	 */
	public function save_metabox_config() {
		$id = wp_stream_filter_input( INPUT_GET, 'section_id', FILTER_SANITIZE_NUMBER_INT );

		$input = array(
			'id'            => wp_stream_filter_input( INPUT_GET, 'section_id', FILTER_SANITIZE_NUMBER_INT ),
			'title'         => wp_stream_filter_input( INPUT_GET, 'title', FILTER_SANITIZE_STRING ),
			'chart_type'    => wp_stream_filter_input( INPUT_GET, 'chart_type', FILTER_SANITIZE_STRING ),
			'data_group'    => wp_stream_filter_input( INPUT_GET, 'data_group', FILTER_SANITIZE_STRING ),
			'data_type'     => wp_stream_filter_input( INPUT_GET, 'data_type', FILTER_SANITIZE_STRING ),
			'selector_type' => wp_stream_filter_input( INPUT_GET, 'selector_type', FILTER_SANITIZE_STRING ),
		);

		if ( in_array( false, array_values( $input ) ) && false !== $id && ! isset( self::$sections[ $id ] ) ) {
			wp_send_json_error();
		}

		// Store the chart configuration
		self::$sections[ $id ] = $input;

		// Update the database option
		WP_Stream_Reports_Settings::ajax_update_user_option( 'sections', self::$sections );
	}

	/**
	 * Instantly update chart based on user configuration
	 */
	public function update_metabox_display() {
		//@todo Generate new data for the chart and send json back for realtime update
	}

	/**
	 * This function will handle the ajax request to add a metabox to the page.
	 */
	public function add_metabox() {
		// Add a new section
		self::$sections[] = array();

		WP_Stream_Reports_Settings::update_user_option_and_redirect( 'sections', self::$sections );
	}

	/**
	 * This function will remove the metabox from the current view.
	 */
	public function delete_metabox() {
		$meta_key = wp_stream_filter_input( INPUT_GET, 'key', FILTER_SANITIZE_NUMBER_INT );

		// Unset the metabox from the array.
		unset( self::$sections[ $meta_key ] );

		// If there is no more section. We delete the user option.
		if ( empty( self::$sections ) ) {
			delete_user_option(
				get_current_user_id(),
				'meta-box-order_stream_page_' . WP_Stream_Reports::REPORTS_PAGE_SLUG,
				true
			);
		} else {
			// Delete the metabox from the page ordering as well
			// There might be a better way on handling this I'm sure (stream_page should not be hardcoded)
			$user_options = get_user_option( 'meta-box-order_stream_page_' . WP_Stream_Reports::REPORTS_PAGE_SLUG );
		}

		if ( ! empty( $user_options ) ) {
			// Remove the one we are deleting from the list
			foreach ( $user_options as $key => &$string ) {
				$order = explode( ',', $string );
				if ( false !== ( $key = array_search( self::META_PREFIX . $meta_key, $order ) ) ) {
					unset( $order[ $key ] );
					$string = implode( ',', $order );
				}
			}

			// Save the ordering again
			update_user_option(
				get_current_user_id(),
				'meta-box-order_stream_page_' . WP_Stream_Reports::REPORTS_PAGE_SLUG,
				$user_options,
				true
			);
		}

		WP_Stream_Reports_Settings::update_user_option_and_redirect( 'sections', self::$sections );
	}

	/**
	 * Return active instance of WP_Stream_Reports_Metaboxes, create one if it doesn't exist
	 *
	 * @return WP_Stream_Reports_Metaboxes
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}

		return self::$instance;
	}

}
