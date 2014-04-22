<?php

require_once WP_STREAM_INC_DIR . 'admin.php';

class WP_Stream_Feeds {

	const FEED_QUERY_VAR         = 'stream';
	const FEED_NETWORK_QUERY_VAR = 'network-stream';
	const FEED_TYPE_QUERY_VAR    = 'type';
	const USER_FEED_KEY          = 'stream_user_feed_key';
	const GENERATE_KEY_QUERY_VAR = 'stream_new_user_feed_key';

	public static $is_network_feed = false;

	public static function load() {
		if ( is_network_admin() ) {
			self::$is_network_feed = true;
		}

		if ( ! is_admin() ) {
			$feed_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
			if ( self::FEED_NETWORK_QUERY_VAR === basename( $feed_path ) ) {
				self::$is_network_feed = true;
			}
		}

		if ( ! self::$is_network_feed ) {
			if ( ! isset( WP_Stream_Settings::$options['general_private_feeds'] ) || ! WP_Stream_Settings::$options['general_private_feeds'] ) {
				return;
			}
		}

		add_action( 'show_user_profile', array( __CLASS__, 'save_user_feed_key' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'save_user_feed_key' ) );

		add_action( 'show_user_profile', array( __CLASS__, 'user_feed_key' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'user_feed_key' ) );

		// Generate new Stream Feed Key
		add_action( 'wp_ajax_wp_stream_feed_key_generate', array( __CLASS__, 'generate_user_feed_key' ) );

		add_feed( self::FEED_QUERY_VAR, array( __CLASS__, 'feed_template' ) );
		add_feed( self::FEED_NETWORK_QUERY_VAR, array( __CLASS__, 'feed_template' ) );
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
			update_user_meta( $user_id, self::USER_FEED_KEY, $feed_key );

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
	 * @return void
	 */
	public static function save_user_feed_key( $user ) {
		$generate_key = isset( $_GET[ self::GENERATE_KEY_QUERY_VAR ] );
		$verify_nonce = isset( $_GET['wp_stream_nonce'] ) && wp_verify_nonce( $_GET['wp_stream_nonce'], 'wp_stream_generate_key' );

		if ( ! $generate_key && get_user_meta( $user->ID, self::USER_FEED_KEY, true ) ) {
			return;
		}

		if ( $generate_key && ! $verify_nonce ) {
			return;
		}

		$feed_key = wp_generate_password( 32, false );

		update_user_meta( $user->ID, self::USER_FEED_KEY, $feed_key );
	}

	/**
	 * Output for Stream Feed URL field in user profiles.
	 *
	 * @action show_user_profile
	 * @action edit_user_profile
	 * @return string
	 */
	public static function user_feed_key( $user ) {
		if ( ! is_network_admin() && ! array_intersect( $user->roles, WP_Stream_Settings::$options['general_role_access'] ) ) {
			return;
		}

		if ( is_network_admin() && ! is_super_admin( $user->ID ) ) {
			return;
		}

		$key  = get_user_meta( $user->ID, self::USER_FEED_KEY, true );
		$link = self::get_user_feed_url( $key );

		$nonce = wp_create_nonce( 'wp_stream_generate_key' );
		?>
		<table class="form-table">
			<tr>
				<th><label for="<?php echo esc_attr( self::USER_FEED_KEY ) ?>"><?php esc_html_e( 'Stream Feeds Key', 'stream' ) ?></label></th>
				<td>
					<p class="wp-stream-feeds-key">
						<?php wp_nonce_field( 'wp_stream_generate_key', 'wp_stream_generate_key_nonce' ) ?>
						<input type="text" name="<?php echo esc_attr( self::USER_FEED_KEY ) ?>" id="<?php echo esc_attr( self::USER_FEED_KEY ) ?>" class="regular-text code" value="<?php echo esc_attr( $key ) ?>" readonly>
						<small><a href="<?php echo esc_url( add_query_arg( array( self::GENERATE_KEY_QUERY_VAR => true, 'wp_stream_nonce' => $nonce ) ) ) ?>" id="<?php echo esc_attr( self::USER_FEED_KEY ) ?>_generate"><?php esc_html_e( 'Generate new key', 'stream' ) ?></a></small>
						<span class="spinner" style="display: none;"></span>
					</p>
					<p class="description"><?php esc_html_e( 'This is your private key used for accessing feeds of Stream Records securely. You can change your key at any time by generating a new one using the link above.', 'stream' ) ?></p>
					<p class="wp-stream-feeds-links">
						<a href="<?php echo esc_url( add_query_arg( array( 'type' => 'xml' ), $link )  ) ?>" class="rss-feed" target="_blank"><?php echo esc_html_e( 'RSS Feed' ) ?></a>
						|
						<a href="<?php echo esc_url( add_query_arg( array( 'type' => 'json' ), $link ) ) ?>" class="json-feed" target="_blank"><?php echo esc_html_e( 'JSON Feed' ) ?></a>
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
		$query_var         = is_network_admin() ? self::FEED_NETWORK_QUERY_VAR : self::FEED_QUERY_VAR;

		if ( empty( $pretty_permalinks ) ) {
			$link = add_query_arg(
				array(
					'feed'     => $query_var,
					$query_var => $key,
				),
				home_url( '/' )
			);
		} else {
			$link = add_query_arg(
				array(
					$query_var => $key,
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
		$die_message = '<h1>' . $die_title .'</h1><p>' . esc_html__( 'You don\'t have permission to view this feed, please contact your site Administrator.', 'stream' ) . '</p>';

		if ( ! isset( $_GET[self::FEED_QUERY_VAR] ) || empty( $_GET[self::FEED_QUERY_VAR] ) ) {
			wp_die( $die_message, $die_title );
		}

		$args = array(
			'meta_key'   => self::USER_FEED_KEY,
			'meta_value' => $_GET[self::FEED_QUERY_VAR],
			'number'     => 1,
		);
		$user = get_users( $args );

		if ( ! is_super_admin( $user[0]->ID ) ) {
			$roles = isset( $user[0]->roles ) ? (array) $user[0]->roles : array();

			if ( self::$is_network_feed ) {
				wp_die( $die_message, $die_title );
			}

			if ( ! $roles || ! array_intersect( $roles, WP_Stream_Settings::$options['general_role_access'] ) ) {
				wp_die( $die_message, $die_title );
			}
		}

		$blog_id = self::$is_network_feed ? null : get_current_blog_id();

		$args = array(
			'blog_id'          => $blog_id,
			'records_per_page' => wp_stream_filter_input( INPUT_GET, 'records_per_page', FILTER_SANITIZE_NUMBER_INT, array( 'options' => array( 'default' => get_option( 'posts_per_rss' ) ) ) ),
			'search'           => wp_stream_filter_input( INPUT_GET, 'search' ),
			'object_id'        => wp_stream_filter_input( INPUT_GET, 'object_id', FILTER_SANITIZE_NUMBER_INT ),
			'ip'               => wp_stream_filter_input( INPUT_GET, 'ip', FILTER_VALIDATE_IP ),
			'author'           => wp_stream_filter_input( INPUT_GET, 'author', FILTER_SANITIZE_NUMBER_INT ),
			'author_role'      => wp_stream_filter_input( INPUT_GET, 'author_role' ),
			'date'             => wp_stream_filter_input( INPUT_GET, 'date' ),
			'date_from'        => wp_stream_filter_input( INPUT_GET, 'date_from' ),
			'date_to'          => wp_stream_filter_input( INPUT_GET, 'date_to' ),
			'record__in'       => wp_stream_filter_input( INPUT_GET, 'record__in', FILTER_SANITIZE_NUMBER_INT ),
			'record_parent'    => wp_stream_filter_input( INPUT_GET, 'record_parent', FILTER_SANITIZE_NUMBER_INT ),
			'order'            => wp_stream_filter_input( INPUT_GET, 'order', FILTER_DEFAULT, array( 'options' => array( 'default' => 'desc' ) ) ),
			'orderby'          => wp_stream_filter_input( INPUT_GET, 'orderby', FILTER_DEFAULT, array( 'options' => array( 'default' => 'ID' ) ) ),
			'fields'           => wp_stream_filter_input( INPUT_GET, 'fields', FILTER_DEFAULT, array( 'options' => array( 'default' => 'with-meta' ) ) ),
		);

		$records = wp_stream_query( $args );

		$latest_record = isset( $records[0]->created ) ? $records[0]->created : null;

		$records_admin_url = add_query_arg(
			array(
				'page' => WP_Stream_Admin::RECORDS_PAGE_SLUG,
			),
			admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
		);

		if ( 'json' === wp_stream_filter_input( INPUT_GET, self::FEED_TYPE_QUERY_VAR ) ) {
			if ( version_compare( PHP_VERSION, '5.4', '>=' ) ) {
				echo json_encode( $records, JSON_PRETTY_PRINT );
			} else {
				echo json_encode( $records );
			}
		} else {

			header( 'Content-Type: ' . feed_content_type( 'rss-http' ) . '; charset=' . get_option( 'blog_charset' ), true );

			printf( '<?xml version="1.0" encoding="%s"?>', esc_attr( get_option( 'blog_charset' ) ) );
			?>

			<rss version="2.0"
				xmlns:content="http://purl.org/rss/1.0/modules/content/"
				xmlns:wfw="http://wellformedweb.org/CommentAPI/"
				xmlns:dc="http://purl.org/dc/elements/1.1/"
				xmlns:atom="http://www.w3.org/2005/Atom"
				xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
				xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
				<?php
				/**
				 * Action fires during RSS xmls printing
				 */
				?>
				<?php do_action( 'rss2_ns' ) ?>
			>
				<channel>
					<title><?php bloginfo_rss( 'name' ) ?> - <?php esc_html_e( 'Stream Feed', 'stream' ) ?></title>
					<atom:link href="<?php self_link() ?>" rel="self" type="application/rss+xml" />
					<link><?php echo esc_url( $records_admin_url ) ?></link>
					<description><?php bloginfo_rss( 'description' ) ?></description>
					<lastBuildDate><?php echo esc_html( mysql2date( 'r', $latest_record, false ) ) ?></lastBuildDate>
					<language><?php bloginfo_rss( 'language' ) ?></language>
					<sy:updatePeriod><?php echo esc_html( 'hourly' ) ?></sy:updatePeriod>
					<sy:updateFrequency><?php echo absint( 1 ) ?></sy:updateFrequency>
					<?php
					/**
					 * Action fires during RSS head
					 */
					?>
					<?php do_action( 'rss2_head' ) ?>
					<?php foreach ( $records as $record ) : ?>
						<?php
						$record_link  = add_query_arg(
							array(
								'record__in' => (int) $record->ID,
							),
							$records_admin_url
						);
						$author       = get_userdata( $record->author );
						$display_name = isset( $author->display_name ) ? $author->display_name : 'N/A';
						?>
						<item>
							<title><![CDATA[ <?php echo trim( $record->summary ) // xss ok ?> ]]></title>
							<pubDate><?php echo esc_html( mysql2date( 'r', $record->created, false ) ) ?></pubDate>
							<dc:creator><?php echo esc_html( $display_name ) ?></dc:creator>
							<category domain="connector"><![CDATA[ <?php echo esc_html( $record->connector ) ?> ]]></category>
							<category domain="context"><![CDATA[ <?php echo esc_html( $record->context ) ?> ]]></category>
							<category domain="action"><![CDATA[ <?php echo esc_html( $record->action ) ?> ]]></category>
							<category domain="ip"><?php echo esc_html( $record->ip ) ?></category>
							<guid isPermaLink="false"><?php echo esc_url( $record_link ) ?></guid>
							<link><?php echo esc_url( $record_link ) ?></link>
							<?php
							/**
							 * Action fires during RSS item
							 */
							?>
							<?php do_action( 'rss2_item' ) ?>
						</item>
					<?php endforeach; ?>
				</channel>
			</rss>
			<?php
			exit;
		}
	}

}
