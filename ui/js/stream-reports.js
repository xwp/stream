/* global jQuery, _, nv, d3, stream */
(function (window, $, _, nv, d3) {
    'use strict';
    var report = {};

    // Internals
    report._ = {
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
    };

    // Grab all the opts and draw the chart on the screen
    report.draw = function () {
        return;
    };

    // Build all the opts to be drawn later
    report.build = function (elements, opts) {
        opts = $.extend(true, {}, report._.opts, { '$' : elements }, (typeof opts !== 'undefined' ? opts : {}));

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
                nv.utils.windowResize(data.chart.update());

                return data.chart;
            });

        });

    };

    window.stream = _.extend((!_.isObject(window.stream) ? {} : window.stream), { 'report' : report });

    // Just working on a good exemple
    $(document).ready(function(){
        stream.report.build( $('.report-chart') );

        // Delete Action
        $('.postbox').hover(
                function() {
                    $(this).find('.settings .delete').addClass( 'visible' );
                }, function() {
                    $(this).find('.settings .delete').removeClass( 'visible' );
                }
        );
    });

})(window, jQuery.noConflict(), _.noConflict(), nv, d3);
