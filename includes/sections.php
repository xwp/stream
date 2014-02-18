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
	 * Public constructor
	 */
	public function __construct() {
		wp_enqueue_script( 'dashboard' );
		wp_enqueue_script( 'postbox' );
		add_screen_option( 'layout_columns', array( 'max' => 2, 'default' => 2 ) );

		//Temporary
		$sections = array(
			'id' => array( 'title' => 'Super Title', 'data' => array() ),
			'id2' => array( 'title' => 'Super Title 2', 'data' => array() ),
		);

		foreach ( $sections as $id => $section ) {
			add_meta_box(
				"wp-stream-reports-{$id}",
				$section['title'],
				array( $this, 'fill' ),
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
	public function fill( $data, $section ) {
		echo '<div class="chart">This will be replace by the chart</div>';
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
