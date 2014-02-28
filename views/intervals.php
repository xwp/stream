<div class="reports-date-interval">
	<select class="field-predefined">
		<option value="custom"><?php esc_attr_e( 'Custom Interval', 'stream-reports' ) ?></option>
		<?php foreach ( $date->intervals as $key => $interval ): ?>
			<?php $selected = ( $key === $user_interval['key'] ) ? 'selected' : ''; ?>
			<?php printf(
				'<option value="%s" data-from="%s" data-to="%s" %s>%s</option>',
				esc_attr( $key ),
				esc_attr( $interval['start']->format( 'Y/m/d' ) ),
				esc_attr( $interval['end']->format( 'Y/m/d' ) ),
				esc_attr( $selected ),
				esc_attr( $interval['label'] )
			); ?>
		<?php endforeach; ?>

	</select>

	<div class="report-date-inputs">
		<div class="box">
			<i class="date-remove dashicons"></i>
			<input type="text"
						 name="date_from"
						 class="date-picker field-from"
						 placeholder="Start Date"
						 size="14"
						 value="<?php echo esc_attr( $user_interval['start'] ); ?>">
		</div>
		<span class="connector dashicons"></span>
		<div class="box">
			<i class="date-remove dashicons"></i>
			<input type="text"
						 name="date_to"
						 class="date-picker field-to"
						 placeholder="End date"
						 size="14"
						 value="<?php echo esc_attr( $user_interval['end'] ); ?>">
		</div>
	</div>
	<a href="<?php echo esc_url( $save_interval_url ); ?>" class="button button-primary"><?php _e( 'Generate reports', 'stream-report' ) ?></a>
	<div class="clear"></div>
</div>
