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

	public function load_page() {
		// Enqueue all core scripts required for this page to work
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );

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

		// Create an object of available charts
		$chart_types = array(
			'multibar' => 'dashicons-chart-bar',
			'pie'      => 'dashicons-chart-pie',
			'line'     => 'dashicons-chart-area',
		);

		$chart_type = isset( $args['chart_type'] ) ? $args['chart_type'] : 'line';

		if ( array_key_exists( $chart_type, $chart_types ) ) {
			$chart_types[ $args['chart_type'] ] .= ' active';
		}

		// Get records sorted grouped by original sort
		$date = new WP_Stream_Date_Interval();

		$default_interval = array(
			'key'   => 'all-time',
			'start' => '',
			'end'   => '',
		);

		$user_interval = WP_Stream_Reports_Settings::get_user_options( 'interval', $default_interval );
		$records       = $this->load_metabox_records( $args, $user_interval );

		switch ( $chart_type ) {
			case 'pie':
				$coordinates = $this->get_pie_chart_coordinates( $records );
				break;
			case 'multibar' :
				$coordinates = $this->get_bar_chart_coordinates( $records );
				break;
			default:
				$coordinates = $this->get_line_chart_coordinates( $records );
		}

		$data_type  = isset( $args['data_type'] ) ? $args['data_type'] : null;
		$data_group = isset( $args['data_group'] ) ? $args['data_group'] : null;

		$data_types = array(
			'all' => __( 'All Activity', 'stream-report' ),
			array(
				'title'   => __( 'Connector Activity', 'stream-report' ),
				'group'   => 'connector',
				'options' => WP_Stream_Connectors::$term_labels['stream_connector'],
			),
			array(
				'title'   => __( 'Context Activity', 'stream-report' ),
				'group'   => 'context',
				'options' => WP_Stream_Connectors::$term_labels['stream_context'],
			),
			array(
				'title'   => __( 'Actions Activity', 'stream-report' ),
				'group'   => 'action',
				'options' => WP_Stream_Connectors::$term_labels['stream_action'],
			),
		);

		$selector_type = isset( $args['selector_type'] ) ? $args['selector_type'] : '';

		$selector_types = array(
			'author'  => __( 'Author', 'stream-reports' ),
			'context' => __( 'Context', 'stream-reports' ),
			'action'  => __( 'Action', 'stream-reports' ),
		);

		include WP_STREAM_REPORTS_VIEW_DIR . 'meta-box.php';
	}

	public function get_line_chart_coordinates( $records ) {
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

	public function get_pie_chart_coordinates( $records ) {
		$counts = array();

		foreach ( $records as $type => $items ) {
			$counts[] = array(
				'key'   => $type,
				'value' => count( $items ),
			);
		};

		return $counts;
	}

	public function get_bar_chart_coordinates( $records ) {
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
		$available_fields = array( 'author', 'action', 'context', 'connector', 'ip' );

		if ( ! in_array( $grouping_field, $available_fields ) ) {
			return array();
		}

		$unsorted = stream_query( $query_args );
		$sorted   = $this->group_by_field( $grouping_field, $unsorted );

		return $sorted;
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
	 * Used to group data points by day
	 */
	protected function collapse_dates( $date ) {
		return strtotime( date( 'Y-m-d', strtotime( $date ) ) );
	}

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
		WP_Stream_Reports_Settings::update_user_option( 'sections', self::$sections );
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

		// Update the database option (pass true in param so the function redirect)
		WP_Stream_Reports_Settings::update_user_option( 'sections', self::$sections, true );
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

		// Update the database option (pass true in param so the function redirect)
		WP_Stream_Reports_Settings::update_user_option( 'sections', self::$sections, true );
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
