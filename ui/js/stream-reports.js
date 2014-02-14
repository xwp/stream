(function( window, $, _, c3 ){
	'use strict';
	$.fn.streamReportChart = function (){
		if ( typeof arguments[0] !== 'undefined' && typeof arguments[0] !== 'object' ){
			return console.error( "Error: %s (%s)", "bad type", typeof arguments[0] );
		}

		var default_args = _.extend(
				// Default Arguments that will be passed for each element found in `this`
				// then this will be replace if the functions finds a data attr
				{
					data: {
						columns: [],
						type: null,
						groups: [],
						names: {}
					},
					axis: {}
				},
				arguments[0]
			),
			_base_ueid = '__stream-report-chart-';

		return this.each(function ( key, el ){
			var	ueid = _.uniqueId(_base_ueid), // Builds a Unique ID for the chart container element
				$chart_box = $(el).attr( 'id', ueid ), // Append the Unique ID
				args = _.extend( {}, default_args, { bindto: "#" + ueid }, $chart_box.data( 'report-chart' ) ), // Builds the arguments
				chart = null;

			// Change `args` if needed


			// Then generate the C3 Chart based on `args`
			chart = c3.generate(args);
		});
	};


	// Just working on a good exemple
	$(document).ready(function (){
		$('.report_chart').streamReportChart();
	});

})( window, jQuery.noConflict(), _.noConflict(), c3 );