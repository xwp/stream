<div class="wrap">
	<h2><?php esc_html_e( 'C3 Exemple', 'stream-reports' ); ?></h2>
	<?php
		$args = array(
			'data' => array(
				'columns' => array(
					array( 'group', 30, 200, 100, 400, 150, 250 ),
					array( 'group2', 130, 100, 140, 200, 150, 50 ),
				),
				'type' => 'bar',
				'groups' => array(
					array( 'group', 'group2' ),
				),
				'names' => array(
					'group' => 'Developmentdasdadad',
					'group2' => 'Development',
				),
			),
			'axis' => array(
				'x' => array(
					'type' => 'categorized',
				),
			),
		);
	?>
	<div class="report_chart" style='margin: 15px 0 25px;' data-report-chart='<?php echo json_encode( $args ); ?>'></div>
	<?php
		$args = array(
			'data' => array(
				'columns' => array(
					array( 'group', 30, 200, 100, 400, 150, 250 ),
					array( 'group2', 130, 100, 140, 200, 150, 50 ),
				),
				'type' => 'bar',
				'groups' => array(
					array( 'group', 'group2' ),
				),
				'names' => array(
					'group' => 'Development',
					'group2' => 'Development',
				),
			),
			'axis' => array(
				'x' => array(
					'type' => 'categorized',
				),
			),
		);
	?>
	<div class="report_chart" style='margin: 15px 0 25px;' data-report-chart='<?php echo json_encode( $args ); ?>'></div>
</div>
