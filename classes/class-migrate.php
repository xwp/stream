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
	private $api_key;

	/**
	 * Hold site UUID
	 *
	 * @var string
	 */
	private $site_uuid;

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

		$this->limit = absint( apply_filters( 'wp_stream_migrate_chunk_size', 100 ) );

		$this->record_count = $this->get_record_count();

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
	private function is_connected() {
		return ( ! empty( $this->api_key ) && ! empty( $this->site_uuid ) );
	}

	/**
	 * Disconnect from WP Stream
	 */
	private function disconnect() {
		delete_option( 'wp_stream_site_api_key' );
		delete_option( 'wp_stream_site_uuid' );
		delete_option( 'wp_stream_delay_migration' );
		delete_option( 'wp_stream_site_restricted' );

		$this->api_key   = false;
		$this->site_uuid = false;
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

		$defaults = array(
			'sort' => array(
				array(
					'created' => array(
						'order' => 'asc',
					),
				),
			),
		);

		$last = get_option( 'wp_stream_last_migrated' );

		if ( $last ) {
			$defaults['filter'] = array(
				'and' => array(
					array(
						'range' => array(
							'created' => array(
								'gt' => $last,
							),
						),
					),
				),
			);
		}

		$body['sites'] = array( $this->site_uuid );
		$body['query'] = array_merge( $defaults, (array) $query );

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
			! empty( $this->record_count )
			&&
			false === get_transient( 'wp_stream_delay_migration' )
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
			'<h3>%s</h3><strong id="stream-migrate-title">%s</strong></p><p id="stream-migrate-blog-link"><a href="https://wp-stream.com/introducing-stream-3/" target="_blank">%s</a></p><p id="stream-migrate-message">%s</p><div id="stream-migrate-progress"><progress value="0" max="100"></progress> <strong>0&#37;</strong> <em></em> <button id="stream-migrate-actions-close" class="button button-secondary">%s</button><div class="clear"></div></div><p id="stream-migrate-actions"><button id="stream-start-migrate" class="button button-primary">%s</button> <button id="stream-migrate-reminder" class="button button-secondary">%s</button> <a href="#" id="stream-ignore-migrate" class="delete">%s</a>',
			__( 'Stream Records Update' ),
			__( 'Our cloud storage services will be shutting down permanently on September 1, 2015', 'stream' ),
			__( 'Read the announcement post', 'stream' ),
			sprintf( esc_html__( 'We found %s activity records in the cloud that need to be migrated to your local database.', 'stream' ), number_format( $this->record_count ) ),
			__( 'Close', 'stream' ),
			__( 'Start Migration Now', 'stream' ),
			__( 'Remind Me Later', 'stream' ),
			__( "No thanks, I don't want to migrate", 'stream' )
		);

		$this->plugin->admin->notice( $notice, true );
	}

	/**
	 * Ajax callback for processing migrate actions
	 *
	 * Break down the total number of records found into reasonably-sized
	 * chunks and save records from each of those chunks to the local DB.
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

		switch ( $action ) {
			case 'migrate':
			case 'continue':
				$this->migrate();
				break;
			case 'delay':
				$this->delay();
				break;
			case 'ignore':
				$this->ignore();
				break;
		}

		die();
	}

	/**
	 * Migrate a chunk of records
	 *
	 * @return string JSON data
	 */
	private function migrate() {
		$records = $this->get_records( $this->limit );

		// Disconnect when there are no records left
		if ( ! $records ) {
			$this->disconnect();

			wp_send_json_success( esc_html__( 'Migration complete!', 'stream' ) );
		}

		$records_saved = $this->save_records( $records );

		if ( true !== $records_saved ) {
			wp_send_json_error( esc_html__( 'An unknown error occurred during migration. Please try again later or contact support.', 'stream' ) );
		}

		wp_send_json_success( 'continue' );
	}

	/**
	 * Delay the migration of records for 3 hours
	 *
	 * @return string JSON data
	 */
	private function delay() {
		set_transient( 'wp_stream_delay_migration', "Don't nag me, bro", HOUR_IN_SECONDS * 3 );

		wp_send_json_success( esc_html__( "OK, we'll remind you again in a few hours.", 'stream' ) );
	}

	/**
	 * Don't migrate any records
	 *
	 * @return string JSON data
	 */
	private function ignore() {
		$this->disconnect();

		wp_send_json_success( esc_html__( 'All new activity will be stored in the local database.', 'stream' ) );
	}

	/**
	 * Save records to the database
	 *
	 * @param array $records
	 *
	 * @return bool
	 */
	private function save_records( $records ) {
		foreach ( $records as $record ) {
			// Remove existing meta field
			unset( $record->meta );

			// Map fields to the newer data model
			$record->user_id         = $record->author;
			$record->user_role       = $record->author_role;
			$record->meta            = $record->stream_meta;
			$record->meta->user_meta = $record->author_meta;

			// Convert the record object to a record array
			// @codingStandardsIgnoreStart
			$record = json_decode( json_encode( $record ), true );
			// @codingStandardsIgnoreEnd

			// Save the record
			$inserted = $this->plugin->db->insert( $record );

			// Save the date of the last known migrated record
			if ( false !== $inserted ) {
				update_option( 'wp_stream_last_migrated', $record['created'] );
			}
		}

		return true;
	}
}
