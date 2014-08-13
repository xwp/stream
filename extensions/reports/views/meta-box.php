<div class="stream-section-wrapper">

	<div class="configure <?php echo esc_attr( $configure_class ); ?>">

		<div class="inside">

			<input type="hidden" class="section-id" value="<?php echo esc_attr( $args['key'] ); ?>">
			<input type="hidden" class="chart-title" value="<?php echo esc_attr( $args['title'] ); ?>">
			<input type="hidden" class="chart-generated-title" value="<?php echo esc_attr( $args['generated_title'] ); ?>">

			<?php wp_stream_reports_selector( $action_types, $args, 'chart-option chart-action' ); ?>

			<span class="grouping-separator"><?php esc_html_e( 'in', 'stream' ) ?></span>

			<?php wp_stream_reports_selector( $data_types, $args, 'chart-option chart-dataset' ); ?>

			<span class="grouping-separator"><?php esc_html_e( 'by', 'stream' ) ?></span>

			<select class="chart-option chart-selector">
				<?php foreach ( $selector_types as $type => $text ) : ?>
					<option value="<?php echo esc_attr( $type ) ?>" <?php selected( $type === $args['selector_id'] ) ?>><?php echo esc_html( $text ) ?></option>
				<?php endforeach; ?>
			</select>

			<div class="chart-types">
				<?php foreach ( $chart_types as $type => $class ) : ?>
					<div data-type="<?php echo esc_attr( $type ) ?>" class="dashicons <?php echo esc_attr( $class ) ?>"></div>
				<?php endforeach; ?>
			</div>

			<input type="button" name="submit" class="button button-primary configure-submit" value="<?php esc_attr_e( 'Save', 'stream' ) ?>" data-id="<?php echo absint( $key ) ?>">
			<span class="spinner"></span>

		</div>

	</div>

	<div class="chart" style="height:<?php echo absint( $chart_height ) ?>px;">
		<div class="chart-loading"><span><span class="spinner"></span><?php _e( 'Loading&hellip;', 'stream' ) ?></span></div>
		<svg></svg>
	</div>

</div>
