<div class="date-interval">

	<select class="field-predefined" data-placeholder="<?php esc_attr_e( 'All Time', 'stream' ) ?>">
		<option></option>
		<option value="custom" <?php selected( 'custom' === $user_interval['key'] ) ?>><?php esc_attr_e( 'Custom', 'stream' ) ?></option>
		<?php
		foreach ( $date->intervals as $key => $interval ) {
			$start = isset( $interval['start'] ) ? $interval['start']->format( 'Y/m/d' ) : null;
			$end   = isset( $interval['end'] ) ? $interval['end']->format( 'Y/m/d' ) : date( 'Y/m/d' );
			$key   = ( $key === $user_interval['key'] ) ? 'selected="selected"' : null;

			printf(
				'<option value="%s" data-from="%s" data-to="%s" %s>%s</option>',
				esc_attr( $key ),
				esc_attr( $start ),
				esc_attr( $end ),
				$key, // xss ok
				esc_html( $interval['label'] )
			);
		}
		?>
	</select>

	<div class="date-inputs">
		<div class="box">
			<i class="date-remove dashicons"></i>
			<input type="text" name="date_from" class="date-picker field-from" placeholder="<?php esc_attr_e( 'Start date', 'stream' ) ?>" value="<?php echo esc_attr( $user_interval['start'] ) ?>">
		</div>
		<span class="connector dashicons"></span>
		<div class="box">
			<i class="date-remove dashicons"></i>
			<input type="text" name="date_to" class="date-picker field-to" placeholder="<?php esc_attr_e( 'End date', 'stream' ) ?>" value="<?php echo esc_attr( $user_interval['end'] ) ?>">
		</div>
	</div>

	<a href="<?php echo esc_url( $save_interval_url ) ?>" class="button button-primary"><?php esc_html_e( 'Update', 'stream' ) ?></a>

	<div class="clear"></div>

</div>
