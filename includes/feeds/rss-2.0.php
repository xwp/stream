<?php
/**
 * Renders a RSS feed of records.
 *
 * @package WP_Stream
 */

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
	do_action( 'rss2_ns' )
	?>
>
	<channel>
		<title><?php bloginfo_rss( 'name' ); ?> - <?php esc_html_e( 'Stream Feed', 'stream' ); ?></title>
		<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
		<link><?php echo esc_url( $records_admin_url ); ?></link>
		<description><?php bloginfo_rss( 'description' ); ?></description>
		<lastBuildDate><?php echo esc_html( mysql2date( 'r', $latest_record, false ) ); ?></lastBuildDate>
		<language><?php bloginfo_rss( 'language' ); ?></language>
		<sy:updatePeriod><?php echo esc_html( 'hourly' ); ?></sy:updatePeriod>
		<sy:updateFrequency><?php echo absint( 1 ); ?></sy:updateFrequency>
		<?php
		/**
		 * Action fires during RSS head
		 */
		do_action( 'rss2_head' );

		foreach ( $records as $record ) :
			$record_link = add_query_arg(
				array(
					'record__in' => $record->ID,
				),
				$records_admin_url
			);

			$author       = get_userdata( $record->author );
			$display_name = isset( $author->display_name ) ? $author->display_name : 'N/A';
			?>
			<item>
				<title><![CDATA[ <?php echo esc_html( $record->summary ); // xss ok. ?> ]]></title>
				<pubDate><?php echo esc_html( mysql2date( 'r', $record->created, false ) ); ?></pubDate>
				<dc:creator><?php echo esc_html( $display_name ); ?></dc:creator>
				<category domain="connector"><![CDATA[ <?php echo esc_html( $record->connector ); ?> ]]></category>
				<category domain="context"><![CDATA[ <?php echo esc_html( $record->context ); ?> ]]></category>
				<category domain="action"><![CDATA[ <?php echo esc_html( $record->action ); ?> ]]></category>
				<category domain="ip"><?php echo esc_html( $record->ip ); ?></category>
				<guid isPermaLink="false"><?php echo esc_url( $record_link ); ?></guid>
				<link><?php echo esc_url( $record_link ); ?></link>
				<?php
				/**
				 * Action fires during RSS item
				 */
				do_action( 'rss2_item' )
				?>
			</item>
		<?php endforeach; ?>
	</channel>
</rss>
