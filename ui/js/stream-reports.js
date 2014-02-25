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
        },
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


                if (null !== data.donutRatio && $.isFunction(data.chart.donutRatio)) {
                    data.chart.donutRatio(data.donutRatio);
                }

                if (null !== data.label.show && $.isFunction(data.chart.showLabels)) {
                    data.chart.showLabels(data.label.show);
                }

                if (null !== data.showValues && $.isFunction(data.chart.showValues)) {
                    data.chart.showValues(data.showValues);
                }

                if (null !== data.label.threshold && $.isFunction(data.chart.labelThreshold)) {
                    data.chart.labelThreshold(data.label.threshold);
                }

                if (null !== data.label.type && $.isFunction(data.chart.labelType)) {
                    data.chart.labelType(data.label.type);
                }

                if (null !== data.group.spacing && $.isFunction(data.chart.groupSpacing)) {
                    data.chart.groupSpacing(data.group.spacing);
                }

                if (null !== data.guidelines && $.isFunction(data.chart.useInteractiveGuideline)) {
                    data.chart.useInteractiveGuideline(data.guidelines);
                }

                if (null !== data.animate && $.isFunction(data.chart.transitionDuration)) {
                    data.chart.transitionDuration(data.animate);
                }

                if (null !== data.legend.show && $.isFunction(data.chart.showLegend)) {
                    data.chart.showLegend(data.legend.show);
                }

                if (null !== data.yAxis.show && $.isFunction(data.chart.showYAxis)) {
                    data.chart.showYAxis(data.yAxis.show);
                }

                if (null !== data.yAxis.reduceTicks && $.isFunction(data.chart.reduceYTicks)) {
                    data.chart.reduceYTicks(data.yAxis.reduceTicks);
                }

                if (null !== data.yAxis.label && _.isObject(data.chart.yAxis) && $.isFunction(data.chart.yAxis.axisLabel)) {
                    data.chart.yAxis.axisLabel(data.yAxis.label);
                }

                if (null !== data.yAxis.format && _.isObject(data.chart.yAxis) && $.isFunction(data.chart.yAxis.tickFormat)) {
                    data.chart.yAxis.tickFormat(d3.format(data.yAxis.format));
                }

                if (null !== data.xAxis.show && $.isFunction(data.chart.showXAxis)) {
                    data.chart.showXAxis(data.xAxis.show);
                }

                if (null !== data.xAxis.reduceTicks && $.isFunction(data.chart.reduceXTicks)) {
                    data.chart.reduceXTicks(data.xAxis.reduceTicks);
                }

                if (null !== data.xAxis.label && _.isObject(data.chart.xAxis) && $.isFunction(data.chart.xAxis.axisLabel)) {
                    data.chart.xAxis.axisLabel(data.xAxis.label);
                }

                if (null !== data.xAxis.format && _.isObject(data.chart.xAxis) && $.isFunction(data.chart.xAxis.tickFormat)) {
                    data.chart.xAxis.tickFormat(d3.format(data.xAxis.format));
                }

                if (null !== data.controls && $.isFunction(data.chart.showControls)) {
                    data.chart.showControls(data.controls);
                }

                if (null !== data.margin && $.isFunction(data.chart.margin)) {
                    data.chart.margin(data.margin);
                }

                if (null !== data.tooltip.show && $.isFunction(data.chart.tooltips)) {
                    data.chart.tooltips(data.tooltip.show);
                }

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

})( window, jQuery.noConflict(), _.noConflict(), nv, d3);
