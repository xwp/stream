<div class="wrap">

	<h2><?php esc_html_e( 'Stream Reports', 'stream-reports' ) ?>
		<a href="<?php echo esc_url( $add_url ) ?>" class="add-new-h2">
			<?php esc_html_e( 'Add New', 'stream-reports' ) ?>
		</a>
	</h2>

	<?php wp_nonce_field( 'stream-reports-page', 'stream_report_nonce', false ) ?>
	<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ) ?>
	<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ) ?>

	<div id="dashboard-widgets" class="<?php echo esc_attr( $class ) ?>">

		<div class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'normal', 'normal' ) ?>
		</div>

		<div class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'side', 'side' ) ?>
		</div>

	</div>
</div>
