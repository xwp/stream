<div class="wrap">
	<h2><?php _e( 'Stream Reports', 'stream-reports' ); ?></h2>

	<input id="stream-add-section" type="button" class="button-primary" value="Add section">

	<?php wp_nonce_field( 'stream-reports-page', 'stream_report_nonce', false ); ?>
	<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
	<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>

	<div id="dashboard-widgets" class="<?php echo esc_attr( $class ) ?>">
		<div id="postbox-container-1" class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'normal', '' ); ?>
		</div>
		<div id="postbox-container-2" class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'side', '' ); ?>
		</div>
		<div id="postbox-container-3" class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'column3', '' ); ?>
		</div>
	</div>

</div>
