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
		foreach ( self::$sections as $key => $section ) :
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
			$title_key = $key + 1;
			$default   = array(
				'title'      => 'Report ' . $title_key,
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
				sprintf( '<span class="title">%s</span>%s', $section['title'], $configure ),
				array( $this, 'metabox_content' ),
				WP_Stream_Reports::$screen_id,
				$section['context'],
				$section['priority'],
				$section
			);
		endforeach;
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
			'bar'  => 'dashicons-chart-bar',
			'pie'  => 'dashicons-chart-pie',
			'line' => 'dashicons-chart-area',
		);

		$data_type  = isset( $args['data_type'] ) ? $args['data_type'] : null;
		$data_types = WP_Stream_Connectors::$term_labels['stream_connector'];

		// Apply the active class to the active chart type used
		if ( array_key_exists( $args['chart_type'], $chart_types ) ) {
			$chart_types[ $args['chart_type'] ] .= ' active';
		}

		include WP_STREAM_REPORTS_VIEW_DIR . 'meta-box.php';
	}

	/**
	 * Update configuration array from ajax call and save this to the user option
	 */
	public function save_metabox_config() {
		$id = wp_stream_filter_input( INPUT_GET, 'section_id', FILTER_SANITIZE_NUMBER_INT );

		$input = array(
			'id'          => wp_stream_filter_input( INPUT_GET, 'section_id', FILTER_SANITIZE_NUMBER_INT ),
			'title'       => wp_stream_filter_input( INPUT_GET, 'title', FILTER_SANITIZE_STRING ),
			'chart_type'  => wp_stream_filter_input( INPUT_GET, 'chart_type', FILTER_SANITIZE_STRING ),
			'data_type'   => wp_stream_filter_input( INPUT_GET, 'data_type', FILTER_SANITIZE_STRING ),
		);

		if (
			in_array( null, array_values( $input ) )
			&& null !== $id
			&& ! isset( self::$sections[ $id ] )
		) {
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
		$meta_key = false;
		if ( isset( $_GET[ 'key' ] ) && is_numeric( $_GET['key'] ) ) {
			$meta_key = (int) $_GET['key'];
		}

		// Unset the metabox from the array.
		unset( self::$sections[$meta_key] );

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
