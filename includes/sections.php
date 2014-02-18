<?php
/**
 * Section class for Stream Reports
 *
 * @author X-Team <x-team.com>
 * @author Jonathan Bardo <jonathan.bardo@x-team.com>
 */

class WP_Stream_Reports_Sections {

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
	 * Public constructor
	 */
	public function __construct() {
		//Temp default
		$default = array(
			array( 'title' => 'Super Title', 'data' => array() ),
			array( 'title' => 'Super Title', 'data' => array() ),
		);

		// Get all sections from the db
		self::$sections = get_option( __CLASS__, $default );

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
			check_admin_referer( 'stream-reports-page', 'stream_report_nonce' );
		}
	}

	public function load_page() {
		// Enqueue all core scripts required for this page to work
		wp_enqueue_script( array( 'common', 'dashboard', 'postbox' ) );
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );

		// Add all metaboxes
		foreach ( self::$sections as $key => $section ) {
			add_meta_box(
				"wp-stream-reports-{$key}",
				$section['title'] . " {$key}",
				array( $this, 'metabox_content' ),
				WP_Stream_Reports::$screen_id,
				'normal',
				'default',
				$section
			);
		}
	}

	/**
	 * This is the content of the metabox
	 *
	 * @param $data
	 * @param $section
	 */
	public function metabox_content( $data, $section ) {
		echo '<div class="chart">This will be replace by the chart</div>';
	}

	/**
	 * This function will handle the ajax request to add a metabox to the page.
	 */
	public function add_metabox() {
		// Add a new section
		self::$sections[] = array( 'title' => 'Super Title', 'data' => array() );

		// Update the database option
		$this->update_option();
	}

	/**
	 * This function will remove the metabox from the current view.
	 */
	public function delete_metabox() {
		//@todo Save new metabox to the db here

		wp_send_json_success();
	}

	// Handle option updating in the database
	private function update_option(){
		$is_saved = update_option( __CLASS__, self::$sections );

		if ( $is_saved ) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Return active instance of WP_Stream_Reports, create one if it doesn't exist
	 *
	 * @return WP_Stream_Reports
	 */
	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}
		return self::$instance;
	}

}
