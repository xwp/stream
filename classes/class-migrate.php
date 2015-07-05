<?php
namespace WP_Stream;

class Migrate {
	/**
	 * Hold Plugin class
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Hold site API Key
	 *
	 * @var string
	 */
	public $api_key;

	/**
	 * Hold site UUID
	 *
	 * @var string
	 */
	public $site_uuid;

	/**
	 * Hold the total number of legacy records found in the cloud
	 *
	 * @var int
	 */
	public $record_count;

	/**
	 * Limit payload chunks to a certain number of records
	 *
	 * @var int
	 */
	public $limit;

	/**
	 * Number of chunks required to migrate
	 *
	 * @var int
	 */
	public $chunks;

	/**
	 * Class constructor.
	 *
	 * @param Plugin $plugin The main Plugin class.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		$this->api_key   = get_option( 'wp_stream_site_api_key' );
		$this->site_uuid = get_option( 'wp_stream_site_uuid' );

		// Exit early if disconnected
		if ( ! $this->is_connected() ) {
			return;
		}

		$this->record_count = $this->get_record_count();

		// Disconnect and exit if no records exist
		if ( empty( $this->record_count ) ) {
			$this->disconnect();

			return;
		}

		$this->limit  = absint( apply_filters( 'wp_stream_migrate_chunk_size', 100 ) );
		$this->chunks = ( $this->record_count > $this->limit ) ? absint( ceil( $this->record_count / $this->limit ) ) : 1;

		// Display admin notice
		add_action( 'admin_notices', array( $this, 'migrate_notice' ), 9 );

		// AJAX callback for migrate action
		add_action( 'wp_ajax_wp_stream_migrate_action', array( $this, 'migrate_action_callback' ) );
	}

	/**
	 * Are we currently connected to WP Stream?
	 *
	 * @return bool
	 */
	public function is_connected() {
		return ( ! empty( $this->api_key ) && ! empty( $this->site_uuid ) );
	}

	/**
	 * Disconnect from WP Stream
	 */
	public function disconnect() {
		delete_option( 'wp_stream_site_api_key' );
		delete_option( 'wp_stream_site_uuid' );
		delete_option( 'wp_stream_migrate_chunk' );

		$this->api_key   = false;
		$this->site_uuid = false;
	}

	/**
	 * Get the current chunk number being migrated
	 *
	 * @return int
	 */
	private function get_current_chunk() {
		return absint( get_option( 'wp_stream_migrate_chunk', 1 ) );
	}

