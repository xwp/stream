<?php

class WP_Stream_Reports {

	/**
	 * Hold Stream Reports instance
	 *
	 * @var string
	 */
	public static $instance;

	/**
	 * Screen ID for my admin page
	 * @var string
	 */
	public static $screen_id;

	/**
	 * Holds admin notices messages
	 *
	 * @var array
	 */
	public static $messages = array();

	/**
	 * Hold the nonce name
	 */
	public static $nonce;

	/**
	 * Page slug for notifications list table screen
	 *
	 * @const string
	 */
	const REPORTS_PAGE_SLUG = 'wp_stream_reports';

	/**
	 * Capability for the Notifications to be viewed
	 *
	 * @const string
	 */
	const VIEW_CAP = 'view_stream_reports';

	/**
	 * Class constructor
	 */
	private function __construct() {
		define( 'WP_STREAM_REPORTS_DIR', WP_STREAM_EXTENSIONS_DIR . 'reports/' ); // Has trailing slash
		define( 'WP_STREAM_REPORTS_URL', WP_STREAM_URL . 'extensions/reports/' ); // Has trailing slash
		define( 'WP_STREAM_REPORTS_INC_DIR', WP_STREAM_REPORTS_DIR . 'includes/' ); // Has trailing slash
		define( 'WP_STREAM_REPORTS_VIEW_DIR', WP_STREAM_REPORTS_DIR . 'views/' ); // Has trailing slash

		if ( ! apply_filters( 'wp_stream_reports_load', true ) ) {
			return;
		}

		add_action( 'init', array( $this, 'load' ) );
	}

	/**
	 * Load our classes, actions/filters, only if our big brother is activated.
	 * GO GO GO!
	 *
	 * @return void
	 */
	public function load() {
		// Register new submenu
		if ( ! apply_filters( 'wp_stream_reports_disallow_site_access', false ) && ! WP_Stream_Admin::$disable_access && ( WP_Stream::is_connected() || WP_Stream::is_development_mode() ) ) {
			add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		}

		add_action( 'all_admin_notices', array( $this, 'admin_notices' ) );

		// Load settings
		require_once WP_STREAM_REPORTS_INC_DIR . 'class-wp-stream-reports-settings.php';
		add_action( 'init', array( 'WP_Stream_Reports_Settings', 'load' ), 9 );

		// Load date interval
		require_once WP_STREAM_CLASS_DIR . 'class-wp-stream-date-interval.php';
		require_once WP_STREAM_REPORTS_INC_DIR . 'class-wp-stream-reports-date-interval.php';
		add_action( 'init', array( 'WP_Stream_Reports_Date_Interval', 'get_instance' ) );

		// Load metaboxes and charts
		require_once WP_STREAM_REPORTS_INC_DIR . 'class-wp-stream-reports-meta-boxes.php';
		require_once WP_STREAM_REPORTS_INC_DIR . 'class-wp-stream-reports-charts.php';
		add_action( 'init', array( 'WP_Stream_Reports_Metaboxes', 'get_instance' ), 12 );

		// Load template tags
		require_once WP_STREAM_REPORTS_INC_DIR . 'template-tags.php';

		// Register and enqueue the administration scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'register_ui_assets' ), 20 );
		add_action( 'admin_print_scripts', array( $this, 'dequeue_media_conflicts' ), 9999 );
	}

	/**
	 * @param array $ajax_hooks associative array of ajax hooks action to actual functionname
	 * @param object $referer Class refering the action
	 */
	public static function handle_ajax_request( $ajax_hooks, $referer ) {
		// If we are not in ajax mode, return early
		if ( ! defined( 'DOING_AJAX' ) || ! is_object( $referer ) ) {
			return;
		}

		foreach ( $ajax_hooks as $hook => $function ) {
			add_action( "wp_ajax_{$hook}", array( $referer, $function ) );
		}

		// Check referer here so we don't have to check it on every function call
		if ( array_key_exists( $_REQUEST['action'], $ajax_hooks ) ) {
			// Checking permission
			if ( ! current_user_can( WP_Stream_Reports::VIEW_CAP ) ) {
				wp_die( __( 'Cheating huh?', 'stream' ) );
			}
			check_admin_referer( 'stream-reports-page', 'wp_stream_reports_nonce' );
		}
	}

	/**
	 * Register Notification menu under Stream's main one
	 *
	 * @action admin_menu
	 * @return void
	 */
	public function register_menu() {
		self::$screen_id = add_submenu_page(
			WP_Stream_Admin::RECORDS_PAGE_SLUG,
			__( 'Reports', 'stream' ),
			__( 'Reports', 'stream' ),
			self::VIEW_CAP,
			self::REPORTS_PAGE_SLUG,
			array( $this, 'page' )
		);

		// Create nonce right away so it is accessible everywhere
		self::$nonce = array( 'wp_stream_reports_nonce' => wp_create_nonce( 'stream-reports-page' ) );

		$metabox = WP_Stream_Reports_Metaboxes::get_instance();
		add_action( 'load-' . self::$screen_id, array( $metabox, 'load_page' ) );
	}

