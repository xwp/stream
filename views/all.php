<div class="wrap">
	<h2><?php _e( 'Stream Reports', 'stream-reports' ) ?>
		<form action="<?php echo admin_url( 'admin-ajax.php' ); //xss ok ?>" method="post" id="stream-report-form">
			<a href="javascript:void(0)"
				 id="stream-reports-add-section"
				 class="add-new-h2"
				 onclick="document.getElementById('stream-report-form').submit();">
				<?php esc_html_e( 'Add New Section', 'stream-reports' ); ?>
			</a>
			<input type="hidden" name="action" value="stream_reports_add_metabox">
			<?php wp_nonce_field( 'stream-reports-page', 'stream_report_nonce', false ); ?>
			<?php wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false ); ?>
			<?php wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
		</form>
	</h2>

	<div id="dashboard-widgets" class="<?php echo esc_attr( $class ) ?>">
		<div class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'normal', '' ); ?>
		</div>
		<div class="postbox-container">
			<?php do_meta_boxes( WP_Stream_Reports::$screen_id, 'side', '' ); ?>
		</div>
	</div>

</div>
