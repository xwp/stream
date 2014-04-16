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

		add_filter( 'wp_stream_reports_load_records', array( $this, 'sort_coordinates_by_count' ), 10, 2 );
		add_filter( 'wp_stream_reports_load_records', array( $this, 'limit_coordinates' ), 10, 2 );

		add_filter( 'wp_stream_reports_make_chart', array( $this, 'pie_chart_coordinates' ), 10, 2 );
		add_filter( 'wp_stream_reports_make_chart', array( $this, 'bar_chart_coordinates' ), 10, 2 );
		add_filter( 'wp_stream_reports_make_chart', array( $this, 'line_chart_coordinates' ), 10, 2 );

		add_filter( 'wp_stream_reports_finalize_chart', array( $this, 'translate_labels' ), 10, 2 );
		add_filter( 'wp_stream_reports_finalize_chart', array( $this, 'apply_chart_settings' ), 10, 2 );

		if ( is_multisite() ) {
			add_filter( 'wp_stream_reports_data_types', array( $this, 'mutlisite_data_types' ), 10 );
			add_filter( 'wp_stream_reports_selector_types', array( $this, 'mutlisite_selector_types' ), 10 );
		}

		$ajax_hooks = array(
			'wp_stream_reports_add_metabox'           => 'add_metabox',
			'wp_stream_reports_delete_metabox'        => 'delete_metabox',
			'wp_stream_reports_save_metabox_config'    => 'save_metabox_config',
			'wp_stream_reports_save_chart_height'      => 'save_chart_height',
			'wp_stream_reports_save_chart_options'     => 'save_chart_options',
			'wp_stream_reports_update_metabox_display' => 'update_metabox_display',
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

		$order = array(
			'normal' => sprintf( '%1$s0,%1$s2', self::META_PREFIX ),
			'side'   => sprintf( '%1$s1,%1$s3', self::META_PREFIX ),
		);

		update_user_option( get_current_user_id(), 'meta-box-order_stream_page_' . WP_Stream_Reports::REPORTS_PAGE_SLUG, $order, true );

		$interval = array(
			'key'   => 'last-30-days',
			'start' => '',
			'end'   => '',
		);

		WP_Stream_Reports_Settings::update_user_option_and_redirect( 'interval', $interval );
	}

	public function load_page() {
		if ( is_admin() && WP_Stream_Reports_Settings::is_first_visit() ) {
			$this->setup_user();
		}

		// Add screen option for chart height
		add_filter( 'screen_settings', array( $this, 'chart_height_display' ), 10, 2 );

		// Enqueue all core scripts required for this page to work
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );

		// Add all metaboxes
		foreach ( self::$sections as $key => $section ) {
			$delete_url = add_query_arg(
				array_merge(
					array(
						'action' => 'wp_stream_reports_delete_metabox',
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

			// Parse default argument
			$section = $this->parse_section( $section );

			// Set the key for template use
			$section['key'] = $key;
			$section['generated_title'] = $this->get_generated_title( $section );

			// Generate the title automatically if not already set
			$title = empty( $section['title'] ) ? $section['generated_title'] : $section['title'];

			// Add the actual metabox
			add_meta_box(
				self::META_PREFIX . $key,
				sprintf( '<span class="title">%s</span>%s', esc_html( $title ), $configure ), // xss ok
				array( $this, 'metabox_content' ),
				WP_Stream_Reports::$screen_id,
				$section['context'],
				$section['priority'],
				$section
			);
		}
	}

	/**
	 * Parses the section arguments and provides defaults
	 */
	protected function parse_section( $section ) {
		$default = array(
			'title'         => '',
			'priority'      => 'default',
			'context'       => 'normal',
			'chart_type'    => 'line',
			'data_type'     => '',
			'data_group'    => '',
			'selector_type' => '',
			'is_new'        => false,
			'disabled'      => array(),
			'group'         => false,
		);

		return wp_parse_args( $section, $default );
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

		$chart_types = $this->get_chart_types();

		if ( array_key_exists( $args['chart_type'], $chart_types ) ) {
			$chart_types[ $args['chart_type'] ] .= ' active';
		} else {
			$args['chart_type'] = 'line';
		}

		$configure_class = '';
		if ( $args['is_new'] ) {
			$configure_class = 'stream-reports-expand';
			unset( self::$sections[ $key ]['is_new'] );
			WP_Stream_Reports_Settings::update_user_option( 'sections', self::$sections );
		}

		$chart_height   = WP_Stream_Reports_Settings::get_user_options( 'chart_height' , 300 );
		$data_types     = $this->get_data_types();
		$selector_types = $this->get_selector_types();

		include WP_STREAM_REPORTS_VIEW_DIR . 'meta-box.php';
	}

	protected function get_chart_options( $args ) {
		$values        = $this->get_chart_coordinates( $args );
		$show_controls = count( $values ) > 1;

		return array(
			'type'       => $args['chart_type'],
			'guidelines' => true,
			'tooltip'    => array(
				'show' => true,
			),
			'values'     => $values,
			'controls'   => $show_controls,
			'stacked'    => (bool) $args['group'],
			'grouped'    => false,
		);
	}

	public function get_chart_coordinates( $args ) {
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

		$records     = $this->load_metabox_records( $args, $user_interval );
		$records     = apply_filters( 'wp_stream_reports_load_records', $records, $args );
		$coordinates = apply_filters( 'wp_stream_reports_make_chart', $records, $args );

		return apply_filters( 'wp_stream_reports_finalize_chart', $coordinates, $args );
	}

	public function line_chart_coordinates( $records, $args ) {
		if ( 'line' !== $args['chart_type'] ) {
			return $records;
		}

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
				'key'    => $line_name,
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

	public function pie_chart_coordinates( $records, $args ) {
		if ( 'pie' !== $args['chart_type'] ) {
			return $records;
		}
		$counts = array();

		foreach ( $records as $type => $items ) {
			$counts[] = array(
				'key'   => $type,
				'value' => count( $items ),
			);
		}

		return $counts;
	}

	public function bar_chart_coordinates( $records, $args ) {
		if ( 'multibar' !== $args['chart_type'] ) {
			return $records;
		}

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
				'key'    => $line_name,
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

	public function apply_chart_settings( $coordinates, $args ) {
		foreach ( $coordinates as $key => $dataset ) {
			if ( in_array( $key, $args['disabled'] ) ) {
				$coordinates[ $key ]['disabled'] = true;
			}
		}

		return $coordinates;
	}

	public function translate_labels( $coordinates, $args ) {
		foreach ( $coordinates as $key => $dataset ) {
			$coordinates[ $key ]['key'] = $this->get_label( $dataset['key'], $args['selector_type'] );
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
				if ( $value ) {
					$user_info = get_userdata( $value );
					$output    = isset( $user_info->display_name ) ? $user_info->display_name : sprintf( __( 'User ID: %d', 'stream-reports' ), $value );
				} else {
					$output = __( 'N/A', 'stream-reports' );
				}
				break;
			case 'author_role':
				$output = ucfirst( $value );
				break;
			case 'connector':
				$output = isset( WP_Stream_Connectors::$term_labels['stream_connector'][ $value ] ) ? WP_Stream_Connectors::$term_labels['stream_connector'][ $value ] : $value;
				break;
			case 'context':
				$output = isset( WP_Stream_Connectors::$term_labels['stream_context'][ $value ] ) ? WP_Stream_Connectors::$term_labels['stream_context'][ $value ] : $value;
				break;
			case 'site':
				$details = get_blog_details( $value );
				$output  = $details->blogname;
				break;
			default:
				$output = $this->get_data_types( $value ) ? $this->get_data_types( $value ) : $value;
				break;
		}

		return $output;
	}

	/**
	 * Returns data type labels, or a single data type's label'
	 * @return string
	 */
	protected function get_data_types( $key = '' ) {
		$labels = array(
			'all' => __( 'All Activity', 'stream-reports' ),
			'connector' => array(
				'title'   => __( 'Connector Activity', 'stream-reports' ),
				'group'   => 'connector',
				'options' => WP_Stream_Connectors::$term_labels['stream_connector'],
				'disable' => array(
					'connector',
				),
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

		$labels = apply_filters( 'wp_stream_reports_data_types', $labels );

		if ( empty( $key ) ) {
			$output = $labels;
		} elseif ( array_key_exists( $key, $labels ) ) {
			$output = $labels[ $key ];
		} else {
			$output = false;
		}

		return $output;
	}

	public function mutlisite_data_types( $old_labels ) {
		$options = array();
		$sites   = wp_get_sites();
		foreach ( $sites as $site ) {
			$details = get_blog_details( $site['blog_id'] );
			$options[ $site['blog_id'] ] = $details->blogname;
		}

		// Position sites label right after 'all' dataset
		$labels         = array( 'all' => array() );
		$labels['site'] = array(
			'title'   => __( 'Site Activity', 'stream-reports' ),
			'group'   => 'site',
			'options' => $options,
			'disable' => array(),
		);

		return array_merge( $labels, $old_labels );
	}

	/**
	 * Returns chart types available
	 * @return string
	 */
	protected function get_chart_types() {
		return array(
			'line'     => 'dashicons-chart-area',
			'pie'      => 'dashicons-chart-pie',
			'multibar' => 'dashicons-chart-bar',
		);
	}

	/**
	 * Returns selector type labels, or a single selector type's label'
	 * @return string
	 */
	protected function get_selector_types( $key = '' ) {
		$labels = array(
			'action'      => __( 'Action', 'stream-reports' ),
			'author'      => __( 'Author', 'stream-reports' ),
			'author_role' => __( 'Author Role', 'stream-reports' ),
			'connector'   => __( 'Connector', 'stream-reports' ),
			'context'     => __( 'Context', 'stream-reports' ),
			'ip'          => __( 'IP Address', 'stream-reports' ),
		);

		$labels = apply_filters( 'wp_stream_reports_selector_types', $labels );

		if ( empty( $key ) ) {
			$output = $labels;
		} elseif ( array_key_exists( $key, $labels ) ) {
			$output = $labels[ $key ];
		} else {
			$output = false;
		}

		return $output;
	}

	public function mutlisite_selector_types( $labels ) {
		$new_labels = array(
			'site' => __( 'Site', 'stream-reports' ),
		);

		return array_merge( $labels, $new_labels );
	}


	public function load_metabox_records( $args, $date_interval ) {
		$query_args = array(
			'records_per_page' => -1,
			'date_from'        => $date_interval['start'],
			'date_to'          => $date_interval['end'],
		);

		switch ( $args['data_group'] ) {
			case 'connector':
				$query_args['connector'] = $args['data_type'];
				break;
			case 'context':
				$query_args['context'] = $args['data_type'];
				break;
			case 'action':
				$query_args['action'] = $args['data_type'];
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

		$unsorted = wp_stream_query( $query_args );
		if ( 'author_role' === $grouping_field ) {
			foreach ( $unsorted as $key => $record ) {
				$user = get_userdata( $record->author );
				if ( $user ) {
					$record->author_role = join( ',', $user->roles );
				} else if ( 0 === $record->author ) {
					$record->author_role = __( 'N/A', 'stream-reports' );
				} else {
					$record->author_role = __( 'Unknown', 'stream-reports' );
				}
			}
		}
		$sorted = $this->group_by_field( $grouping_field, $unsorted );

		return $sorted;
	}

	/**
	 * Sorts each set of data by the number of records in them
	 */
	public function sort_coordinates_by_count( $records ) {
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
	public function limit_coordinates( $records, $args ) {
		$limit = apply_filters( 'wp_stream_reports_record_limit', 10 );
		if ( 0 === $limit ) {
			return $records;
		}

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
				$key = call_user_func( $callback, $key );
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
	 * Creates a title generated from the arguments for the chart
	 */
	protected function get_generated_title( $args ) {
		if ( empty( $args['data_type'] ) ) {
			return sprintf( esc_html__( 'Report %d', 'stream-reports' ), absint( $args['key'] + 1 ) );
		}

		$type_label     = $this->get_label( $args['data_type'], $args['data_group'] );
		$selector_label = $this->get_selector_types( $args['selector_type'] );

		// Don't add 'Activity' to special cases that already have it
		$exceptions = array( 'all' );
		if ( in_array( $args['data_type'], $exceptions ) ) {
			$string = _x(
				'%1$s by %2$s',
				'Special case for activities that do not add activity suffix. 1: Dataset 2: Selector',
				'stream-reports'
			);
		} else {
			$string = _x(
				'%1$s Activity by %2$s',
				'1: Dataset 2: Selector',
				'stream-reports'
			);
		}

		return sprintf( $string, $type_label, $selector_label );
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
		$section_id = wp_stream_filter_input( INPUT_GET, 'section_id', FILTER_SANITIZE_NUMBER_INT );
		$sections   = WP_Stream_Reports_Settings::get_user_options( 'sections' );
		$section    = $sections[ $section_id ];

		$args = $this->parse_section( $section );

		$chart_types = $this->get_chart_types();

		if ( ! array_key_exists( $args['chart_type'], $chart_types ) ) {
			$args['chart_type'] = 'line';
		}

		$chart_options = $this->get_chart_options( $args );

		wp_send_json_success(
			array(
				'options'         => $chart_options,
				'title'           => $section['title'],
				'generated_title' => $this->get_generated_title( $args ),
			)
		);
	}

	/**
	 * This function will handle the ajax request to add a metabox to the page.
	 */
	public function add_metabox() {
		// Add a new section
		self::$sections[] = array(
			'is_new' => true,
		);

		// Push new metabox to top of the display
		$new_section_id = 'wp-stream-reports-' . ( count( self::$sections ) - 1 );
		$order          = get_user_option( 'meta-box-order_stream_page_' . WP_Stream_Reports::REPORTS_PAGE_SLUG );
		$normal_order   = explode( ',', $order['normal'] );

		array_unshift( $normal_order, $new_section_id );
		$order['normal'] = join( ',', $normal_order );
		update_user_option( get_current_user_id(), 'meta-box-order_stream_page_' . WP_Stream_Reports::REPORTS_PAGE_SLUG, $order, true );

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

	public function save_chart_options(){
		$section_id = wp_stream_filter_input( INPUT_GET, 'section_id', FILTER_SANITIZE_NUMBER_INT );
		$sections   = WP_Stream_Reports_Settings::get_user_options( 'sections' );
		$section    = $sections[ $section_id ];
		$type       = wp_stream_filter_input( INPUT_GET, 'update_type', FILTER_SANITIZE_STRING );

		if ( 'disable' === $type ) {
			if ( ! isset( $_GET['update_payload'] ) || ! is_array( $_GET['update_payload'] ) ) {
				wp_send_json_error();
			}

			$payload = array();
			foreach ( $_GET['update_payload'] as $key => $value ) {
				if ( 'true' === $value ) {
					$payload[] = absint( $key );
				}
			}

			$section['disabled'] = $payload;
		} else if ( 'group' === $type ) {
			$payload = wp_stream_filter_input( INPUT_GET, 'update_payload', FILTER_SANITIZE_STRING );
			$section['group'] = 'true' === $payload;
		}

		// Store the chart configuration
		self::$sections[ $section_id ] = $section;

		// Update the database option
		WP_Stream_Reports_Settings::ajax_update_user_option( 'sections', self::$sections );
	}

	public function save_chart_height(){
		$chart_height = wp_stream_filter_input( INPUT_GET, 'chart_height', FILTER_SANITIZE_NUMBER_INT );

		if ( false === $chart_height ) {
			wp_send_json_error();
		}

		// Update the database option
		WP_Stream_Reports_Settings::ajax_update_user_option( 'chart_height', $chart_height );
	}

	public function chart_height_display( $status, $args ) {
		$user_id = get_current_user_id();
		$option  = WP_Stream_Reports_Settings::get_user_options( 'chart_height', 300 );
		$nonce   = wp_create_nonce( 'wp_stream_reports_chart_height_nonce' );
		ob_start();
		?>
		<fieldset>
			<h5><?php esc_html_e( 'Chart height', 'stream-repotrs' ); ?></h5>
			<div><input type="hidden" name="update_chart_height_nonce" id="update_chart_height_nonce" value="<?php echo esc_attr( $nonce ); ?>"></div>
			<div><input type="hidden" name="update_chart_height_user" id="update_chart_height_user" value="<?php echo esc_attr( $user_id ); ?>"></div>
			<div class="metabox-prefs stream-reports-chart-height-option">
				<label for="chart-height">
					<input type="number" step="50" min="100" max="950" name="chart_height" id="chart_height" maxlength="3" value="<?php echo esc_attr( $option ); ?>">
					<?php esc_html_e( 'px', 'stream-reports' ); ?>
				</label>
				<input type="submit" id="chart_height_apply" class="button" value="<?php esc_attr_e( 'Apply', 'stream-reports' ); ?>">
				<span class="spinner"></span>
			</div>
		</fieldset>
		<?php
		return ob_get_clean();
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
