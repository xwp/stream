<div class="wrap">

	<?php if ( is_network_admin() ) : ?>

		<?php
		$site_count = sprintf( _n( '1 site', '%d sites', get_blog_count(), 'stream-reports' ), get_blog_count() );

		printf(
			'<h2>%s (%s)<a href="%s" class="add-new-h2">%s</a></h2>',
			esc_html__( 'Stream Reports', 'stream-reports' ),
			$site_count, // xss ok
			esc_url( $add_url ),
			esc_html__( 'Add New', 'stream-reports' )
		);
		?>

	<?php else : ?>

		<h2><?php esc_html_e( 'Stream Reports', 'stream-reports' ) ?>
			<a href="<?php echo esc_url( $add_url ) ?>" class="add-new-h2">
				<?php esc_html_e( 'Add New', 'stream-reports' ) ?>
			</a>
		</h2>

	<?php endif; ?>

	<?php wp_nonce_field( 'stream-reports-page', 'wp_stream_reports_nonce', false ) ?>
	<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ) ?>
	<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ) ?>

	<?php wp_stream_reports_intervals_html() ?>

	<div id="dashboard-widgets" class="<?php echo esc_attr( $class ) ?>">

		<div class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'normal', 'normal' ) ?>
		</div>

		<div class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'side', 'side' ) ?>
		</div>

	</div>

</div>
