<?php

class WP_Stream_Feeds {

	const FEED_QUERY_VAR         = 'stream';
	const FEED_KEY_QUERY_VAR     = 'key';
	const FEED_TYPE_QUERY_VAR    = 'type';
	const USER_FEED_OPTION_KEY   = 'stream_user_feed_key';
	const GENERATE_KEY_QUERY_VAR = 'stream_new_user_feed_key';

	public static function load() {
		if ( ! is_admin() ) {
			$feed_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		}

		if ( ! isset( WP_Stream_Settings::$options['general_private_feeds'] ) || ! WP_Stream_Settings::$options['general_private_feeds'] ) {
			return;
		}

		add_action( 'show_user_profile', array( __CLASS__, 'save_user_feed_key' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'save_user_feed_key' ) );

		add_action( 'show_user_profile', array( __CLASS__, 'user_feed_key' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'user_feed_key' ) );

		// Generate new Stream Feed Key
		add_action( 'wp_ajax_wp_stream_feed_key_generate', array( __CLASS__, 'generate_user_feed_key' ) );

		add_feed( self::FEED_QUERY_VAR, array( __CLASS__, 'feed_template' ) );
	}

	/**
	 * Sends a new user key when the
	 *
	 * @return void/json
	 */
	public static function generate_user_feed_key() {
		check_ajax_referer( 'wp_stream_generate_key', 'nonce' );

		$user_id = wp_stream_filter_input( INPUT_POST, 'user', FILTER_SANITIZE_NUMBER_INT );

		if ( $user_id ) {
			$feed_key = wp_generate_password( 32, false );
			update_user_meta( $user_id, self::USER_FEED_OPTION_KEY, $feed_key );

			$link      = self::get_user_feed_url( $feed_key );
			$xml_feed  = add_query_arg( array( 'type' => 'json' ), $link );
			$json_feed = add_query_arg( array( 'type' => 'json' ), $link );

			wp_send_json_success(
				array(
					'message'   => 'User feed key successfully generated.',
					'feed_key'  => $feed_key,
					'xml_feed'  => $xml_feed,
					'json_feed' => $json_feed,
				)
			);
		} else {
			wp_send_json_error( 'User ID error' );
		}
	}

	/**
	 * Generates and saves a unique key as user meta if the user does not
	 * already have a key, or has requested a new one.
	 *
	 * @action show_user_profile
	 * @action edit_user_profile
	 * @param WP_User $user
	 * @return void
	 */
	public static function save_user_feed_key( $user ) {
		$generate_key = wp_stream_filter_input( INPUT_GET, self::GENERATE_KEY_QUERY_VAR );
		$nonce        = wp_stream_filter_input( INPUT_GET, 'wp_stream_nonce' );

		if ( ! $generate_key && get_user_meta( $user->ID, self::USER_FEED_OPTION_KEY, true ) ) {
			return;
		}

		if ( $generate_key && ! wp_verify_nonce( $nonce, 'wp_stream_generate_key' ) ) {
			return;
		}

		$feed_key = wp_generate_password( 32, false );

		update_user_meta( $user->ID, self::USER_FEED_OPTION_KEY, $feed_key );
	}

