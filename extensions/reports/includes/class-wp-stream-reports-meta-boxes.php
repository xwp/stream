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
		// Get all sections from the database
		self::$sections = WP_Stream_Reports_Settings::get_user_options( 'sections' );
		$this->charts   = new WP_Stream_Reports_Charts();

		if ( isset( self::$sections[0] ) && isset( self::$sections[0]['data_type'] ) ) {
			$this->migrate_settings();
		}

		// Finalize charts
		add_filter( 'wp_stream_reports_finalize_chart', array( $this, 'translate_labels' ), 10, 2 );

		// Get chart labels
		add_filter( 'wp_stream_reports_get_label', array( $this, 'translate_data_type_labels' ), 10, 2 );

		$ajax_hooks = array(
			'wp_stream_reports_add_metabox'            => 'add_metabox',
			'wp_stream_reports_delete_metabox'         => 'delete_metabox',
			'wp_stream_reports_default_reports'        => 'setup_user',
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
	public function setup_user() {
		$sections = array(
			array(
				'id'            => 0,
				'title'         => __( 'All Activity by Author', 'stream' ),
				'chart_type'    => 'line',
				'selector_id'   => 'author',
			),
			array(
				'id'            => 1,
				'title'         => __( 'All Activity by Action', 'stream' ),
				'chart_type'    => 'line',
				'selector_id'   => 'action',
			),
			array(
				'id'            => 2,
				'title'         => __( 'All Activity by Author Role', 'stream' ),
				'chart_type'    => 'multibar',
				'selector_id'   => 'author_role',
				'group'         => true,
			),
			array(
				'id'            => 3,
				'title'         => __( 'Comments Activity by Action', 'stream' ),
				'chart_type'    => 'pie',
				'connector_id'  => 'comments',
				'context_id'    => 'comments',
				'selector_id'   => 'action',
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

	public static function in_admin_header() {

		?>
		<div class="stream-example">
			<div class="stream-example-modal">
				<h1><i class="dashicons dashicons-chart-area"></i> <?php _e( 'Stream Reports', 'stream' ) ?></h1>
				<p><?php _e( 'Generate stunning visuals of logged-in user activity and share them with stakeholders or your clients.', 'stream' ) ?></p>
				<ul>
					<li><i class="dashicons dashicons-yes"></i> <?php _e( 'Fully-interactive charts', 'stream' ) ?></li>
					<li><i class="dashicons dashicons-yes"></i> <?php _e( 'Monitor team contributions', 'stream' ) ?></li>
					<li><i class="dashicons dashicons-yes"></i> <?php _e( 'Responsive for any screen size', 'stream' ) ?></li>
				</ul>
				<a href="<?php echo esc_url( WP_Stream_Admin::account_url( sprintf( 'upgrade?site_uuid=%s', WP_Stream::$api->site_uuid ) ) ); ?>" class="button button-primary button-large"><?php _e( 'Upgrade to Pro', 'stream' ) ?></a>
			</div>
		</div>
		<?php
	}

	public function load_page() {
		if ( WP_Stream_API::is_restricted() ) {
			add_action( 'in_admin_header', array( __CLASS__, 'in_admin_header' ) );
			return;
		}

		if ( is_admin() && WP_Stream_Reports_Settings::is_first_visit() ) {
			$this->setup_user();
		}

		$this->existing_records = $this->get_existing_records();

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
				esc_html__( 'Delete', 'stream' ),
				esc_html__( 'Configure', 'stream' )
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
			'connector_id'  => '',
			'context_id'    => '',
			'action_id'     => '',
			'selector_id'   => '',
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
		$data_types     = $this->get_contexts();
		$action_types   = $this->get_actions();
		$selector_types = $this->get_selector_types();

		include WP_STREAM_REPORTS_VIEW_DIR . 'meta-box.php';
	}

	public function translate_labels( $coordinates, $args ) {
		foreach ( $coordinates as $key => $dataset ) {
			$coordinates[ $key ]['key'] = $this->get_label( $dataset['key'], $args['selector_id'] );
		}

		return $coordinates;
	}

	protected function get_label( $value, $grouping ) {
		if ( 'report-others' === $value ) {
			return __( 'All Others', 'stream' );
		}

		switch ( $grouping ) {
			case 'action':
				$output = isset( WP_Stream_Connectors::$term_labels['stream_action'][ $value ] ) ? WP_Stream_Connectors::$term_labels['stream_action'][ $value ] : $value;
				break;
			case 'author':
				if ( $value ) {
					$user_info = get_userdata( $value );
					$output    = isset( $user_info->display_name ) ? $user_info->display_name : sprintf( __( 'User ID: %d', 'stream' ), $value );
				} else {
					$output = __( 'N/A', 'stream' );
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
			default:
				// Allow plugins to translate the label
				$output = apply_filters( 'wp_stream_reports_get_label', $value, $grouping );
				break;
		}
		return $output;
	}

	public function translate_data_type_labels( $value, $grouping ) {
		return $this->get_data_types( $value ) ? $this->get_data_types( $value ) : $value;
	}

	/**
	 * Returns data type labels, or a single data type's label'
	 * @return string
	 */
	protected function get_data_types( $key = '' ) {
		$labels = array(
			'all' => __( 'All Activity', 'stream' ),
			'connector' => array(
				'title'   => __( 'Connector Activity', 'stream' ),
				'group'   => 'connector',
				'options' => WP_Stream_Connectors::$term_labels['stream_connector'],
				'disable' => array(
					'connector',
				),
			),
			'context' => array(
				'title'   => __( 'Context Activity', 'stream' ),
				'group'   => 'context',
				'options' => WP_Stream_Connectors::$term_labels['stream_context'],
				'disable' => array(
					'context'
				),
			),
			'action' => array(
				'title'   => __( 'Actions Activity', 'stream' ),
				'group'   => 'action',
				'options' => WP_Stream_Connectors::$term_labels['stream_action'],
				'disable' => array(
					'action'
				),
			),
		);

		$labels = apply_filters( 'wp_stream_reports_data_types', $labels );

		if ( '' === $key ) {
			$output = $labels;
		} elseif ( array_key_exists( $key, $labels ) ) {
			$output = $labels[ $key ];
		} else {
			$output = false;
		}

		return $output;
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
			'action'      => __( 'Action', 'stream' ),
			'author'      => __( 'Author', 'stream' ),
			'author_role' => __( 'Author Role', 'stream' ),
			'connector'   => __( 'Connector', 'stream' ),
			'context'     => __( 'Context', 'stream' ),
			'ip'          => __( 'IP Address', 'stream' ),
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

	public function load_metabox_records( $args ) {
		$date_interval = $this->get_date_interval();

		$query_args    = array(
			'records_per_page' => -1,
			'date_from'        => $date_interval['start'],
			'date_to'          => $date_interval['end'],
		);

		$available_args = array(
			'action'    => 'action_id',
			'connector' => 'connector_id',
			'context'   => 'context_id',
		);
		foreach ( $available_args as $query_key => $args_key ) {
			if ( isset( $args[ $args_key ] ) ) {
				$query_args[ $query_key ] = $args[ $args_key ];
			}
		}

		$selector            = $args['selector_id'];
		$available_selectors = array( 'author', 'author_role', 'action', 'context', 'connector', 'ip' );

		if ( ! in_array( $selector, $available_selectors ) ) {
			return array();
		}

		$query_args = apply_filters( 'wp_stream_reports_query_args', $query_args, $args );
		$records    = wp_stream_query( $query_args );

		if ( 'author_role' === $selector ) {
			foreach ( $records as $key => $record ) {
				$user = get_userdata( $record->author );
				if ( $user ) {
					$record->author_role = join( ',', $user->roles );
				} else if ( 0 === $record->author ) {
					$record->author_role = __( 'N/A', 'stream' );
				} else {
					$record->author_role = __( 'Unknown', 'stream' );
				}
			}
		}

		$records = $this->charts->group_by_field( $selector, $records );
		$records = $this->charts->offset_record_dates( $records );

		return apply_filters( 'wp_stream_reports_load_records', $records, $args );
	}

	protected function get_date_interval(){
		$date             = new WP_Stream_Date_Interval();
		$default_interval = array(
			'key'   => 'all-time',
			'start' => '',
			'end'   => '',
		);

		$user_interval       = WP_Stream_Reports_Settings::get_user_options( 'interval', $default_interval );
		$user_interval_key   = $user_interval['key'];
		$available_intervals = $date->get_predefined_intervals();

		if ( array_key_exists( $user_interval_key, $available_intervals ) ) {
			$interval = $available_intervals[ $user_interval_key ];
			$user_interval['start'] = isset( $interval['start'] ) ? $interval['start']->toDateString() : null;
			$user_interval['end']   = isset( $interval['end'] ) ? $interval['end']->toDateString() : null;
		}

		return $user_interval;
	}

	/**
	 * Creates a title generated from the arguments for the chart
	 */
	protected function get_generated_title( $args ) {
		if ( empty( $args['selector_id'] ) ) {
			return sprintf( esc_html__( 'Report %d', 'stream' ), absint( $args['key'] + 1 ) );
		}

		if ( ! empty( $args['context_id'] ) ) {
			$dataset = $this->get_label( $args['context_id'], 'context' );
		} else if ( ! empty( $args['connector_id'] ) ) {
			$dataset = $this->get_label( $args['connector_id'], 'connector' );
		} else {
			$dataset = '';
		}

		$action   = ( ! empty( $args['action_id'] ) ) ? $this->get_label( $args['action_id'], 'action' ) : null;
		$selector = ( ! empty( $args['selector_id'] ) ) ? $this->get_selector_types( $args['selector_id'] ) : null;

		if ( ! empty( $action ) ) {
			if ( ! empty( $dataset ) ) {
				$string = _x(
					'%1$s in %2$s by %3$s',
					'1: Action 2: Dataset 3: Selector',
					'stream'
				);
			} else {
				$string = _x(
					'All %1$s by %3$s',
					'1: Action 3: Selector',
					'stream'
				);
			}
		} else if ( ! empty( $dataset ) ) {
			$string = _x(
				'All Activity in %2$s by %3$s',
				'2: Dataset 3: Selector',
				'stream'
			);
		} else {
			$string = _x(
				'All Activity by %3$s',
				'3: Selector',
				'stream'
			);
		}

		$title = sprintf( $string, $action, $dataset, $selector );
		return $title;
	}

	/**
	 * Update configuration array from ajax call and save this to the user option
	 */
	public function save_metabox_config() {
		$id = wp_stream_filter_input( INPUT_GET, 'section_id', FILTER_SANITIZE_NUMBER_INT );

		$input = array(
			'id'           => wp_stream_filter_input( INPUT_GET, 'section_id', FILTER_SANITIZE_NUMBER_INT ),
			'title'        => wp_stream_filter_input( INPUT_GET, 'title', FILTER_SANITIZE_STRING ),
			'chart_type'   => wp_stream_filter_input( INPUT_GET, 'chart_type', FILTER_SANITIZE_STRING ),
			'connector_id' => wp_stream_filter_input( INPUT_GET, 'data_connector', FILTER_SANITIZE_STRING ),
			'context_id'   => wp_stream_filter_input( INPUT_GET, 'data_context', FILTER_SANITIZE_STRING ),
			'action_id'    => wp_stream_filter_input( INPUT_GET, 'data_action', FILTER_SANITIZE_STRING ),
			'selector_id'  => wp_stream_filter_input( INPUT_GET, 'data_selector', FILTER_SANITIZE_STRING ),
		);

		$required_fields = array( 'id', 'title', 'chart_type', 'selector_id' );
		foreach ( $required_fields as $key ){
			if ( $input[ $key ] === null ) {
				wp_send_json_error( array( 'missing' => $key, 'value' => $input[ $key ] ) );
			}
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
		$section    = $this->get_section( $section_id );

		$args = $this->parse_section( $section );

		$chart_types = $this->get_chart_types();

		if ( ! array_key_exists( $args['chart_type'], $chart_types ) ) {
			$args['chart_type'] = 'line';
		}

		$records       = $this->load_metabox_records( $args );
		$chart_options = $this->charts->get_chart_options( $args, $records );

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
		$section    = $this->get_section( $section_id );
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
		} elseif ( 'group' === $type ) {
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
			<h5><?php esc_html_e( 'Chart height', 'stream' ); ?></h5>
			<div><input type="hidden" name="update_chart_height_nonce" id="update_chart_height_nonce" value="<?php echo esc_attr( $nonce ); ?>"></div>
			<div><input type="hidden" name="update_chart_height_user" id="update_chart_height_user" value="<?php echo esc_attr( $user_id ); ?>"></div>
			<div class="metabox-prefs stream-reports-chart-height-option">
				<label for="chart-height">
					<input type="number" step="50" min="100" max="950" name="chart_height" id="chart_height" maxlength="3" value="<?php echo esc_attr( $option ); ?>">
					<?php esc_html_e( 'px', 'stream' ); ?>
				</label>
				<input type="submit" id="chart_height_apply" class="button" value="<?php esc_attr_e( 'Apply', 'stream' ); ?>">
				<span class="spinner"></span>
			</div>
		</fieldset>
		<?php
		return ob_get_clean();
	}

	protected function get_section( $id ) {
		if ( empty( self::$sections ) ) {
			self::$sections = WP_Stream_Reports_Settings::get_user_options( 'sections' );
		}

		$section = self::$sections[ $id ];
		$section = $this->parse_section( $section );

		$section['key'] = $id;

		return $section;
	}

	public function get_contexts() {

		// Add Connectors as parents, and apply the Contexts as children
		$contexts   = $this->assemble_records( 'context' );
		$connectors = $this->assemble_records( 'connector' );
		foreach ( $connectors as $connector => $item ) {
			$context_items[ $connector ]['label'] = $item['label'];
			$context_items[ $connector ]['connector'] = $connector;
			$context_items[ $connector ]['disabled'] = $item['disabled'];
			foreach ( $contexts as $context_value => $context_item ) {
				if ( isset( WP_Stream_Connectors::$contexts[ $connector ] ) && array_key_exists( $context_value, WP_Stream_Connectors::$contexts[ $connector ] ) ) {
					$context_items[ $connector ]['children'][ $context_value ] = $context_item;
					$context_items[ $connector ]['children'][ $context_value ]['connector'] = $connector;
					$context_items[ $connector ]['children'][ $context_value ]['context'] = $context_value;
				}
			}
		}

		foreach ( $context_items as $context_value => $context_item ) {
			if ( ! isset( $context_item['children'] ) || empty( $context_item['children'] ) ) {
				unset( $context_items[ $context_value ] );
			}
		}

		$all_items = array(
			'label' => __( 'All Contexts', 'stream' )
		);

		$new_array = apply_filters( 'wp_stream_reports_get_contexts', $context_items );
		return array_merge( array( $all_items ), $new_array );
	}

	public function get_actions() {
		$actions = $this->assemble_records( 'action' );
		foreach ( $actions as $id => $item ) {
			$actions[ $id ]['action'] = $id;
		}

		$all_actions = array(
			'label' => __( 'All Actions', 'stream' )
		);

		$new_array = array_merge( array( $all_actions ), $actions );
		return apply_filters( 'wp_stream_reports_get_actions', $new_array );
	}

	/**
	 * Assembles records for display
	 *
	 * Gathers list of all authors/connectors, then compares it to
	 * results of existing records.  All items that do not exist in records
	 * get assigned a disabled value of "true".
	 *
	 * @param  string  Column requested
	 *
	 * @return array   options to be displayed in search filters
	 */
	function assemble_records( $column ) {

		$available_columns = array( 'context', 'connector', 'action' );
		if ( ! in_array( $column, $available_columns ) ) {
			return;
		}

		$prefixed_column = sprintf( 'stream_%s', $column );
		$all_records     = WP_Stream_Connectors::$term_labels[ $prefixed_column ];

		$active_records   = array();
		$disabled_records = array();

		foreach ( $all_records as $record => $label ) {
			if ( isset( $this->existing_records[ $column ] ) && in_array( $record, $this->existing_records[ $column ] ) ) {
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
			return strtolower( $label_a ) < strtolower( $label_b ) ? -1 : 1;
		};
		uasort( $active_records, $sort );
		uasort( $disabled_records, $sort );

		// Not using array_merge() in order to preserve the array index for the Authors dropdown which uses the user_id as the key
		$all_records = $active_records + $disabled_records;

		return $all_records;
	}

	/**
	 * Gets existing records for filtering dropdown menus
	 *
	 * @return array
	 */
	function get_existing_records() {
		$existing_records = array();

		$args = array(
			'aggregations' => array(
				'connector',
				'context',
				'action',
			),
		);

		$query      = wp_stream_query( $args );
		$query_meta = WP_Stream::$db->get_query_meta();

		if ( isset( $query_meta->aggregations ) ) {
			foreach ( $query_meta->aggregations as $field => $aggregation ) {
				$existing_records[ $field ] = array();
				foreach ( $aggregation->buckets as $bucket ) {
					$existing_records[ $field ][] = $bucket->key;
				}
			}
		}

		return $existing_records;
	}

	public function migrate_settings() {
		$sections = self::$sections;
		foreach ( $sections as $key => $args ) {
			switch ( $args['data_group'] ) {
				case  'action' :
					$sections[ $key ]['action_id'] = $args['data_type'];
					break;
				case  'connector' :
					$sections[ $key ]['connector_id'] = $args['data_type'];
					break;
				case  'context' :
					$sections[ $key ]['connector_id'] = $this->find_connector_by_context( $args['data_type'] );
					$sections[ $key ]['context_id'] = $args['data_type'];
					break;
				case 'other' :
					break;
			}

			if ( isset( $args['selector_type'] ) ) {
				$sections[ $key ]['selector_id'] = $args['selector_type'];
			}

			unset( $sections[ $key ]['data_group'] );
			unset( $sections[ $key ]['data_type'] );
			unset( $sections[ $key ]['selector_type'] );
		}

		WP_Stream_Reports_Settings::update_user_option_and_redirect( 'sections', $sections );
	}

	public function find_connector_by_context( $context ) {
		$output     = 'unknown';
		$connectors = $this->assemble_records( 'connector' );
		foreach ( $connectors as $connector => $item ) {
			if ( isset( WP_Stream_Connectors::$contexts[ $connector ] ) && array_key_exists( $context, WP_Stream_Connectors::$contexts[ $connector ] ) ) {
				$output = $connector;
				break;
			}
		}

		return $output;
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
