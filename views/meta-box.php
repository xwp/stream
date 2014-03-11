<div class="stream-section-wrapper">
	<div class="configure">
		<div class="inside">
			<select class="chart-option chart-dataset">
				<?php foreach ( $data_types as $type => $text ): 
					$selected = $type == $data_type ? 'selected="selected"' : '';
				?>
				<option value="<?php echo esc_attr( $type ); ?>" <?php echo $selected; ?>><?php echo $text; ?></option>
				<?php endforeach; ?>
			</select>
			<select class="chart-option chart-selector">
				<?php foreach ( $selector_types as $type => $text ): 
					$selected = $type == $selector_type ? 'selected="selected"' : '';
				?>
				<option value="<?php echo esc_attr( $type ); ?>" <?php echo $selected; ?>><?php echo $text; ?></option>
				<?php endforeach; ?>
			</select>
			<div class="chart-types">
				<?php foreach ( $chart_types as $type => $class ): ?>
				<div data-type="<?php echo esc_attr( $type ); ?>"
						 class="dashicons <?php echo esc_attr( $class ); ?>"></div>
				<?php endforeach; ?>
			</div>
			<input type="button"
						 name="submit"
						 class="button button-primary configure-submit disabled"
						 value="<?php esc_html_e( 'Save', 'stream-reports' ) ?>"
						 data-id="<?php echo esc_attr( $key ) ?>">
		</div>
	</div>

	<?php
	$args = array(
		'type' => 'line',
		'guidelines' => true,
		'tooltip' => array(
			'show' => true,
		),
		'values' => array(
			array(
				'key' => 'One',
				'values' => array(
					array(
						'x' => 0,
						'y' => 0,
					),
					array(
						'x' => 1,
						'y' => 1,
					),
					array(
						'x' => 2,
						'y' => 2,
					),
					array(
						'x' => 3,
						'y' => 3,
					),
				),
			),
			array(
				'key' => 'Three',
				'values' => array(
					array(
						'x' => 0,
						'y' => 0,
					),
					array(
						'x' => 1,
						'y' => 4,
					),
					array(
						'x' => 2,
						'y' => 1,
					),
					array(
						'x' => 3,
						'y' => 2,
					),
				),
			),
			array(
				'key' => 'Two',
				'values' => array(
					array(
						'x' => 0,
						'y' => 1,
					),
					array(
						'x' => 1,
						'y' => 2,
					),
					array(
						'x' => 2,
						'y' => 3,
					),
					array(
						'x' => 3,
						'y' => 4,
					),
				),
			),
		),
	);
	?>
	<div class="chart" data-report='<?php echo json_encode( $args ) ?>'><svg></svg></div>

</div>