	/**
	 * Output for Stream Feed URL field in user profiles.
	 *
	 * @action show_user_profile
	 * @action edit_user_profile
	 * @param WP_User $user
	 * @return string
	 */
	public static function user_feed_key( $user ) {
		if ( ! array_intersect( $user->roles, WP_Stream_Settings::$options['general_role_access'] ) ) {
			return;
		}

		$key  = get_user_meta( $user->ID, self::USER_FEED_OPTION_KEY, true );
		$link = self::get_user_feed_url( $key );

		$nonce = wp_create_nonce( 'wp_stream_generate_key' );
		?>
		<table class="form-table">
			<tr>
				<th><label for="<?php echo esc_attr( self::USER_FEED_OPTION_KEY ) ?>"><?php esc_html_e( 'Stream Feeds Key', 'stream' ) ?></label></th>
				<td>
					<p class="wp-stream-feeds-key">
						<?php wp_nonce_field( 'wp_stream_generate_key', 'wp_stream_generate_key_nonce' ) ?>
						<input type="text" name="<?php echo esc_attr( self::USER_FEED_OPTION_KEY ) ?>" id="<?php echo esc_attr( self::USER_FEED_OPTION_KEY ) ?>" class="regular-text code" value="<?php echo esc_attr( $key ) ?>" readonly>
						<small><a href="<?php echo esc_url( add_query_arg( array( self::GENERATE_KEY_QUERY_VAR => true, 'wp_stream_nonce' => $nonce ) ) ) ?>" id="<?php echo esc_attr( self::USER_FEED_OPTION_KEY ) ?>_generate"><?php esc_html_e( 'Generate new key', 'stream' ) ?></a></small>
						<span class="spinner" style="display: none;"></span>
					</p>
					<p class="description"><?php esc_html_e( 'This is your private key used for accessing feeds of Stream Records securely. You can change your key at any time by generating a new one using the link above.', 'stream' ) ?></p>
					<p class="wp-stream-feeds-links">
						<a href="<?php echo esc_url( add_query_arg( array( 'type' => 'rss' ), $link ) ) ?>" class="rss-feed" target="_blank"><?php echo esc_html_e( 'RSS Feed', 'stream' ) ?></a>
						|
						<a href="<?php echo esc_url( add_query_arg( array( 'type' => 'atom' ), $link ) ) ?>" class="atom-feed" target="_blank"><?php echo esc_html_e( 'ATOM Feed', 'stream' ) ?></a>
						|
						<a href="<?php echo esc_url( add_query_arg( array( 'type' => 'json' ), $link ) ) ?>" class="json-feed" target="_blank"><?php echo esc_html_e( 'JSON Feed', 'stream' ) ?></a>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Return Stream Feed URL
	 *
	 * @return string
	 */
	public static function get_user_feed_url( $key ) {
		$pretty_permalinks = get_option( 'permalink_structure' );
		$query_var         = self::FEED_QUERY_VAR;

		if ( empty( $pretty_permalinks ) ) {
			$link = add_query_arg(
				array(
					'feed'                   => $query_var,
					self::FEED_KEY_QUERY_VAR => $key,
				),
				home_url( '/' )
			);
		} else {
			$link = add_query_arg(
				array(
					self::FEED_KEY_QUERY_VAR => $key,
				),
				home_url(
					sprintf(
						'/feed/%s/',
						$query_var
					)
				)
			);
		}

		return $link;
	}

	/**
	 * Output for Stream Records as a feed.
	 *
	 * @return xml
	 */
	public static function feed_template() {
		$die_title   = esc_html__( 'Access Denied', 'stream' );
		$die_message = sprintf( '<h1>%s</h1><p>%s</p>', $die_title, esc_html__( "You don't have permission to view this feed, please contact your site Administrator.", 'stream' ) );
		$query_var   = self::FEED_QUERY_VAR;

		$args = array(
			'meta_key'   => self::USER_FEED_OPTION_KEY,
			'meta_value' => wp_stream_filter_input( INPUT_GET, self::FEED_KEY_QUERY_VAR ),
			'number'     => 1,
		);
		$user = get_users( $args );

		if ( empty( $user ) ) {
			wp_die( $die_message, $die_title );
		}

		if ( ! is_super_admin( $user[0]->ID ) ) {
			$roles = isset( $user[0]->roles ) ? (array) $user[0]->roles : array();

			if ( ! $roles || ! array_intersect( $roles, WP_Stream_Settings::$options['general_role_access'] ) ) {
				wp_die( $die_message, $die_title );
			}
		}

		$args = array(
			'search'           => wp_stream_filter_input( INPUT_GET, 'search' ),
			'record_after'     => wp_stream_filter_input( INPUT_GET, 'record_after' ), // Deprecated, use date_after instead
			'date'             => wp_stream_filter_input( INPUT_GET, 'date' ),
			'date_from'        => wp_stream_filter_input( INPUT_GET, 'date_from' ),
			'date_to'          => wp_stream_filter_input( INPUT_GET, 'date_to' ),
			'date_after'       => wp_stream_filter_input( INPUT_GET, 'date_after' ),
			'date_before'      => wp_stream_filter_input( INPUT_GET, 'date_before' ),
			'record'           => wp_stream_filter_input( INPUT_GET, 'record' ),
			'record__in'       => wp_stream_filter_input( INPUT_GET, 'record__in' ),
			'record__not_in'   => wp_stream_filter_input( INPUT_GET, 'record__not_in' ),
			'records_per_page' => wp_stream_filter_input( INPUT_GET, 'records_per_page', FILTER_SANITIZE_NUMBER_INT ),
			'order'            => wp_stream_filter_input( INPUT_GET, 'order' ),
			'orderby'          => wp_stream_filter_input( INPUT_GET, 'orderby' ),
			'meta'             => wp_stream_filter_input( INPUT_GET, 'meta' ),
			'fields'           => wp_stream_filter_input( INPUT_GET, 'fields' ),
		);

		$properties = array(
			'author',
			'author_role',
			'ip',
			'object_id',
			'connector',
			'context',
			'action',
		);

		foreach ( $properties as $property ) {
			$args[ $property ]             = wp_stream_filter_input( INPUT_GET, $property );
			$args[ "{$property}__in" ]     = wp_stream_filter_input( INPUT_GET, "{$property}__in" );
			$args[ "{$property}__not_in" ] = wp_stream_filter_input( INPUT_GET, "{$property}__not_in" );
		}

		$records = wp_stream_query( $args );

		$latest_record = isset( $records[0]->created ) ? $records[0]->created : null;

		$records_admin_url = add_query_arg(
			array(
				'page' => WP_Stream_Admin::RECORDS_PAGE_SLUG,
			),
			admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
		);

		$latest_link = null;

		if ( isset( $records[0]->ID ) ) {
			$latest_link = add_query_arg(
				array(
					'record__in' => $records[0]->ID,
				),
				$records_admin_url
			);
		}

		$domain = parse_url( $records_admin_url, PHP_URL_HOST );
		$format = wp_stream_filter_input( INPUT_GET, self::FEED_TYPE_QUERY_VAR );

		if ( 'atom' === $format ) {
			require_once WP_STREAM_INC_DIR . 'feeds/atom.php';
		} elseif ( 'json' === $format ) {
			require_once WP_STREAM_INC_DIR . 'feeds/json.php';
		} else {
			require_once WP_STREAM_INC_DIR . 'feeds/rss-2.0.php';
		}

		exit;
	}

}
