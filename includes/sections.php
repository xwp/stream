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
		self::$sections = get_user_option( __CLASS__ );

		// Apply default if no user option is found
		self::$sections = (self::$sections) ?: $default;

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
				'title'    => "Report {$title_key}" . $configure,
				'priority' => 'default',
				'context'  => 'normal',
			);

			// Parse default argument
			$section = wp_parse_args( $section, $default );

			// Add the actual metabox
			add_meta_box(
				self::META_PREFIX . $key,
				$section['title'],
				array( $this, 'metabox_content' ),
				WP_Stream_Reports::$screen_id,
				$section['context'],
				$section['priority'],
				$key
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
		$key = $section['args'];

		include WP_STREAM_REPORTS_VIEW_DIR . 'section.php';
	}

	/**
	 * This function will handle the ajax request to add a metabox to the page.
	 */
	public function add_metabox() {
		// Add a new section
		self::$sections[] = array();

		// Update the database option
		$this->update_option();
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

		// Update the database option
		$this->update_option();
	}

	// Handle option updating in the database
	private function update_option() {
		$is_saved = update_user_option( get_current_user_id(), __CLASS__, self::$sections );

		if ( $is_saved ) {
			wp_redirect(
				add_query_arg(
					array( 'page' => WP_Stream_Reports::REPORTS_PAGE_SLUG ),
					admin_url( 'admin.php' )
				)
			);
		} else {
			wp_die( __( "Uh no! This wasn't suppose to happen :(", 'stream-reports' ) );
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
