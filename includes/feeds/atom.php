<?php
/**
 * Renders an Atom feed of records.
 *
 * @package WP_Stream
 */

header( 'Content-Type: ' . feed_content_type( 'atom' ) . '; charset=' . get_option( 'blog_charset' ), true );
printf( '<?xml version="1.0" encoding="%s"?>', esc_attr( get_option( 'blog_charset' ) ) );
?>

<feed xmlns="http://www.w3.org/2005/Atom" xmlns:thr="http://purl.org/syndication/thread/1.0" xml:lang="<?php echo esc_attr( bloginfo_rss( 'language' ) ); ?>" xmlns:sy="http://purl.org/rss/1.0/modules/syndication/" <?php do_action( 'atom_ns' ); ?>>
	<title><?php bloginfo_rss( 'name' ); ?> - <?php esc_html_e( 'Stream Feed', 'stream' ); ?></title>
	<link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link href="<?php echo esc_url( $records_admin_url ); ?>" />
	<subtitle type="html"><?php esc_html( bloginfo_rss( 'description' ) ); ?></subtitle>
	<updated><?php echo esc_html( mysql2date( 'c', $latest_record, false ) ); ?></updated>
	<id><?php echo esc_url( $latest_link ); ?></id>
	<sy:updatePeriod><?php echo esc_html( 'hourly' ); ?></sy:updatePeriod>
	<sy:updateFrequency><?php echo absint( 1 ); ?></sy:updateFrequency>
	<?php
	/**
	 * Action fires during RSS head
	 */
	do_action( 'atom_head' );

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
		<entry>
			<title type="html"><![CDATA[[<?php echo esc_html( $domain ); ?>] <?php echo esc_html( $record->summary ); // xss ok. ?> ]]></title>
			<link href="<?php echo esc_url( $record_link ); ?>" />
			<updated><?php echo esc_html( mysql2date( 'c', $record->created, false ) ); ?></updated>
			<author>
				<name><?php echo esc_html( $display_name ); ?></name>
			</author>
			<category term="connector" label="<?php echo esc_html( $record->connector ); ?>" />
			<category term="context" label="<?php echo esc_html( $record->context ); ?>"/>
			<category term="action" label="<?php echo esc_html( $record->action ); ?>" />
			<category term="ip" label="<?php echo esc_html( $record->ip ); ?>" />
			<id><?php echo esc_url( $record_link ); ?></id>
			<summary type="html"><![CDATA[- <?php echo esc_html( $display_name ); ?> ]]></summary>
			<?php
			/**
			 * Action fires during Atom item
			 */
			do_action( 'atom_item' )
			?>
		</entry>
	<?php endforeach; ?>
</feed>
<?php
