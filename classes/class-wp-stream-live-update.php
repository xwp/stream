<?php

class WP_Stream_Live_Update {

	/**
	 * User meta key/identifier
	 *
	 * @const string
	 */
	const USER_META_KEY = 'stream_live_update_records';

	/**
	 * List table object instance
	 *
	 * @var object
	 */
	public static $list_table = null;

	/**
	 * Load live updates methods
	 */
	public static function load() {
		// Heartbeat live update
		add_filter( 'heartbeat_received', array( __CLASS__, 'heartbeat_received' ), 10, 2 );

		// Enable/Disable live update per user
		add_action( 'wp_ajax_stream_enable_live_update', array( __CLASS__, 'enable_live_update' ) );
	}

	/**
	 * Ajax function to enable/disable live update
	 *
	 * @return string Ajax respsonse back in JSON format
	 */
	public static function enable_live_update() {
		check_ajax_referer( self::USER_META_KEY . '_nonce', 'nonce' );

		$input = array(
			'checked'   => FILTER_SANITIZE_STRING,
			'user'      => FILTER_SANITIZE_STRING,
			'heartbeat' => FILTER_SANITIZE_STRING,
		);

		$input = filter_input_array( INPUT_POST, $input );

		if ( false === $input ) {
			wp_send_json_error( 'Error in live update checkbox' );
		}

		$checked = ( 'checked' === $input['checked'] ) ? 'on' : 'off';

		$user = (int) $input['user'];

		if ( 'false' === $input['heartbeat'] ) {
			update_user_meta( $user, self::USER_META_KEY, 'off' );

			wp_send_json_error( esc_html__( "Live updates could not be enabled because Heartbeat is not loaded.\n\nYour hosting provider or another plugin may have disabled it for performance reasons.", 'stream' ) );

			return;
		}

		$success = update_user_meta( $user, self::USER_META_KEY, $checked );

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
	 * @param array Response to heartbeat
	 * @param array Response from heartbeat
	 *
	 * @return array Data sent to heartbeat
	 */
	public static function live_update( $response, $data ) {
		if ( ! isset( $data['wp-stream-heartbeat-last-time'] ) ) {
			return;
		}

		$last_time = $data['wp-stream-heartbeat-last-time'];
		$query     = $data['wp-stream-heartbeat-query'];

		if ( empty( $query ) ) {
			$query = array();
		}

		// Decode the query
		$query = json_decode( wp_kses_stripslashes( $query ) );

		$updated_items = self::gather_updated_items( $last_time, (array) $query );

		if ( ! empty( $updated_items ) ) {
			ob_start();

			foreach ( $updated_items as $item ) {
				self::$list_table->single_row( $item );
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
	 * @param int   Timestamp of last update
	 * @param array Query args
	 *
	 * @return array  Array of recently updated items
	 */
	public static function gather_updated_items( $last_time, $args = array() ) {
		if ( false === $last_time ) {
			return '';
		}

		if ( empty( self::$list_table->items ) ) {
			return '';
		}

		$items = array();

		foreach ( self::$list_table->items as $item ) {
			if ( strtotime( $item->created ) > strtotime( $last_time ) ) {
				$items[] = $item;
			} else {
				break;
			}
		}

		return $items;
	}

	/**
	 * Handles live updates for both dashboard widget and Stream Post List
	 *
	 * @action heartbeat_recieved
	 *
	 * @param array Response to be sent to heartbeat tick
	 * @param array Data from heartbeat send
	 *
	 * @return array Data sent to heartbeat tick
	 */
	public static function heartbeat_received( $response, $data ) {
		// Only fire when Stream is requesting a live update
		if ( ! isset( $data['wp-stream-heartbeat'] ) ) {
			return $response;
		}

		$option                  = get_option( 'dashboard_stream_activity_options' );
		$enable_stream_update    = ( 'off' !== get_user_meta( get_current_user_id(), self::USER_META_KEY, true ) );
		$enable_dashboard_update = ( 'off' !== ( $option['live_update'] ) );

		// Register list table
		self::$list_table = new WP_Stream_List_Table( array( 'screen' => 'toplevel_page_' . WP_Stream_Admin::RECORDS_PAGE_SLUG ) );
		self::$list_table->prepare_items();

		$total_items = isset( self::$list_table->_pagination_args['total_items'] ) ? self::$list_table->_pagination_args['total_items'] : null;
		$total_pages = isset( self::$list_table->_pagination_args['total_pages'] ) ? self::$list_table->_pagination_args['total_pages'] : null;
		$per_page    = isset( self::$list_table->_pagination_args['per_page'] ) ? self::$list_table->_pagination_args['per_page'] : null;

		if ( isset( $data['wp-stream-heartbeat'] ) && isset( $total_items ) ) {
			$response['total_items']      = $total_items;
			$response['total_items_i18n'] = sprintf( _n( '1 item', '%s items', $total_items ), number_format_i18n( $total_items ) );
		}

		if ( isset( $data['wp-stream-heartbeat'] ) && 'live-update' === $data['wp-stream-heartbeat'] && $enable_stream_update ) {

			if ( ! empty( $data['wp-stream-heartbeat'] ) ) {
				if ( isset( $total_pages ) ) {
					$response['total_pages']      = $total_pages;
					$response['total_pages_i18n'] = number_format_i18n( $total_pages );

					$query_args          = json_decode( $data['wp-stream-heartbeat-query'], true );
					$query_args['paged'] = $total_pages;

					$response['last_page_link'] = add_query_arg( $query_args, admin_url( 'admin.php' ) );
				} else {
					$response['total_pages'] = 0;
				}
			}

			$response['wp-stream-heartbeat'] = self::live_update( $response, $data );

		} elseif ( isset( $data['wp-stream-heartbeat'] ) && 'dashboard-update' === $data['wp-stream-heartbeat'] && $enable_dashboard_update ) {

			$per_page = isset( $option['records_per_page'] ) ? absint( $option['records_per_page'] ) : 5;

			if ( isset( $total_items ) ) {
				$total_pages = ceil( $total_items / $per_page );
				$response['total_pages'] = $total_pages;
				$response['total_pages_i18n'] = number_format_i18n( $total_pages );

				$query_args['page']  = WP_Stream_Admin::RECORDS_PAGE_SLUG;
				$query_args['paged'] = $total_pages;

				$response['last_page_link'] = add_query_arg( $query_args, admin_url( 'admin.php' ) );
			}

			$response['per_page'] = $per_page;
			$response['wp-stream-heartbeat'] = WP_Stream_Dashboard_Widget::live_update( $response, $data );

		} else {
			$response['log'] = 'fail';
		}

		return $response;
	}

}
