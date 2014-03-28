<div class="date-interval">

	<select class="field-predefined" data-placeholder="<?php _e( 'All Time', 'stream-reports' ); ?>">
		<option></option>
		<option value="custom" <?php selected( 'custom' === $user_interval['key'] ); ?>><?php esc_attr_e( 'Custom', 'stream-reports' ) ?></option>
		<?php foreach ( $date->intervals as $key => $interval ) {
			echo sprintf(
				'<option value="%s" data-from="%s" data-to="%s" %s>%s</option>',
				esc_attr( $key ),
				esc_attr( $interval['start']->format( 'Y/m/d' ) ),
				esc_attr( $interval['end']->format( 'Y/m/d' ) ),
				selected( $key === $user_interval['key'] ),
				esc_html( $interval['label'] )
			); // xss ok
		} ?>
	</select>

	<div class="date-inputs">
		<div class="box">
			<i class="date-remove dashicons"></i>
			<input type="text"
				 name="date_from"
				 class="date-picker field-from"
				 placeholder="<?php esc_attr_e( 'Start date', 'stream-reports' ) ?>"
				 value="<?php echo esc_attr( $user_interval['start'] ) ?>">
		</div>
		<span class="connector dashicons"></span>
		<div class="box">
			<i class="date-remove dashicons"></i>
			<input type="text"
				 name="date_to"
				 class="date-picker field-to"
				 placeholder="<?php esc_attr_e( 'End date', 'stream-reports' ) ?>"
				 value="<?php echo esc_attr( $user_interval['end'] ) ?>">
		</div>
	</div>

	<a href="<?php echo esc_url( $save_interval_url ) ?>" class="button button-primary"><?php esc_html_e( 'Update', 'stream-reports' ) ?></a>

	<div class="clear"></div>

</div>
