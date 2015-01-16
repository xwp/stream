<?php
/**
 * Stream commands for WP-CLI
 *
 * @since 2.0.3
 * @see https://github.com/wp-cli/wp-cli
 */
class WP_Stream_WP_CLI_Command extends WP_CLI_Command {

	/**
	 * Subcommand to query a set of Stream records
	 *
	 * You may use any associative argument(s) available in the
	 * WP_Stream_Query::instance()->query() method.
	 *
	 * Examples:
	 * wp stream query --author_role__not_in=administrator --date_after=2015-01-01T12:00:00
	 * wp stream query --author=1 --action=login --records_per_page=50 --fields=created
	 *
	 * @see WP_Stream_Query
	 * @subcommand query
	 */
	public function query( $args, $assoc_args ) {
		self::connection();

		$start      = microtime( true );
		$query_args = array();

		foreach ( $assoc_args as $key => $value ) {
			$query_args[ $key ] = $value;
		}

		if ( empty( $query_args['fields'] ) ) {
			$defaults = array( 'created', 'ip', 'author', 'author_meta.user_login', 'author_role', 'summary' );

			/**
			 * Filter for default fields when arg is not provided
			 *
			 * @return array $defaults
			 */
			$defaults = apply_filters( 'wp_stream_wp_cli_default_fields', (array) $defaults );

			$query_args['fields'] = implode( ',', array_map( 'trim', $defaults ) );
		}

		$records = wp_stream_query( $query_args );

		if ( empty( $records ) ) {
			WP_CLI::success( __( 'No results found.', 'stream' ) );
			return;
		}

		$fields = array_map( 'trim', explode( ',', $query_args['fields'] ) );

		foreach ( $records as $record ) {
			$output = '';

			foreach ( $fields as $field ) {
				$values = wp_list_pluck( $records, $field );

				array_walk( $values, function( &$value ) {
					$value = is_array( $value ) ? $value[0] : $value;
				});

				$longest = max( array_map( 'strlen', $values ) );
				$value   = is_array( $record->$field ) ? implode( ', ', $record->$field ) : $record->$field;
				$output .= $value;
				$diff    = absint( $longest - strlen( $value ) );

				for ( $i = 0; $i < $diff; $i++ ) {
					$output .= ' ';
				}

				$output .= '   ';
			}

			WP_CLI::line( trim( $output ) );
		}

		$found   = count( $records );
		$stop    = microtime( true ) - $start;
		$message = sprintf( _n( '1 result found in %s seconds.', '%s results found in %s seconds.', $found, 'stream' ), number_format( $found ), number_format( $stop, 4 ) );

		WP_CLI::success( $message );
	}

	/**
	 * Subcommand to retreive details about your Stream account
	 *
	 * @subcommand account
	 */
	public function account() {
		self::connection();

		$site       = WP_Stream::$api->get_site();
		$plan       = isset( $site->plan->type ) ? $site->plan->type : 'unknown';
		$plan_label = ( 'free' === $plan ) ? __( 'Free', 'stream' ) : ( 'pro_monthly' === $plan ) ? __( 'Pro', 'stream' ) : ucwords( $plan );

		WP_CLI::line( sprintf( __( 'Plan: %s', 'stream' ), $plan_label ) );

		if ( 'free' !== $plan && 'pro_monthly' !== $plan ) {
			return;
		}

		$retention   = isset( $site->plan->retention ) ? absint( $site->plan->retention ) : 0;
		$retention   = ( 0 !== $retention ) ? sprintf( _n( '1 Day', '%s Days', $retention, 'stream' ), $retention ) : __( 'Unlimited', 'stream' );
		$amount      = ! empty( $site->plan->amount ) ? $site->plan->amount : 0;
		$expiry      = ! empty( $site->expiry->date ) ? $site->expiry->date : __( 'N/A', 'stream' );
		$created     = isset( $site->created ) ? $site->created : null;
		$date_format = get_option( 'date_format', 'F j, Y' );
		$site_uuid   = get_option( 'wp_stream_site_uuid', null );
		$api_key     = get_option( 'wp_stream_site_api_key', null );

		WP_CLI::line( sprintf( __( 'Retention: %s', 'stream' ), $retention ) );

		if ( 'free' !== $plan ) {
			WP_CLI::line( sprintf( __( 'Next Billing: $%1$s on %2$s', 'stream' ), $amount, $expiry ) );
		}

		if ( $created ) {
			WP_CLI::line( sprintf( __( 'Created: %s', 'stream' ), date_i18n( $date_format, strtotime( $created ) ) ) );
		}

		if ( $site_uuid ) {
			WP_CLI::line( sprintf( __( 'Site ID: %s', 'stream' ), $site_uuid ) );
		}

		if ( $api_key ) {
			WP_CLI::line( sprintf( __( 'API Key: %s', 'stream' ), $api_key ) );
		}
	}

	/**
	 * Subcommand for n00bs
	 *
	 * @subcommand help
	 */
	public function help() {
		WP_CLI::line( __( 'Welcome to Stream for WP-CLI!', 'stream' ) );
		WP_CLI::line( __( 'Available commands: query, account', 'stream' ) );
	}

	/**
	 * Checks for a Stream connection and displays an error or success message
	 *
	 * @return void
	 */
	private static function connection() {
		WP_CLI::line( __( 'Establishing secure connection with Stream...', 'stream' ) );

		$query = wp_stream_query( array( 'records_per_page' => 1, 'fields' => 'created' ) );

		if ( ! $query ) {
			WP_CLI::error( __( 'SITE IS DISCONNECTED', 'stream' ) );
		} else {
			WP_CLI::success( __( 'SITE IS CONNECTED', 'stream' ) );
		}
	}

}
