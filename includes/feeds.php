<?php

require_once WP_STREAM_INC_DIR . 'admin.php';

class WP_Stream_Feeds {

	const FEED_QUERY_VAR         = 'stream';
	const FEED_KEY_QUERY_VAR     = 'key';
	const FEED_TYPE_QUERY_VAR    = 'type';
	const USER_FEED_KEY          = 'stream_user_feed_key';
	const GENERATE_KEY_QUERY_VAR = 'stream_new_user_feed_key';

	public static function load() {
		if ( ! isset( WP_Stream_Settings::$options['general_private_feeds'] ) || 1 != WP_Stream_Settings::$options['general_private_feeds'] ) {
			return;
		}

		add_action( 'show_user_profile', array( __CLASS__, 'save_user_feed_key' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'save_user_feed_key' ) );

		add_action( 'show_user_profile', array( __CLASS__, 'user_feed_key' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'user_feed_key' ) );

		add_feed( self::FEED_QUERY_VAR, array( __CLASS__, 'feed_template' ) );
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
		if ( get_user_meta( $user->ID, self::USER_FEED_KEY, true ) && ! isset( $_GET[self::GENERATE_KEY_QUERY_VAR] ) ) {
			return;
		}

		if ( ! isset( $_GET['wp_stream_nonce'] ) || ! wp_verify_nonce( $_GET['wp_stream_nonce'], 'wp_stream_generate_key' ) ) {
			return;
		}

		update_user_meta( $user->ID, self::USER_FEED_KEY, wp_generate_password( 32, false ) );
	}

	/**
	 * Output for Stream Feed URL field in user profiles.
	 *
	 * @action show_user_profile
	 * @action edit_user_profile
	 * @return string
	 */
	public static function user_feed_key( $user ) {
		if ( ! array_intersect( $user->roles, WP_Stream_Settings::$options['general_role_access'] ) ) {
			return;
		}

		$key = get_user_meta( $user->ID, self::USER_FEED_KEY, true );

		$pretty_permalinks = get_option( 'permalink_structure' );

		if ( empty( $pretty_permalinks ) ) {
			$link = add_query_arg( array( 'feed' => self::FEED_QUERY_VAR, self::FEED_KEY_QUERY_VAR => $key ), home_url( '/' ) );
		} else {
			$link = add_query_arg( array( self::FEED_KEY_QUERY_VAR => $key ), home_url( sprintf( '/feed/%s/', self::FEED_QUERY_VAR ) ) );
		}

		$nonce = wp_create_nonce( 'wp_stream_generate_key' );
		?>
		<table class="form-table">
			<tr>
				<th><label for="stream_feed_url"><?php esc_html_e( 'Stream Feeds Key', 'stream' ) ?></label></th>
				<td>
					<p>
						<code><?php echo esc_html( $key ) ?></code>
						<small><a href="<?php echo esc_url( add_query_arg( array( self::GENERATE_KEY_QUERY_VAR => true, 'wp_stream_nonce' => $nonce ) ) ) ?>"><?php esc_html_e( 'Generate new key', 'stream' ) ?></a></small>
					</p>
					<p class="description"><?php esc_html_e( 'This is your private key used for accessing feeds of Stream Records securely. You can change your key at any time by generating a new one using the link above.', 'stream' ) ?></p>
					<p>
						<a href="<?php echo esc_url( $link ) ?>" target="_blank"><?php echo esc_html_e( 'RSS Feed' ) ?></a>
						|
						<a href="<?php echo esc_url( add_query_arg( array( 'type' => 'json' ), $link ) ) ?>" target="_blank"><?php echo esc_html_e( 'JSON Feed' ) ?></a>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Output for Stream Records as a feed.
	 *
	 * @return xml
	 */
	public static function feed_template() {
		$die_title   = esc_html__( 'Access Denied', 'stream' );
		$die_message = '<h1>' . $die_title .'</h1><p>' . esc_html__( 'You don\'t have permission to view this feed, please contact your site Administrator.', 'stream' ) . '</p>';

		if ( ! isset( $_GET[self::FEED_KEY_QUERY_VAR] ) || empty( $_GET[self::FEED_KEY_QUERY_VAR] ) ) {
			wp_die( $die_message, $die_title );
		}

		$args = array(
			'meta_key'   => self::USER_FEED_KEY,
			'meta_value' => $_GET[self::FEED_KEY_QUERY_VAR],
			'number'     => 1,
		);
		$user = get_users( $args );

		$roles = isset( $user[0]->roles ) ? (array) $user[0]->roles : array();

		if ( ! $roles || ! array_intersect( $roles, WP_Stream_Settings::$options['general_role_access'] ) ) {
			wp_die( $die_message, $die_title );
		}

		$args = array(
			'records_per_page' => isset( $_GET['records_per_page'] ) ? (int) $_GET['records_per_page'] : get_option( 'posts_per_rss' ),
			'search'           => isset( $_GET['search'] ) ? (string) $_GET['search'] : null,
			'object_id'        => isset( $_GET['object_id'] ) ? (int) $_GET['object_id'] : null,
			'ip'               => isset( $_GET['ip'] ) ? (string) $_GET['ip'] : null,
			'author'           => isset( $_GET['author'] ) ? (int) $_GET['author'] : null,
			'date'             => isset( $_GET['date'] ) ? (string) $_GET['date'] : null,
			'date_from'        => isset( $_GET['date_from'] ) ? (string) $_GET['date_from'] : null,
			'date_to'          => isset( $_GET['date_to'] ) ? (string) $_GET['date_to'] : null,
			'record_parent'    => isset( $_GET['record_parent'] ) ? (int) $_GET['record_parent'] : null,
			'order'            => isset( $_GET['order'] ) ? (string) $_GET['order'] : 'desc',
			'orderby'          => isset( $_GET['orderby'] ) ? (string) $_GET['orderby'] : 'ID',
			'fields'           => isset( $_GET['fields'] ) ? (string) $_GET['fields'] : '',
		);
		$records = stream_query( $args );

		$latest_record = isset( $records[0]->created ) ? $records[0]->created : null;

		$records_admin_url = add_query_arg( array( 'page' => WP_Stream_Admin::RECORDS_PAGE_SLUG ), admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE ) );

		if ( 'json' === filter_input( INPUT_GET, self::FEED_TYPE_QUERY_VAR ) ) {
			if ( version_compare( PHP_VERSION, '5.4', '>=' ) ) {
				echo json_encode( $records, JSON_PRETTY_PRINT );
			} else {
				echo json_encode( $records );
			}
		} else {

			header( 'Content-Type: ' . feed_content_type( 'rss-http' ) . '; charset=' . get_option( 'blog_charset' ), true );

			echo '<?xml version="1.0" encoding="' . get_option( 'blog_charset' ) . '"?>';
			?>

			<rss version="2.0"
				xmlns:content="http://purl.org/rss/1.0/modules/content/"
				xmlns:wfw="http://wellformedweb.org/CommentAPI/"
				xmlns:dc="http://purl.org/dc/elements/1.1/"
				xmlns:atom="http://www.w3.org/2005/Atom"
				xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
				xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
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
					<?php do_action( 'rss2_head' ) ?>
					<?php foreach ( $records as $record ) : ?>
						<?php
						$record_link  = add_query_arg( array( 'record__in' => (int) $record->ID ), $records_admin_url );
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
