<?php

class WP_Stream_Live_Update {

	public static $list_table = null;

	public static function load() {
		// Heartbeat live update
		add_filter( 'heartbeat_received', array( __CLASS__, 'heartbeat_received' ), 10, 2 );

		// Enable/Disable live update per user
		add_action( 'wp_ajax_stream_enable_live_update', array( __CLASS__, 'enable_live_update' ) );

	}

	/**
	 * Ajax function to enable/disable live update
	 * @return void/json
	 */
	public static function enable_live_update() {
		check_ajax_referer( 'stream_live_update_records_nonce', 'nonce' );

		$input = array(
			'checked' => FILTER_SANITIZE_STRING,
			'user'    => FILTER_SANITIZE_STRING,
		);

		$input = filter_input_array( INPUT_POST, $input );

		if ( false === $input ) {
			wp_send_json_error( 'Error in live update checkbox' );
		}

		$checked = ( 'checked' === $input['checked'] ) ? 'on' : 'off';

		$user = (int) $input['user'];

		$success = update_user_meta( $user, 'stream_live_update_records', $checked );

		if ( $success ) {
			wp_send_json_success( 'Live Updates Enabled' );
		} else {
			wp_send_json_error( 'Live Updates checkbox error' );
		}
	}

	/**
	 * Sends Updated Actions to the List Table View
	 *
	 * @todo fix reliability issues with sidebar widgets
	 *
	 * @uses gather_updated_items
	 * @uses generate_row
	 *
	 * @param  array  Response to heartbeat
	 * @param  array  Response from heartbeat
	 *
	 * @return array  Data sent to heartbeat
	 */
	public static function live_update( $response, $data ) {
		if ( ! isset( $data['wp-stream-heartbeat-last-id'] ) ) {
			return;
		}

		$last_id = intval( $data['wp-stream-heartbeat-last-id'] );
		$query   = $data['wp-stream-heartbeat-query'];
		if ( empty( $query ) ) {
			$query = array();
		}

		// Decode the query
		$query = json_decode( wp_kses_stripslashes( $query ) );

		$updated_items = WP_Stream_Dashboard_Widget::gather_updated_items( $last_id, $query );

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
	 * Handles live updates for both dashboard widget and Stream Post List
	 *
	 * @action heartbeat_recieved
	 * @param  array  Response to be sent to heartbeat tick
	 * @param  array  Data from heartbeat send
	 * @return array  Data sent to heartbeat tick
	 */
	public static function heartbeat_received( $response, $data ) {
		$option                  = get_option( 'dashboard_stream_activity_options' );
		$enable_stream_update    = ( 'off' !== get_user_meta( get_current_user_id(), 'stream_live_update_records', true ) );
		$enable_dashboard_update = ( 'off' !== ( $option['live_update'] ) );

		// Register list table
		require_once WP_STREAM_INC_DIR . 'list-table.php';
		self::$list_table = new WP_Stream_List_Table( array( 'screen' => 'toplevel_page_' . WP_Stream_Admin::RECORDS_PAGE_SLUG ) );
		self::$list_table->prepare_items();

		extract( self::$list_table->_pagination_args, EXTR_SKIP );

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