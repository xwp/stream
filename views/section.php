<div class="stream-section-wrapper" data-id="<?php echo esc_attr( $key ); ?>">
	<div class="configure">
		<select class="chart-options">
			<option value="all">All activity</option>
			<option value="role">Activity by Role</option>
			<option value="custom">Custom</option>
		</select>
		<p class="submit">
			<input type="button" name="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Submit' ); ?>">
		</p>
	</div>

	<div class="chart">
		This is where the magic happens.
	</div>
</div>
