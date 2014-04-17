<div class="wrap">

	<h2><?php esc_html_e( 'Stream Reports', 'stream-reports' ) ?>
		<a href="<?php echo esc_url( $add_url ) ?>" class="add-new-h2">
			<?php esc_html_e( 'Add New', 'stream-reports' ) ?>
		</a>
	</h2>

	<?php wp_nonce_field( 'stream-reports-page', 'wp_stream_reports_nonce', false ) ?>
	<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ) ?>
	<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ) ?>

	<?php wp_stream_reports_intervals_html() ?>

	<div id="dashboard-widgets" class="<?php echo esc_attr( $class ) ?>">

		<?php if ( $no_reports ) : ?>
		<div class="no-reports-message">
			<?php esc_html_e( "There's nothing here! Do you want to ", 'stream-reports' ); ?>
			<a href="<?php echo esc_attr( $create_url ) ?>">
				<?php esc_html_e( 'create some reports?', 'stream-reports' ); ?>
			</a>
		</div>
		<?php endif; ?>

		<div class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'normal', 'normal' ) ?>
		</div>

		<div class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'side', 'side' ) ?>
		</div>

	</div>

</div>
