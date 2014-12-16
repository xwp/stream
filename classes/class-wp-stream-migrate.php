<?php

class WP_Stream_Migrate {

	/**
	 * Migrate delay transient name/identifier used when user wants to be reminded to migrate later
	 */
	const MIGRATE_DELAY_TRANSIENT = 'wp_stream_migrate_delayed';

	/**
	 * Hold the current site ID
	 *
	 * @var int
	 */
	public static $site_id = 1;

	/**
	 * Hold the current blog ID
	 *
	 * @var int
	 */
	public static $blog_id = 1;

	/**
	 * Hold the total number of legacy records found in the DB
	 *
	 * @var int
	 */
	public static $record_count = 0;

	/**
	 * Limit payload chunks to a certain number of records
	 *
	 * @var int
	 */
	public static $limit = 0;

	/**
	 * Hold unformatted records temporarily for deletion
	 *
	 * @var array
	 */
	private static $_records = array();

	/**
	 * Check that legacy data exists before doing anything
	 *
	 * @return void
	 */
	public static function load() {
		// Exit early if there is no option holding the DB version
		if ( false === get_site_option( 'wp_stream_db' ) ) {
			return;
		}

		global $wpdb;

		// If there are no legacy tables found, then attempt to clear all legacy data and exit early
		if ( null === $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->base_prefix}stream'" ) ) {
			self::drop_legacy_data( false );
			return;
		}

		self::$site_id = is_multisite() ? get_current_site()->id : 1;
		self::$blog_id = get_current_blog_id();

		self::$record_count = $wpdb->get_var(
			$wpdb->prepare( "
				SELECT COUNT(*)
				FROM {$wpdb->base_prefix}stream AS s, {$wpdb->base_prefix}stream_context AS sc
				WHERE s.site_id = %d
					AND s.blog_id = %d
					AND s.type = 'stream'
					AND sc.record_id = s.ID
				",
				self::$site_id,
				self::$blog_id
			)
		);

		// If there are no legacy records for this site/blog, then attempt to clear all legacy data and exit early
		if ( 0 === self::$record_count ) {
			self::drop_legacy_data();
			return;
		}

		self::$limit = apply_filters( 'wp_stream_migrate_chunk_size', 100 );

		add_action( 'admin_notices', array( __CLASS__, 'migrate_notice' ), 9 );