	/**
	 * Register and enqueue the scripts related to our plugin.
	 *
	 * @action   admin_enqueue_scripts
	 * @uses     wp_register_script
	 * @uses     wp_enqueue_script
	 *
	 * @param $pagename the actual page name
	 *
	 * @return void
	 */
	public function register_ui_assets( $pagename ) {
		// JavaScript registration
		wp_register_script(
			'stream-reports-d3',
			WP_STREAM_REPORTS_URL . 'ui/lib/d3/d3.min.js',
			array(),
			'3.5.3',
			true
		);

		wp_register_script(
			'stream-reports-nvd3',
			WP_STREAM_REPORTS_URL . 'ui/lib/nvd3/nv.d3.min.js',
			array( 'stream-reports-d3' ),
			'1.1.15b',
			true
		);

		wp_register_script(
			'stream-reports',
			WP_STREAM_REPORTS_URL . 'ui/js/stream-reports.js',
			array( 'stream-reports-nvd3', 'jquery', 'underscore', 'jquery-ui-datepicker' ),
			WP_STREAM::VERSION,
			true
		);

		// CSS registration
		wp_register_style(
			'stream-reports-nvd3',
			WP_STREAM_REPORTS_URL . 'ui/lib/nvd3/nv.d3.min.css',
			array(),
			'1.1.15b',
			'screen'
		);

		wp_register_style(
			'stream-reports',
			WP_STREAM_REPORTS_URL . 'ui/css/stream-reports.css',
			array( 'stream-reports-nvd3', 'wp-stream-datepicker' ),
			WP_STREAM::VERSION,
			'screen'
		);

		// If we are not on the right page we return early
		if ( $pagename !== self::$screen_id ) {
			return;
		}

		// Localization
		wp_localize_script(
			'stream-reports',
			'wp_stream_reports',
			array(
				'i18n'       => array(
					'configure' => __( 'Configure', 'stream' ),
					'cancel'    => __( 'Cancel', 'stream' ),
					'deletemsg' => __( 'Do you really want to delete this section? This cannot be undone.', 'stream' ),
				),
				'gmt_offset' => get_option( 'gmt_offset' ),
			)
		);

		// Scripts
		wp_enqueue_script( 'stream-reports' );
		wp_enqueue_script( 'select2' );
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'dashboard' );
		wp_enqueue_script( 'postbox' );

		// Styles
		wp_enqueue_style( 'stream-reports' );
		wp_enqueue_style( 'select2' );
	}

	/**
	 * Admin page callback function, redirects to each respective method based
	 * on $_GET['view']
	 *
	 * @return void
	 */
	public function page() {
		// Page class
		$class   = 'metabox-holder columns-' . get_current_screen()->get_columns();
		$add_url = add_query_arg(
			array_merge(
				array(
					'action' => 'wp_stream_reports_add_metabox',
				),
				self::$nonce
			),
			admin_url( 'admin-ajax.php' )
		);

		$sections   = WP_Stream_Reports_Settings::get_user_options( 'sections' );
		$no_reports = empty( $sections );
		$create_url = add_query_arg(
			array_merge(
				array(
					'action' => 'wp_stream_reports_default_reports',
				),
				self::$nonce
			),
			admin_url( 'admin-ajax.php' )
		);

		$no_reports_message = __( "There's nothing here! Do you want to <a href=\"%s\">create some reports</a>?", 'stream' );
		$no_reports_message = sprintf( $no_reports_message, $create_url );

		$view = (object) array(
			'slug' => 'all',
			'path' => null,
		);

		// Avoid throwing Notices by testing the variable
		if ( isset( $_GET['view'] ) && ! empty( $_GET['view'] ) ){
			$view->slug = sanitize_file_name( wp_unslash( $_GET['view'] ) );
		}

		// First we check if the file exists in our plugin folder, otherwhise give the user an error
		if ( ! file_exists( WP_STREAM_REPORTS_VIEW_DIR . $view->slug . '.php' ) ){
			$view->slug = 'error';
		}

		// Define the path for the view we
		$view->path = WP_STREAM_REPORTS_VIEW_DIR . $view->slug . '.php';

		// Execute some actions before including the view, to allow others to hook in here
		// Use these to do stuff related to the view you are working with
		do_action( 'wp_stream_reports_view', $view );
		do_action( "wp_stream_reports_view-{$view->slug}", $view );

		include_once $view->path;
	}

	/**
	 * Remove conflicting JS libraries caused by other plugins loading on pages not belonging to them
	 */
	function dequeue_media_conflicts() {
		if ( 'stream_page_' . self::REPORTS_PAGE_SLUG !== get_current_screen()->id ) {
			return;
		}

		wp_dequeue_script( 'media-upload' );
		wp_enqueue_script( 'media-editor' );
		wp_dequeue_script( 'media-audiovideo' );
		wp_dequeue_script( 'mce-view' );
		wp_dequeue_script( 'image-edit' );
		wp_dequeue_script( 'media-editor' );
		wp_dequeue_script( 'media-audiovideo' );
	}

	/**
	 * Display all messages on admin board
	 *
	 * @return void
	 */
	public static function admin_notices() {
		foreach ( self::$messages as $message ) {
			echo wp_kses_post( $message );
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

$GLOBALS['wp_stream_reports'] = WP_Stream_Reports::get_instance();
