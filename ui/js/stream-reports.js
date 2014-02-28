/* global jQuery, _, nv, d3, stream, streamReportsLocal */
(function (window, $, _, nv, d3, streamReportsLocal) {
    'use strict';
    var report = {};

		/**
		 * Metabox logic logic
		 */
		report.metabox = {
			init: function(configureDiv, deleteBtn, configureBtn) {
				// Variables
				this.$configureDiv = configureDiv;
				this.$deleteBtn    = deleteBtn;
				this.$configureBtn = configureBtn;

				// Let's configure event listener for all sections
				this.configureSection();
			},
			configureSection: function() {
				var t = this;

				// Trigger select2js
				t.$configureDiv.find('.chart-options').select2();

				// Change chart type toggle
				this.$configureDiv.find('.chart-types .dashicons').click(function() {
					var $target = $(this);
					if(!$target.hasClass('active')){
						$target.siblings().removeClass('active');
						$target.addClass('active');
						t.configureEnableSaveButton($target);
					}
				});

				// Confirmation of deletion
				this.$deleteBtn.click(function(){
					if (!window.confirm(streamReportsLocal.deletemsg)) {
						return false;
					}
				});

				// Configuration toggle
				this.$configureBtn.click(function(){
					var $target = $(this);
					// Change value of button
					$target.text( $target.text() === streamReportsLocal.configure ? streamReportsLocal.cancel : streamReportsLocal.configure );

					// Always show the cancel button
					$target.toggleClass('edit-box');

					// Show the delete button
					$target.parent().next().find('a').toggleClass('visible');

					// Hold parent container
					var $curPostbox = $target.parents('.postbox');

					//Open the section if it's hidden
					$curPostbox.removeClass('closed');

					// Show the configure div
					$curPostbox.find('.inside .configure').toggleClass('visible');
				});
			},
			configureEnableSaveButton : function($target){
				var $submit = $target.parents('.configure').find('.configure-submit');
				if( $submit.hasClass('disabled') ) {
					$submit.removeClass('disabled');
				}
			}
		};


		/**
		 * Chart logic
		 */
		report.chart = {
			_: {
			// Default Options
			'opts': {
				// Store the jQuery elements we are using
				'$': null,

				// Margin on the sides of the chart
				'margin': {
					'left': 30,
					'right': 25
				},

				// Width of the canvas
				'width': 'this',

				// Height of the Canvas
				'height': 'this',

				// Actual data that will be plotted
				'values': {},

				// Global Label options
				'label':  {
					'show': true,
					'threshold': 0.05,
					'type': 'percent'
				},

				// Global legend options
				'legend':  {
					'show': true,
				},

				'tooltip': {
					'show': true
				},

				// y Axis information
				'yAxis': {
					'show': true,
					'label' : null,
					'format' : ',r',
					'reduceTicks': false,
				},

				// x Axis information
				'xAxis': {
					'show': true,
					'label' : null,
					'format' : ',r',
					'reduceTicks': false,
					'rotateLabels': 0
				},

				// Group opts
				'group': {
					'spacing': 0.1
				},

				// Use interactive guidelines
				'guidelines': false,

				// Use interactive guidelines
				'showValues': false,

				// Show Controls
				'controls': true,

				// Type of the Chart
				'type': false,

				// Miliseconds on the animation, or false to deactivate
				'animate': 200,

				// Check if a graph need to be draw
				'draw': null
			}
			},

			// Grab all the opts and draw the chart on the screen
			draw: function () {
				return;
			},

			// Build all the opts to be drawn later
			init: function (elements, $columns, opts) {
				opts = $.extend(true, {}, report.chart._.opts, { '$' : elements }, (typeof opts !== 'undefined' ? opts : {}));

				opts.$.each(function (k, el) {
					var $el = $(el),
							data = $el.data('report', $.extend(true, {}, opts, { 'id': _.uniqueId('__stream-report-chart-') }, $el.data('report'))).data('report');

					if ('parent' === data.width || 'parent' === data._width) {
						data.width = $el.parent().innerWidth();
						data._width = 'parent';
					} else if ('this' === data.width || 'this' === data._width) {
						data.width = $el.innerWidth();
						data._width = 'this';
					}

					if ('parent' === data.height || 'parent' === data._height) {
						data.height = $el.parent().innerHeight();
						data._height = 'parent';
					} else if ('this' === data.height || 'this' === data._height) {
						data.height = $el.innerHeight();
						data._height = 'this';
					}

					// This is very important, if you build the SVG live it was bugging...
					data.svg = $el.find('svg');
					data.d3 = d3.select(data.svg[0]);

					nv.addGraph(function () {
						switch (data.type) {
							case 'donut':
							case 'pie':
								data.chart = nv.models.pieChart();
								data.chart.x(function (d) { return d.key; });
								data.chart.y(function (d) { return d.value; });

								if ('donut' === data.type) {
									data.chart.donut(true);
								}
								break;

							case 'line':
								data.chart = nv.models.lineChart();
								break;

							case 'multibar':
								data.chart = nv.models.multiBarChart();
								break;

							case 'multibar-horizontal':
								data.chart = nv.models.multiBarHorizontalChart();
								break;

							default: // If we don't have a type of chart defined it gets out...
								return;
						}

						var mapValidation = [
							{data: data.donutRatio,        function: data.chart.donutRatio},
							{data: data.label.show,        function: data.chart.showLabels},
							{data: data.showValues,        function: data.chart.showValues},
							{data: data.label.threshold,   function: data.chart.labelThreshold},
							{data: data.label.type,        function: data.chart.labelType},
							{data: data.group.spacing,     function: data.chart.groupSpacing},
							{data: data.guidelines,        function: data.chart.useInteractiveGuideline},
							{data: data.animate,           function: data.chart.transitionDuration},
							{data: data.legend.show,       function: data.chart.showLegend},
							{data: data.yAxis.show,        function: data.chart.showYAxis},
							{data: data.yAxis.reduceTicks, function: data.chart.reduceYTicks},
							{data: data.xAxis.show,        function: data.chart.showXAxis},
							{data: data.xAxis.reduceTicks, function: data.chart.reduceXTicks},
							{data: data.controls,          function: data.chart.showControls},
							{data: data.margin,            function: data.chart.margin},
							{data: data.tooltip.show,      function: data.chart.tooltips}
						];

						_.map(mapValidation, function(value){
							if (null !== value.data && _.isFunction(value.function)) {
								value.function(value.data);
							}
						});

						mapValidation = [
							{data: data.yAxis.label,  object: data.chart.yAxis, function: 'data.chart.yAxis.axisLabel'},
							{data: data.yAxis.format, object: data.chart.yAxis, function: 'data.chart.yAxistickFormat', format: true},
							{data: data.xAxis.label,  object: data.chart.xAxis, function: 'data.chart.xAxis.axisLabel'},
							{data: data.xAxis.format, object: data.chart.xAxis, function: 'data.chart.xAxis.tickFormat', format: true}
						];

						_.map(mapValidation, function(value){
							if (null !== value.data && _.isObject(value.object) && _.isFunction(value.function)) {
								if (!_.isUndefined(value.format)){
									value.function(d3.format(value.data));
								} else {
									value.function(value.data);
								}
							}
						});

						data.d3.datum(data.values).call(data.chart);

						//Update the chart when window resizes.
						nv.utils.windowResize(data.chart.update);
						$columns.click(data.chart.update);

						return data.chart;
					});

				});
			}
    };

    window.stream = _.extend((!_.isObject(window.stream) ? {} : window.stream), { 'report' : report });

		/**
		 * Document Ready actions
		 */
    $(document).ready(function(){
			stream.report.chart.init(
					$('.stream_page_wp_stream_reports .chart'),
					$('.columns-prefs input[type="radio"]')
			);
			stream.report.metabox.init(
					$('.postbox .inside .configure'),
					$('.postbox-delete-action a'),
					$('.postbox-title-action .edit-box')
			);
    });

})(window, jQuery.noConflict(), _.noConflict(), nv, d3, streamReportsLocal);
