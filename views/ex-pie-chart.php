<div class="wrap">
	<h2><?php esc_html_e( 'Stream Reports', 'stream-reports' ); ?></h2>
	<?php
		$args = array(
			'type' => 'pie',
			'values' => array( 6, 10, 300 ),
		);
	?>
	<div class="report-chart" style='height: 200px; width: 200px; float:left;' data-stream-report='<?php echo json_encode( $args ) ?>'></div>

	<?php
		$args = array(
			'type' => 'pie',
			'values' => array( 5, 200, 300, 50 ),
		);
	?>
	<div class="report-chart" style='height: 200px; width: 200px; float:left;' data-stream-report='<?php echo json_encode( $args ) ?>'></div>

	<div style='clear:both'></div>
	<?php
		$args = array(
			'type' => 'pie',
			'values' => array( 5, 200, 300, 22, 1, 50 ),
		);
	?>
	<div class="report-chart" style='height: 200px; width: 400px; background: #ddd' data-stream-report='<?php echo json_encode( $args ) ?>'></div>
</div>