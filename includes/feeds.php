<?php

require_once WP_STREAM_INC_DIR . 'admin.php';

class WP_Stream_Feeds {

	const FEED_QUERY_VAR      = 'stream_feed';
	const FEED_TYPE_QUERY_VAR = 'type';
	const USER_FEED_KEY       = 'stream_user_feed_key';

	public static function load() {

		add_filter( 'query_vars', array( __CLASS__, '_add_query_var' ) );

		add_action( 'template_redirect', array( __CLASS__, '_feed_template' ) );

		add_action( 'show_user_profile', array( __CLASS__, '_save_user_feed_key' ) );
		add_action( 'edit_user_profile', array( __CLASS__, '_save_user_feed_key' ) );

		add_action( 'show_user_profile', array( __CLASS__, '_user_feed_key' ) );
		add_action( 'edit_user_profile', array( __CLASS__, '_user_feed_key' ) );
	}

	/**
	 * Adding the query var that will be used to trigger the feed.
	 *
	 * @return $vars
	 * @filter query_vars
	 */
	public static function _add_query_var( $query_vars ) {
		$query_vars[] = self::FEED_QUERY_VAR;
		$query_vars[] = self::FEED_TYPE_QUERY_VAR;
		return $query_vars;
	}

	public static function _save_user_feed_key( $user ) {
		if ( $key = get_user_meta( $user->ID, self::USER_FEED_KEY, true ) ) {
			return;
		}
		update_user_meta( $user->ID, self::USER_FEED_KEY, wp_generate_password( 32, false ) );
	}

	/**
	 * Add field for RSS feed key to user profiles.
	 *
	 * @action show_user_profile
	 * @action edit_user_profile
	 * @return html
	 */
	public static function _user_feed_key( $user ) {
		if ( ! array_intersect( $user->roles, WP_Stream_Settings::$options['general_role_access'] ) ) {
			return;
		}
		$key  = get_user_meta( $user->ID, self::USER_FEED_KEY, true );
		$link = add_query_arg( array( self::FEED_QUERY_VAR => $key ), home_url() );
		?>
		<table class="form-table">
			<tr>
				<th><label for="stream_feed_url"><?php esc_html_e( 'Stream Feed URL', 'stream' ) ?></label></th>
				<td>
					<a href="<?php echo esc_url( $link ) ?>" target="_blank"><?php echo esc_url( $link ) ?></a>
					<p class="description"><?php esc_html_e( 'This is a private URL for you to access your Stream activity.', 'stream' ) ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function _feed_template() {
		if ( ! get_query_var( self::FEED_QUERY_VAR ) ) {
			return;
		}

		$args = array(
			'meta_key'   => self::USER_FEED_KEY,
			'meta_value' => get_query_var( self::FEED_QUERY_VAR ),
			'number'     => 1,
		);
		$user = get_users( $args );

		if ( ! isset( $user[0]->roles ) || ! array_intersect( $user[0]->roles, WP_Stream_Settings::$options['general_role_access'] ) ) {
			return;
		}

		if ( ! get_query_var( self::FEED_TYPE_QUERY_VAR ) || 'rss' === get_query_var( self::FEED_TYPE_QUERY_VAR ) ) {

			header( 'Content-Type: ' . feed_content_type( 'rss-http' ) . '; charset=' . get_option( 'blog_charset' ), true );

			$records = stream_query( array( 'records_per_page' => get_option( 'posts_per_rss' ) ) );

			$latest_record = isset( $records[0]->created ) ? $records[0]->created : null;

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
					<link><?php bloginfo_rss( 'url' ) ?></link>
					<description><?php bloginfo_rss( 'description' ) ?></description>
					<lastBuildDate><?php echo esc_html( mysql2date( 'r', $latest_record, false ) ) ?></lastBuildDate>
					<language><?php bloginfo_rss( 'language' ) ?></language>
					<sy:updatePeriod><?php echo esc_html( 'hourly' ) ?></sy:updatePeriod>
					<sy:updateFrequency><?php echo absint( 1 ) ?></sy:updateFrequency>
					<?php do_action( 'rss2_head' ) ?>
					<?php foreach ( $records as $record ) : ?>
						<?php
						$record_link  = add_query_arg(
							array(
								'page'       => WP_Stream_Admin::RECORDS_PAGE_SLUG,
								'record__in' => $record->ID,
							),
							admin_url( WP_Stream_Admin::ADMIN_PARENT_PAGE )
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
							<?php rss_enclosure() ?>
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
