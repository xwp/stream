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

		<div class="postbox-container">
			<?php if ( $no_reports ) : ?>
				<div class="no-reports-message">
					<?php esc_html_e( 'Well, this is embarrassing. There are no reports yet!', 'stream-reports' ) ?>
					<p>
						<a href="<?php echo esc_url( $add_url ) ?>" class="button button-secondary">
							<?php esc_html_e( 'Add New', 'stream-reports' ) ?>
						</a>
						<span><?php esc_html_e( 'or', 'stream-reports' ) ?></span>
						<a href="<?php echo esc_url( $create_url ) ?>" class="button button-primary">
							<?php esc_html_e( 'Auto-Generate', 'stream-reports' ) ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'normal', 'normal' ) ?>
		</div>

		<div class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'side', 'side' ) ?>
		</div>

	</div>

</div>
