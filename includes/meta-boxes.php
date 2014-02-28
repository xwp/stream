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
		// We should input a default box here
		$default = array();

		// Get all sections from the db
		$user_option = WP_Stream_Reports_Settings::get_user_options();

		// Apply default if no user option is found
		self::$sections = isset( $user_option['sections'] ) ? $user_option['sections'] : $default;

		// If we are not in ajax mode, return early
		if ( ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$ajax_hooks = array(
			'stream_reports_add_metabox' => 'add_metabox',
			'stream_reports_delete_metabox' => 'delete_metabox',
		);

		foreach ( $ajax_hooks as $hook => $function ) {
			add_action( "wp_ajax_{$hook}", array( $this, $function ) );
		}

		// Check referer here so we don't have to check it on every function call
		if ( array_key_exists( $_REQUEST['action'], $ajax_hooks ) ) {
			// Checking permission
			if ( ! current_user_can( WP_Stream_Reports::VIEW_CAP ) ) {
				wp_die( __( 'Cheating huh?', 'stream-reports' ) );
			}
			check_admin_referer( 'stream-reports-page', 'stream_reports_nonce' );
		}
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
				'title'      => "Report {$title_key}" . $configure,
				'priority'   => 'default',
				'context'    => 'normal',
				'chart-type' => 'bar',
			);

			// Parse default argument
			$section = wp_parse_args( $section, $default );

			// Set the key for template use
			$section['key'] = $key;

			// Add the actual metabox
			add_meta_box(
				self::META_PREFIX . $key,
				$section['title'],
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

		// Apply the active class to the active chart type used
		if ( array_key_exists( $args['chart-type'], $chart_types ) ) {
			$chart_types[ $args['chart-type'] ] .= ' active';
		}

		include WP_STREAM_REPORTS_VIEW_DIR . 'meta-box.php';
	}

	/**
	 * Update configuration array from ajax call and save this to the user option
	 */
	public function save_metabox_config() {
		//@todo Parse the new config from $_POST and validate all field

		// Update the database option
		$this->update_option();
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
		$this->update_option( true );
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
				if ( ( $key = array_search( self::META_PREFIX . $meta_key, $order ) ) !== false ) {
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
		$this->update_option( true );
	}

	/**
	 * Handle option updating in the database
	 *
	 * @param bool $redirect If the function must redirect and exit here
	 */
	private function update_option( $redirect = false ) {
		$is_saved = WP_Stream_Reports_Settings::update_user_options( 'sections', self::$sections );

		// If we need to redirect back to stream report page
		if ( $is_saved && $redirect ) {
			wp_redirect(
				add_query_arg(
					array( 'page' => WP_Stream_Reports::REPORTS_PAGE_SLUG ),
					admin_url( 'admin.php' )
				)
			);
			exit;
		} else {
			wp_die( __( "Uh no! This wasn't suppose to happen :(", 'stream-reports' ) );
		}

		// If it was a standard ajax call requesting json back.
		if ( $is_saved ) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Return active instance of WP_Stream_Reports, create one if it doesn't exist
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
