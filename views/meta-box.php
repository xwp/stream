<div class="stream-section-wrapper">

	<div class="configure">

		<div class="inside">

			<select class="chart-option chart-dataset">
				<?php foreach ( $data_types as $section ) : ?>
					<optgroup label="<?php echo esc_attr( $section['title'] ) ?>">
					<?php foreach ( $section['options'] as $type => $text ) : ?>
						<option data-group="<?php echo esc_attr( $section['group'] ) ?>" value="<?php echo esc_attr( $type ) ?>" <?php selected( $type === $data_type ) ?>>
							<?php echo esc_html( $text ) ?>
						</option>
					<?php endforeach; ?>
					</optgroup>
				<?php endforeach; ?>
			</select>

			<span class="grouping-separator"><?php esc_html_e( 'by', 'stream-reports' ) ?></span>

			<select class="chart-option chart-selector">
				<?php foreach ( $selector_types as $type => $text ) : ?>
					<option value="<?php echo esc_attr( $type ) ?>" <?php selected( $type === $selector_type ) ?>><?php echo esc_html( $text ) ?></option>
				<?php endforeach; ?>
			</select>

			<div class="chart-types">
				<?php foreach ( $chart_types as $type => $class ) : ?>
					<div data-type="<?php echo esc_attr( $type ) ?>" class="dashicons <?php echo esc_attr( $class ) ?>"></div>
				<?php endforeach; ?>
			</div>

			<input type="button" name="submit" class="button button-primary configure-submit" value="<?php esc_attr_e( 'Save', 'stream-reports' ) ?>" data-id="<?php echo absint( $key ) ?>">
			<span class="spinner"></span>

		</div>

	</div>

	<?php
	$args = array(
		'type'       => $chart_type,
		'guidelines' => true,
		'tooltip'    => array(
			'show' => true,
		),
		'values' => $coordinates,
	);
	?>

	<div class="chart" data-report='<?php echo json_encode( $args ) ?>'><svg></svg></div>

</div>
