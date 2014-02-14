/* global Raphael, jQuery, _ */
(function( window, $, _, Raphael ){
	'use strict';
	$.fn.streamReportChart = function (){
		if ( typeof arguments[0] !== 'undefined' && typeof arguments[0] !== 'object' ){
			return console.error( 'Error: %s (%s)', 'bad type', typeof arguments[0] );
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
			// Builds a Unique ID for the chart container element
			var	ueid = _.uniqueId(_base_ueid);
			// Append the Unique ID
			$(el).attr( 'id', ueid );
			// Then generate the C3 Chart based on `args`
			var chart = Raphael(ueid);
			chart.text(320, 70, 'Static Pie Chart').attr({ font: '20px sans-serif' });
			chart.piechart(320, 240, 150, [55, 20, 13, 32, 5, 1, 2, 10]);
		});
	};


	// Just working on a good exemple
	$(document).ready(function(){
		$('.report-chart').streamReportChart();
	});

})( window, jQuery.noConflict(), _.noConflict(), Raphael);
