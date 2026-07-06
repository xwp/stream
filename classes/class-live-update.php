<?php
/**
 * Processes update calls from the Stream Records page.
 *
 * @package WP_Stream
 */

namespace WP_Stream;

/**
 * Class - Live_Update
 */
class Live_Update {
	/**
	 * Holds instance of plugin object
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * User meta key/identifier
	 *
	 * @var string
	 */
	public $user_meta_key = 'stream_live_update_records';

	/**
	 * List table object instance
	 *
	 * @var List_Table
	 */
	public $list_table = null;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin Instance of plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// Heartbeat live update.
		add_filter( 'heartbeat_received', array( $this, 'heartbeat_received' ), 10, 2 );

		// Enable / Disable live update per user.
		add_action( 'wp_ajax_stream_enable_live_update', array( $this, 'enable_live_update' ) );
	}

	/**
	 * Ajax function to enable/disable live update
	 *
	 * @return string Ajax respsonse back in JSON format
	 */
	public function enable_live_update() {
		check_ajax_referer( $this->user_meta_key . '_nonce', 'nonce' );

		if ( ! current_user_can( $this->plugin->admin->view_cap ) ) {
			wp_send_json_error( esc_html__( 'You do not have permission to do this.', 'stream' ) );
		}

		$checked = ( 'checked' === wp_stream_filter_input( INPUT_POST, 'checked' ) ) ? 'on' : 'off';

		$user = get_current_user_id();

		if ( 'false' === wp_stream_filter_input( INPUT_POST, 'heartbeat' ) ) {
			update_user_meta( $user, $this->user_meta_key, 'off' );

			wp_send_json_error( esc_html__( "Live updates could not be enabled because Heartbeat is not loaded.\n\nYour hosting provider or another plugin may have disabled it for performance reasons.", 'stream' ) );

			return;
		}

		$success = update_user_meta( $user, $this->user_meta_key, $checked );

		if ( $success ) {
			wp_send_json_success( ( 'on' === $checked ) ? 'Live Updates enabled' : 'Live Updates disabled' );
		} else {
			wp_send_json_error( 'Live Updates checkbox error' );
		}
	}

	/**
	 * Sends updated actions to the list table view
	 *
	 * @todo Fix reliability issues with sidebar widgets
	 *
	 * @uses gather_updated_items
	 * @uses generate_row
	 *
	 * @param array $response Response to heartbeat.
	 * @param array $data     Data from heartbeat.
	 *
	 * @return array Data sent to heartbeat
	 */
	public function live_update( $response, $data ) {
		unset( $response );

		if ( ! isset( $data['wp-stream-heartbeat-last-time'] ) ) {
			return array();
		}

		$last_time = $data['wp-stream-heartbeat-last-time'];
		$query     = $data['wp-stream-heartbeat-query'];

		if ( empty( $query ) ) {
			$query = array();
		}

		// Decode the query.
		$query = json_decode( wp_kses_stripslashes( $query ) );

		$updated_items = $this->gather_updated_items( $last_time, (array) $query );

		if ( ! empty( $updated_items ) ) {
			ob_start();

			foreach ( $updated_items as $item ) {
				$this->list_table->single_row( $item );
			}

			$send = ob_get_clean();
		} else {
			$send = '';
		}

		return $send;
	}

	/**
	 * Sends Updated Actions to the List Table View
	 *
	 * @param int   $last_time Timestamp of last update.
	 * @param array $args      Query args.
	 *
	 * @return array Array of recently updated items
	 */
	public function gather_updated_items( $last_time, $args = array() ) {
		unset( $args );

		if ( false === $last_time ) {
			return '';
		}

		if ( empty( $this->list_table->items ) ) {
			return '';
		}

		$items = array();

		foreach ( $this->list_table->items as $item ) {
			if ( strtotime( $item->created ) > strtotime( $last_time ) ) {
				$items[] = $item;
			} else {
				break;
			}
		}

		return $items;
	}

	/**
	 * Handles live updates for Stream Post List
	 *
	 * @action heartbeat_received
	 *
	 * @param array $response Response to be sent to heartbeat tick.
	 * @param array $data     Data from heartbeat send.
	 *
	 * @return array Data sent to heartbeat tick
	 */
	public function heartbeat_received( $response, $data ) {
		// Only fire when Stream is requesting a live update.
		if ( ! isset( $data['wp-stream-heartbeat'] ) ) {
			return $response;
		}

		// Ensure the current user is allowed to view Stream records before
		// exposing any activity data through the Heartbeat API.
		if ( ! current_user_can( $this->plugin->admin->view_cap ) ) {
			return $response;
		}

		$enable_stream_update = ( 'off' !== get_user_meta( get_current_user_id(), $this->user_meta_key, true ) );

		// Register list table.
		$this->list_table = new List_Table(
			$this->plugin,
			array(
				'screen' => 'toplevel_page_' . $this->plugin->admin->records_page_slug,
			)
		);
		$this->list_table->prepare_items();

		$total_items = isset( $this->list_table->_pagination_args['total_items'] ) ? $this->list_table->_pagination_args['total_items'] : null;
		$total_pages = isset( $this->list_table->_pagination_args['total_pages'] ) ? $this->list_table->_pagination_args['total_pages'] : null;

		if ( isset( $data['wp-stream-heartbeat'] ) && isset( $total_items ) ) {
			$response['total_items'] = $total_items;
			/* translators: %d: number of items (e.g. "42") */
			$response['total_items_i18n'] = sprintf( _n( '%d item', '%d items', $total_items ), number_format_i18n( $total_items ) );
		}

		if ( isset( $data['wp-stream-heartbeat'] ) && 'live-update' === $data['wp-stream-heartbeat'] && $enable_stream_update ) {

			if ( ! empty( $data['wp-stream-heartbeat'] ) ) {
				if ( isset( $total_pages ) ) {
					$response['total_pages']      = $total_pages;
					$response['total_pages_i18n'] = number_format_i18n( $total_pages );

					$query_args          = json_decode( $data['wp-stream-heartbeat-query'], true );
					$query_args['paged'] = $total_pages;

					$response['last_page_link'] = esc_url( add_query_arg( $query_args, admin_url( 'admin.php' ) ) );
				} else {
					$response['total_pages'] = 0;
				}
			}

			$response['wp-stream-heartbeat'] = $this->live_update( $response, $data );

		} else {
			$response['log'] = 'fail';
		}

		return $response;
	}
}