		add_action( 'wp_ajax_wp_stream_migrate_action', array( __CLASS__, 'process_migrate_action' ) );
	}

	/**
	 * Give the user options for how to handle their legacy Stream records
	 *
	 * @action admin_notices
	 * @return void
	 */
	public static function show_migrate_notice() {
		if ( ! isset( $_GET['migrate_action'] ) && WP_Stream::is_connected() && WP_Stream_Admin::is_stream_screen() && ! empty( self::$record_count ) && false === get_transient( self::MIGRATE_DELAY_TRANSIENT ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Give the user options for how to handle their legacy Stream records
	 *
	 * @action admin_notices
	 * @return void
	 */
	public static function migrate_notice() {
		if ( ! self::show_migrate_notice() ) {
			return;
		}

		$notice = sprintf(
			'<strong id="stream-migrate-title">%s</strong></p><p id="stream-migrate-message">%s</p><div id="stream-migrate-progress"><progress value="0" max="100"></progress> <strong>0&#37;</strong> <em></em> <button id="stream-migrate-actions-close" class="button button-secondary">%s</button><div class="clear"></div></div><p id="stream-migrate-actions"><button id="stream-start-migrate" class="button button-primary">%s</button> <button id="stream-migrate-reminder" class="button button-secondary">%s</button> <a href="#" id="stream-delete-records" class="delete">%s</a>',
			__( 'Migrate Stream Records', 'stream' ),
			sprintf( __( 'We found %s existing Stream records that need to be migrated to your Stream account.', 'stream' ), number_format( self::$record_count ) ),
			__( 'Close', 'stream' ),
			__( 'Start Migration Now', 'stream' ),
			__( 'Remind Me Later', 'stream' ),
			__( 'Delete Existing Records', 'stream' )
		);

		WP_Stream::notice( $notice, false );
	}

	/**
	 * Ajax callback for processing migrate actions
	 *
	 * Break down the total number of records found into reasonably-sized chunks
	 * and send each of those chunks to the Stream API
	 *
	 * Drops the legacy Stream data from the DB once the API has consumed everything
	 *
	 * @action wp_ajax_wp_stream_migrate_action
	 * @return void
	 */
	public static function process_migrate_action() {
		$action = wp_stream_filter_input( INPUT_POST, 'migrate_action' );
		$nonce  = wp_stream_filter_input( INPUT_POST, 'nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_stream_migrate-' . absint( get_current_blog_id() ) . absint( get_current_user_id() ) ) ) {
			return;
		}

		set_time_limit( 0 ); // Just in case, this could take a while for some

		if ( 'migrate' === $action ) {
			self::migrate_notification_rules();

			$records = self::get_records( self::$limit );

			if ( ! $records ) {
				// If all the records are gone, clean everything up
				self::drop_legacy_data();

				wp_send_json_success( __( 'Migration complete!', 'stream' ) );
			}

			$response = self::send_records( $records );

			if ( true === $response ) {
				// Delete the records that were just sent to the API successfully
				self::delete_records( self::$_records );

				wp_send_json_success( 'migrate' );
			} else {
				if ( isset( $response['body']['message'] ) && ! empty( $response['body']['message'] ) ) {
					$body    = json_decode( $response['body'], true );
					$message = $body['message'];
				} elseif ( isset( $response['response']['message'] ) && ! empty( $response['response']['message'] ) ) {
					$message = $response['response']['message'];
				} else {
					$message = __( 'An unknown error occurred during migration.', 'stream' );
				}

				wp_send_json_error( sprintf( __( '%s Please try again later or contact support.', 'stream' ), esc_html( $message ) ) );
			}
		}

		if ( 'delay' === $action ) {
			set_transient( self::MIGRATE_DELAY_TRANSIENT, "Don't nag me, bro", HOUR_IN_SECONDS * 3 );

			wp_send_json_success( __( "OK, we'll remind you again in a few hours.", 'stream' ) );
		}

		if ( 'delete' === $action ) {
			$success_message = __( 'All existing records have been deleted from the database.', 'stream' );

			if ( ! is_multisite() ) {
				// If this is a single-site install, force delete everything
				self::drop_legacy_data( true, true );

				wp_send_json_success( $success_message );
			} else {
				// If multisite, only delete records for this site - this will take longer
				$records = self::get_record_ids( self::$limit );

				if ( ! $records ) {
					// If all the records are gone, clean everything up
					self::drop_legacy_data();

					wp_send_json_success( $success_message );
				} else {
					self::delete_records( $records );

					wp_send_json_success( 'delete' );
				}
			}
		}

		die();
	}

	/**
	 * Migrate notification_rule records to the new custom post type
	 *
	 * @return void
	 */
	private static function migrate_notification_rules() {
		global $wpdb;

		// Blog ID is set to 0 on single site installs
		$blog_id = is_multisite() ? self::$blog_id : 0;

		$rules = $wpdb->get_results(
			$wpdb->prepare( "
				SELECT *
				FROM {$wpdb->base_prefix}stream
				WHERE site_id = %d
					AND blog_id = %d
					AND type = 'notification_rule'
				ORDER BY created DESC
				",
				self::$site_id,
				$blog_id
			),
			ARRAY_A
		);

		if ( empty( $rules ) ) {
			return;
		}

		foreach ( $rules as $rule => $data ) {
			$rule_post_args = array();
			$rule_post_meta = array();

			// Set args for the new rule post
			$rule_post_args['post_title']     = $rules[ $rule ]['summary'];
			$rule_post_args['post_type']      = WP_Stream_Notifications_Post_Type::POSTTYPE;
			$rule_post_args['post_status']    = ( 'active' === $rules[ $rule ]['visibility'] ) ? 'publish' : 'draft';
			$rule_post_args['post_date']      = get_date_from_gmt( $rules[ $rule ]['created'] );
			$rule_post_args['post_date_gmt']  = $rules[ $rule ]['created']; // May not work, known bug in WP, see workaround below
			$rule_post_args['comment_status'] = 'closed';
			$rule_post_args['ping_status']    = 'closed';

			// Get rule meta
			$stream_rule_meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->base_prefix}stream_meta WHERE record_id = %d", $rules[ $rule ]['ID'] ), ARRAY_A );

			// Prepare meta values for rule post meta
			foreach ( $stream_rule_meta as $meta => $value ) {
				$rule_post_meta[ $value['meta_key'] ] = maybe_unserialize( $value['meta_value'] );
			}

			// Get rule option, which is automatically unserialized
			$stream_rule_option = get_option( 'stream_notifications_' . absint( $rules[ $rule ]['ID'] ) );

			// Prepare option values for rule post meta
			$rule_post_meta['triggers'] = isset( $stream_rule_option['triggers'] ) ? $stream_rule_option['triggers'] : array();
			$rule_post_meta['groups']   = isset( $stream_rule_option['groups'] )   ? $stream_rule_option['groups']   : array();
			$rule_post_meta['alerts']   = isset( $stream_rule_option['alerts'] )   ? $stream_rule_option['alerts']   : array();

			// Insert rule as a new post
			$post_id = wp_insert_post( $rule_post_args );

			// Workaround to fix bug in wp_insert_post() not honoring the `post_date_gmt` arg
			// See: https://core.trac.wordpress.org/ticket/15946
			$wpdb->update( $wpdb->prefix . 'posts', array( 'post_date_gmt' => $rules[ $rule ]['created'] ), array( 'ID' => $post_id ), array( '%s' ), array( '%d' ) );

			// Save the rule post meta
			foreach ( $rule_post_meta as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}

			// Delete the old option
			delete_option( 'stream_notifications_' . absint( $rules[ $rule ]['ID'] ) );
		}

		// No need for chunks since there likely won't be more than a few dozen rules
		self::delete_records( $rules );
	}

	/**
	 * Send records to the API
	 *
	 * @param  array $records
	 *
	 * @return mixed True on success, the full response array on failure.
	 */
	private static function send_records( $records ) {
		if ( empty( $records ) || ! WP_Stream::$api->site_uuid ) {
			return false;
		}

		$url  = WP_Stream::$api->request_url( sprintf( '/sites/%s/records', urlencode( WP_Stream::$api->site_uuid ) ) );
		$args = array(
			'method'    => 'POST',
			'body'      => json_encode( array( 'records' => $records ) ),
			'sslverify' => true,
			'blocking'  => true,
			'headers'   => array(
				'Content-Type'        => 'application/json',
				'Accept-Version'      => WP_Stream::$api->api_version,
				'Stream-Site-API-Key' => WP_Stream::$api->api_key,
			),
		);

		$response = wp_remote_request( $url, $args );

		// Loose comparison needed
		if ( ! is_wp_error( $response ) && isset( $response['response']['code'] ) && 201 == $response['response']['code'] ) {
			return true;
		} else {
			return (array) $response;
		}
	}

	/**
	 * Get a chunk of records formatted for Stream API ingestion
	 *
	 * @param  int  $limit  The number of rows to query
	 *
	 * @return mixed  An array of record arrays, or FALSE if no records were found
	 */
	private static function get_records( $limit = null ) {
		$limit = is_int( $limit ) ? $limit : self::$limit;

		global $wpdb;

		$records = $wpdb->get_results(
			$wpdb->prepare( "
				SELECT s.*, sc.connector, sc.context, sc.action
				FROM {$wpdb->base_prefix}stream AS s, {$wpdb->base_prefix}stream_context AS sc
				WHERE s.site_id = %d
					AND s.blog_id = %d
					AND s.type = 'stream'
					AND sc.record_id = s.ID
				ORDER BY s.created DESC
				LIMIT %d
				",
				self::$site_id,
				self::$blog_id,
				$limit
			),
			ARRAY_A
		);

		if ( empty( $records ) ) {
			return false;
		}

		self::$_records = array();

		foreach ( $records as $record => $data ) {
			$stream_meta        = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->base_prefix}stream_meta WHERE record_id = %d", $records[ $record ]['ID'] ), ARRAY_A );
			$stream_meta_output = array();
			$author_meta_output = array();

			foreach ( $stream_meta as $key => $meta ) {

				if ( 'author_meta' === $meta['meta_key'] && ! empty( $meta['meta_value'] ) ) {
					$author_meta_output = maybe_unserialize( $meta['meta_value'] );

					unset( $stream_meta[ $key ] );

					continue;
				}

				// Unserialize meta first so we can then check for malformed serialized strings
				$stream_meta_output[ $meta['meta_key'] ] = maybe_unserialize( $meta['meta_value'] );

				// If any serialized data is still lingering in the meta value that means it's malformed and should be removed
				if (
					is_string( $stream_meta_output[ $meta['meta_key'] ] )
					&&
					1 === preg_match( '/(a|O) ?\x3a ?[0-9]+ ?\x3a ?\x7b/', $stream_meta_output[ $meta['meta_key'] ] )
				) {
					unset( $stream_meta_output[ $meta['meta_key'] ] );

					continue;
				}

				// All meta must be strings, so serialize any array meta values again
				$stream_meta_output[ $meta['meta_key'] ] = (string) maybe_serialize( $stream_meta_output[ $meta['meta_key'] ] );
			}

			// All author meta must be strings
			array_walk(
				$author_meta_output,
				function( &$v ) {
					$v = (string) $v;
				}
			);

			$records[ $record ]['stream_meta'] = $stream_meta_output;
			$records[ $record ]['author_meta'] = $author_meta_output;

			self::$_records[] = $records[ $record ];

			$records[ $record ]['created'] = wp_stream_get_iso_8601_extended_date( strtotime( $records[ $record ]['created'] ) );

			unset( $records[ $record ]['ID'] );
			unset( $records[ $record ]['parent'] );

			// Ensure required fields always exist
			$records[ $record ]['site_id']     = ! empty( $records[ $record ]['site_id'] )     ? $records[ $record ]['site_id']     : 1;
			$records[ $record ]['blog_id']     = ! empty( $records[ $record ]['blog_id'] )     ? $records[ $record ]['blog_id']     : 1;
			$records[ $record ]['object_id']   = ! empty( $records[ $record ]['object_id'] )   ? $records[ $record ]['object_id']   : 0;
			$records[ $record ]['author']      = ! empty( $records[ $record ]['author'] )      ? $records[ $record ]['author']      : 0;
			$records[ $record ]['author_role'] = ! empty( $records[ $record ]['author_role'] ) ? $records[ $record ]['author_role'] : '';
			$records[ $record ]['ip']          = ! empty( $records[ $record ]['ip'] )          ? $records[ $record ]['ip']          : '';
		}

		return $records;
	}

	/**
	 * Get a chunk of record IDs
	 *
	 * @param  int  $limit  The number of rows to query
	 *
	 * @return mixed  An array of record IDs, or FALSE if no records were found
	 */
	private static function get_record_ids( $limit = null ) {
		$limit = is_int( $limit ) ? $limit : self::$limit;

		global $wpdb;

		$records = $wpdb->get_col(
			$wpdb->prepare( "
				SELECT s.ID
				FROM {$wpdb->base_prefix}stream AS s
				WHERE s.site_id = %d
					AND s.blog_id = %d
					AND s.type = 'stream'
				LIMIT %d
				",
				self::$site_id,
				self::$blog_id,
				$limit
			)
		);

		if ( empty( $records ) ) {
			return false;
		}

		return $records;
	}

	/**
	 * Drop the legacy Stream records from the database for the current site/blog
	 *
	 * @param  array $records  An array of record arrays.
	 *
	 * @return void
	 */
	private static function delete_records( $records ) {
		if ( empty( $records ) ) {
			return;
		}

		global $wpdb;

		// Delete legacy rows from each Stream table for these records only
		foreach ( $records as $record ) {
			// Get the record ID from an array of records, or from an array of IDs
			if ( isset( $record['ID'] ) ) {
				$record_id = $record['ID'];
			} elseif ( is_numeric( $record ) ) {
				$record_id = $record;
			} else {
				$record_id = false;
			}

			if ( empty( $record_id ) ) {
				continue;
			}

			$wpdb->delete( $wpdb->base_prefix . 'stream', array( 'ID' => $record_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->base_prefix . 'stream_context', array( 'record_id' => $record_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->base_prefix . 'stream_meta', array( 'record_id' => $record_id ), array( '%d' ) );
		}
	}

	/**
	 * Drop the legacy Stream tables and options from the database
	 *
	 * @param bool $drop_tables  If true, attempt to drop the legacy Stream tables
	 * @param bool $force        If true, delete tables even if records still exist
	 *
	 * @return void
	 */
	private static function drop_legacy_data( $drop_tables = true, $force = false ) {
		global $wpdb;

		if ( $drop_tables ) {
			if ( is_multisite() ) {
				$stream_site_blog_pairs = $wpdb->get_results( "SELECT site_id, blog_id FROM {$wpdb->base_prefix}stream WHERE type = 'stream'", ARRAY_A );
				$stream_site_blog_pairs = array_unique( array_map( 'self::implode_key_value', $stream_site_blog_pairs ) );
				$wp_site_blog_pairs     = $wpdb->get_results( "SELECT site_id, blog_id FROM {$wpdb->base_prefix}blogs", ARRAY_A );
				$wp_site_blog_pairs     = array_unique( array_map( 'self::implode_key_value', $wp_site_blog_pairs ) );
				$records_exist          = ( array_intersect( $stream_site_blog_pairs, $wp_site_blog_pairs ) ) ? true : false;
			} else {
				$records_exist = $wpdb->get_var( "SELECT * FROM `{$wpdb->prefix}stream` LIMIT 1" );
			}

			// If records exist for other sites/blogs then don't proceed, unless we're force deleting or those sites/blogs have been deleted
			if ( $records_exist && ! $force ) {
				return;
			}

			// Drop legacy tables
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->base_prefix}stream, {$wpdb->base_prefix}stream_context, {$wpdb->base_prefix}stream_meta" );
		}

		// Delete legacy multisite options
		if ( is_multisite() ) {
			$blogs = wp_get_sites();

			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog['blog_id'] );
				delete_option( plugin_basename( WP_STREAM_DIR ) . '_db' ); // Deprecated option key
				delete_option( 'wp_stream_db' );
				delete_option( 'wp_stream_license' );
				delete_option( 'wp_stream_licensee' );
			}

			restore_current_blog();
		}

		// Delete legacy options
		delete_site_option( plugin_basename( WP_STREAM_DIR ) . '_db' ); // Deprecated option key
		delete_site_option( 'wp_stream_db' );
		delete_site_option( 'wp_stream_license' );
		delete_site_option( 'wp_stream_licensee' );

		// Delete legacy transients
		delete_transient( 'wp_stream_extensions_' );

		// Delete legacy cron event hooks
		wp_clear_scheduled_hook( 'stream_auto_purge' ); // Deprecated hook
		wp_clear_scheduled_hook( 'wp_stream_auto_purge' );
	}

	/**
	 * Callback to impode key/value pairs from an associative array into a specially-formatted string
	 *
	 * @param  array  $array  An associate array
	 *
	 * @return string $output
	 */
	public static function implode_key_value( $array ) {
		$output = implode( ', ',
			array_map(
				function ( $v, $k ) {
					return sprintf( '%s:%s', $k, $v );
				},
				$array,
				array_keys( $array )
			)
		);

			return $output;
	}

}
