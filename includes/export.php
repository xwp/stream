<?php

class WP_Stream_Notifications_Import_Export {

	public static function export() {

		if ( ! wp_verify_nonce( wp_stream_filter_input( INPUT_GET, 'stream_notifications_nonce' ), 'stream-notifications-nonce' ) ) {
			wp_die( __( 'Invalid nonce, go back and try again.', 'stream-notifications' ) );
		}

		$args  = array(
			'type'           => 'notification_rule',
			'ignore_context' => true,
			'posts_per_page' => -1,
			'order'          => 'asc',
		);
		$query  = stream_query( $args );
		$items  = array();
		$cached = get_transient( 'stream-notification-rules' );

		foreach ( $query as $rule ) {
			$rule = new WP_Stream_Notification_Rule( $rule->ID );
			$rule->ID = null;
			$items[] = $rule->to_array();
		}

		$json = json_encode( $items );

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="stream-notification-rules_' . current_time( 'timestamp', 1 ) . '.json"' );
		header( 'Connection: Keep-Alive' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . strlen( $json ) );
		echo $json; // xss ok
	}

	public static function import() {
		$filename = 'notifications_import_rules';
		if ( ! empty( $_FILES[ WP_Stream_Settings::KEY ][ 'tmp_name' ][ $filename ] ) ) {
			$tmpfile = $_FILES[ WP_Stream_Settings::KEY ][ 'tmp_name' ][ $filename ];
			$result = self::_import( file_get_contents( $tmpfile ) );

			if ( $result ) {
				list( $class, $message ) = $result;
				add_settings_error(
					WP_Stream_Settings::KEY,
					'imported',
					$message,
					$class
				);
			}
		}
		return func_get_arg( 0 ); // This is filtering 'pre_update_option_' so must return the passed value
	}

	private static function _import( $contents ) {
		$items = json_decode( $contents, true );
		$added = 0;

		if ( empty( $items ) ) {
			return array( 'error', __( 'Error importing rules, invalid syntax or empty file.', 'stream-notifications' ) );
		}

		foreach ( $items as $item ) {
			$rule = new WP_Stream_Notification_Rule();
			$rule->load_from_array( $item );
			$rule->save();
			++$added;
		}

		return array(
			'updated',
			sprintf( __( 'Imported %d notification rules.', 'stream-notifications' ), $added ),
		);
	}

}