<div class="wrap">
	<h2><?php esc_html_e( 'C3 Exemple', 'stream-reports' ); ?></h2>

	<div id="chart" style='margin: 15px 0;'></div>

	<script>
		var chart = c3.generate({
			data: {
				columns: [
					['data1', 30, 200, 100, 400, 150, 250],
					['data2', 130, 100, 140, 200, 150, 50]
				],
				type: 'bar',
				groups: [
					['data1', 'data2']
				]
			},
			axis: {
				x: {
					type: 'categorized'
				}
			}
		});
	</script>

</div>
