<div class="date-interval">

	<select class="field-predefined" data-placeholder="<?php esc_attr_e( 'All Time', 'stream' ) ?>">
		<option></option>
		<option value="custom" <?php selected( 'custom' === $user_interval['key'] ) ?>><?php esc_attr_e( 'Custom', 'stream' ) ?></option>
		<?php
		foreach ( $date->intervals as $key => $interval ) {
			echo sprintf(
				'<option value="%s" data-from="%s" data-to="%s" %s>%s</option>',
				esc_attr( $key ),
				isset( $interval['start'] ) ? esc_attr( $interval['start']->format( 'Y/m/d' ) ) : null,
				isset( $interval['end'] ) ? esc_attr( $interval['end']->format( 'Y/m/d' ) ) : date( 'Y/m/d' ),
				( $key === $user_interval['key'] ) ? 'selected="selected"' : null,
				esc_html( $interval['label'] )
			); // xss ok
		}
		?>
	</select>

	<div class="date-inputs">
		<div class="box">
			<i class="date-remove dashicons"></i>
			<input type="text"
				 name="date_from"
				 class="date-picker field-from"
				 placeholder="<?php esc_attr_e( 'Start date', 'stream' ) ?>"
				 value="<?php echo esc_attr( $user_interval['start'] ) ?>">
		</div>
		<span class="connector dashicons"></span>
		<div class="box">
			<i class="date-remove dashicons"></i>
			<input type="text"
				 name="date_to"
				 class="date-picker field-to"
				 placeholder="<?php esc_attr_e( 'End date', 'stream' ) ?>"
				 value="<?php echo esc_attr( $user_interval['end'] ) ?>">
		</div>
	</div>

	<a href="<?php echo esc_url( $save_interval_url ) ?>" class="button button-primary"><?php esc_html_e( 'Update', 'stream' ) ?></a>

	<div class="clear"></div>

</div>
