<div class="wrap">
	<h2><?php esc_html_e( 'Stream Reports', 'stream' ); ?></h2>
	<?php
		$args = array(
			'type' => 'multibar-horizontal',
			'showValues' => true,
			'values' => array(
				array(
					'key' => 'One',
					'values' => array(
						array(
							'x' => 0,
							'y' => -3,
						),
						array(
							'x' => 1,
							'y' => -2,
						),
						array(
							'x' => 2,
							'y' => -1,
						),
						array(
							'x' => 3,
							'y' => 0,
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
	<div class="report-chart" style='height: 300px; width: 500px;' data-report='<?php echo json_encode( $args ) ?>'><svg></svg></div>

	<?php
		$args = array(
			'type' => 'multibar',
			'values' => array(
				array(
					'key' => 'One',
					'values' => array(
						array(
							'x' => -2,
							'y' => 0,
						),
						array(
							'x' => -1,
							'y' => 1,
						),
						array(
							'x' => 0,
							'y' => 2,
						),
						array(
							'x' => 1,
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
	<div class="report-chart" style='height: 300px; width: 500px;' data-report='<?php echo json_encode( $args ) ?>'><svg></svg></div>

	<?php
		$args = array(
			'type' => 'pie',
			'values' => array(
				array(
					'key' => 'One',
					'value' => 20,
				),
				array(
					'key' => 'Two',
					'value' => 30,
				),
				array(
					'key' => 'Three',
					'value' => 55,
				),
				array(
					'key' => 'Four',
					'value' => 15,
				),
			),
		);
	?>
	<div class="report-chart" style='height: 300px; width: 500px;' data-report='<?php echo json_encode( $args ) ?>'><svg></svg></div>

	<div style='clear:both'></div>
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
	<div class="report-chart" style='height: 300px; width: 500px;' data-report='<?php echo json_encode( $args ) ?>'><svg></svg></div>
</div>