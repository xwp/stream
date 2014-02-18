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
			array( 'title' => 'Super Title 2', 'data' => array() ),
		);

		// Get all sections from the db
		self::$sections = get_option( __CLASS__, $default );

		// Register AJAX function here
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$ajax_hooks = array(
				'stream_reports_add_metabox' => 'add_metabox',
				'stream_reports_delete_metabox' => 'delete_metabox',
			);

			foreach ( $ajax_hooks as $hook => $function ) {
				add_action( "wp_ajax_{$hook}", array( $this, $function ) );
			}

			// Check referer here so we don't have to check it on every function call
			if ( in_array( $_REQUEST['action'], $ajax_hooks ) ) {
				check_admin_referer( 'stream-reports-page-nonce' );
			}

			// If we are doing a ajax function... no need to continue further
			return;
		}

		// Enqueue all core scripts required for this page to work
		wp_enqueue_script( array( 'common', 'dashboard', 'postbox' ) );
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );

		// Add all metaboxes
		foreach ( self::$sections as $key => $section ) {
			add_meta_box(
				"wp-stream-reports-{$key}",
				$section['title'],
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
		//@todo Save the metabox to the db here

		wp_send_json_success();
	}

	/**
	 * This function will remove the metabox from the current view.
	 */
	public function delete_metabox() {
		//@todo Save new metabox to the db here

		wp_send_json_success();
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