	/**
	 * Search for records
	 *
	 * @param array $query
	 *
	 * @return mixed Response body on success, or FALSE on failure
	 */
	private function search( $query = array() ) {
		if ( ! $this->is_connected() ) {
			return false;
		}

		$body['sites'] = array( $this->site_uuid );
		$body['query'] = (array) $query;

		$args = array(
			'headers'   => array(
				'Stream-Site-API-Key' => $this->api_key,
				'Content-Type'        => 'application/json',
			),
			'method'    => 'POST',
			'body'      => wp_stream_json_encode( $body ),
			'sslverify' => true,
		);

		$response = wp_safe_remote_request( 'https://api.wp-stream.com/search', $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Get the total number of records found
	 *
	 * @return int
	 */
	private function get_record_count() {
		$response = $this->search( array( 'size' => 0 ) );

		if ( empty( $response->meta->total ) ) {
			return 0;
		}

		return absint( $response->meta->total );
	}

	/**
	 * Get a chunk of records
	 *
	 * @param int $limit (optional)
	 * @param int $offset (optional)
	 *
	 * @return array|bool An array of record arrays, or FALSE if no records were found
	 */
	private function get_records( $limit = 100, $offset = 0 ) {
		$limit = is_int( $limit ) ? $limit : $this->limit;

		$query = array(
			'size' => absint( $limit ),
			'from' => absint( $offset ),
		);

		$response = $this->search( $query );

		if ( empty( $response->records ) ) {
			return false;
		}

		return $response->records;
	}

	/**
	 * Determine where and when the migrate notice should be displayed
	 *
	 * @see Admin->admin_enqueue_scripts()
	 *
	 * @return bool
	 */
	public function show_migrate_notice() {
		if (
			! isset( $_GET['migrate_action'] ) // input var okay
			&&
			$this->is_connected()
			&&
			$this->plugin->admin->is_stream_screen()
			&&
			! empty( $this->record_count )
			&&
			false === get_transient( 'wp_stream_migrate_delayed' )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Give the user options for how to handle their records
	 *
	 * @action admin_notices
	 */
	public function migrate_notice() {
		if ( ! $this->show_migrate_notice() ) {
			return;
		}

		$notice = sprintf(
			'<strong id="stream-migrate-title">%s</strong></p><p><a href="#" target="_blank">%s</a></p><p id="stream-migrate-message">%s</p><div id="stream-migrate-progress"><progress value="0" max="100"></progress> <strong>0&#37;</strong> <em></em> <button id="stream-migrate-actions-close" class="button button-secondary">%s</button><div class="clear"></div></div><p id="stream-migrate-actions"><button id="stream-start-migrate" class="button button-primary">%s</button> <button id="stream-migrate-reminder" class="button button-secondary">%s</button> <a href="#" id="stream-delete-records" class="delete">%s</a>',
			__( 'Our cloud storage services will be shutting down permanently on September 1, 2015', 'stream' ),
			__( 'Read the announcement post', 'stream' ),
			sprintf( esc_html__( 'We found %s activity records in the cloud that need to be migrated to your local database.', 'stream' ), number_format( $this->record_count ) ),
			__( 'Close', 'stream' ),
			__( 'Start Migration Now', 'stream' ),
			__( 'Remind Me Later', 'stream' ),
			__( "No thanks, I don't want to migrate", 'stream' )
		);

		$this->plugin->notice( $notice, true );
	}

	/**
	 * Ajax callback for processing migrate actions
	 *
	 * Break down the total number of records found into reasonably-sized chunks
	 * and save records from each of those chunks to the local DB.
	 *
	 * Disconnects from WP Stream once the migration is complete.
	 *
	 * @action wp_ajax_wp_stream_migrate_action
	 */
	public function migrate_action_callback() {
		$action = wp_stream_filter_input( INPUT_POST, 'migrate_action' );
		$nonce  = wp_stream_filter_input( INPUT_POST, 'nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_stream_migrate-' . absint( get_current_blog_id() ) . absint( get_current_user_id() ) ) ) {
			return;
		}

		set_time_limit( 0 ); // Just in case, this could take a while for some

		if ( 'migrate' === $action ) {
			$this->migrate();
		}

		if ( 'delay' === $action ) {
			$this->delay();
		}

		if ( 'delete' === $action ) {
			$this->delete();
		}

		die();
	}

	/**
	 * Migrate a chunk of records
	 *
	 * @return string JSON data
	 */
	private function migrate() {
		$chunk   = $this->get_current_chunk();
		$offset  = ( $chunk - 1 ) * $this->limit;
		$records = $this->get_records( $this->limit, $offset );

		// Disconnect when complete
		if ( empty( $records ) || $chunk > $this->chunks ) {
			$this->disconnect();

			wp_send_json_success( esc_html__( 'Migration complete!', 'stream' ) );
		}

		$records_saved = $this->save_records( $records );

		if ( true !== $records_saved ) {
			wp_send_json_error( esc_html__( 'An unknown error occurred during migration. Please try again later or contact support.', 'stream' ) );

			// @TODO: Provide better error messages during $this->save_records()
		}

		// Records have been saved, move on to the next chunk
		update_option( 'wp_stream_migrate_chunk', absint( $chunk + 1 ) );

		wp_send_json_success( 'continue' );
	}

	/**
	 * Delay the migration of records
	 *
	 * @return string JSON data
	 */
	private function delay() {
		set_transient( 'wp_stream_migrate_delayed', "Don't nag me, bro", HOUR_IN_SECONDS * 3 );

		wp_send_json_success( esc_html__( "OK, we'll remind you again in a few hours.", 'stream' ) );
	}

	/**
	 * Don't migrate any records
	 *
	 * @return string JSON data
	 */
	private function delete() {
		wp_send_json_success( esc_html__( 'Your records will not be migrated. Thank you for using Stream!', 'stream' ) );
	}

	/**
	 * Save records to the database
	 *
	 * @param array $records
	 *
	 * @return bool
	 */
	private function save_records( $records ) {
		return true;

		// @TODO: Save records to the local DB
	}

}
