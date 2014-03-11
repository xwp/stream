/*jslint nomen: true */
/*global jQuery, _, nv, d3, stream, streamReportsLocal, document, window */
(function (window, $, _, nv, d3, streamReportsLocal) {
    'use strict';

    var report = {};

    report.intervals = {
        init: function ($wrapper) {
			this.wrapper = $wrapper;
			this.save_interval(this.wrapper.find('.button-primary'));

            this.$ = this.wrapper.each(function () {
                var container = $(_.last(arguments)),
                    from = container.find('.field-from'),
                    to = container.find('.field-to'),
                    to_remove = to.prev('.date-remove'),
                    from_remove = from.prev('.date-remove'),
                    predefined = container.children('.field-predefined'),
                    datepickers = $('').add(to).add(from);

                if (_.isFunction($.fn.datepicker)) {
                    to.datepicker({
                        dateFormat: 'yy/mm/dd',
						maxDate: 0
                    });

                    from.datepicker({
                        dateFormat: 'yy/mm/dd',
						maxDate: 0
                    });

                    datepickers.datepicker('widget').addClass('stream-datepicker');
                }

                if (_.isFunction($.fn.select2)) {
                    predefined.select2({
                        'placeholder': 'Select an Interval to Report',
                        'allowClear': true
                    });
                }

                predefined.on({
                    'change': function () {
                        var value = $(this).val(),
                            option = predefined.find('[value="' + value + '"]'),
                            to_val = option.data('to'),
                            from_val = option.data('from');

                        if ('custom' === value) {
                            return false;
                        }

                        from.val(from_val).trigger('change', [true]);
                        to.val(to_val).trigger('change', [true]);

                        if (_.isFunction($.fn.datepicker) && datepickers.datepicker('widget').is(':visible')) {
                            datepickers.datepicker('refresh').datepicker('hide');
                        }
                    },
                    'select2-removed': function () {
                        predefined.val('').trigger('change');
                    },
                    'check_options': function () {
                        if ('' !== to.val() && '' !== from.val()) {
                            var option = predefined.find('option').filter('[data-to="' + to.val() + '"]').filter('[data-from="' + from.val() + '"]');
                            if (0 !== option.length) {
                                predefined.val(option.attr('value')).trigger('change');
                            } else {
                                predefined.val('custom').trigger('change');
                            }
                        } else if ('' === to.val() && '' === from.val()) {
                            predefined.val('').trigger('change');
                        } else {
                            predefined.val('custom').trigger('change');
                        }
                    }
                });

                from.on({
                    'change': function () {
                        if ('' !== from.val()) {
                            from_remove.show();
                        } else {
                            from_remove.hide();
                        }

                        if (_.last(arguments) === true) {
                            return false;
                        }

                        to.datepicker('option', 'minDate', from.val());
                        predefined.trigger('check_options');
                    }
                });

                to.on({
                    'change': function () {
                        if ('' !== to.val()) {
                            to_remove.show();
                        } else {
                            to_remove.hide();
                        }

                        if (_.last(arguments) === true) {
                            return false;
                        }

                        from.datepicker('option', 'maxDate', to.val());
                        predefined.trigger('check_options');
                    }
                });

								// Trigger change on load
								predefined.trigger('change');

                $('').add(from_remove).add(to_remove).on({
                    'click': function () {
                        $(this).next('input').val('').trigger('change');
                    }
                });
            });
        },
		save_interval: function($btn) {
			var $wrapper = this.wrapper;
			$btn.click(function(){
				var data = {
					key: $wrapper.find('select.field-predefined').find(':selected').val(),
					start: $wrapper.find('.report-date-inputs .field-from').val(),
					end: $wrapper.find('.report-date-inputs .field-to').val()
				};

				// Add params to URL
				$(this).attr('href', $(this).attr('href') + '&' + $.param(data));
			});
		}
    };

	/**
	 * Metabox logic logic
	 */
	report.metabox = {
		init: function (configureDiv, deleteBtn, configureBtn) {
			// Variables
			this.$configureDiv = configureDiv;
			this.$deleteBtn    = deleteBtn;
			this.$configureBtn = configureBtn;

			// Let's configure event listener for all sections
			this.configureSection();
		},
		configureSection: function () {
			var parent = this;
			// Trigger select2js
			this.$configureDiv.find('select.chart-option').select2();

			// Change chart type toggle
			this.$configureDiv.find('.chart-types .dashicons').click(function () {
				var $target = $(this);
				if (!$target.hasClass('active')) {
					$target.siblings().removeClass('active');
					$target.addClass('active');
					parent.$btnSave.removeClass('disabled');
				}
			});
			
			// Change chart type toggle
			this.$configureDiv.find('.chart-option').change(function () {
				parent.$btnSave.removeClass('disabled');
			});

			// Bind handler to save button
			this.$btnSave = this.$configureDiv.find('.button-primary').click(this.configureSave);

			// Confirmation of deletion
			this.$deleteBtn.click(function () {
				if (!window.confirm(streamReportsLocal.deletemsg)) {
					return false;
				}
			});

			// Configuration toggle
			this.$configureBtn.click(function () {
				var $target = $(this),

				// Hold parent container
					$curPostbox = $target.parents('.postbox');

				// Change value of button
				$target.text($target.text() === streamReportsLocal.configure ? streamReportsLocal.cancel : streamReportsLocal.configure);

				// Always show the cancel button
				$target.toggleClass('edit-box');

				// Show the delete button
				$target.parent().next().find('a').toggleClass('visible');

				//Open the section if it's hidden
				$curPostbox.removeClass('closed');

				// Show the configure div
				$curPostbox.find('.inside .configure').toggleClass('visible');
			});
		},
		configureSave: function() {
			var parent = stream.report.metabox;
			if ($(this).hasClass('disabled')){
				return false;
			}
			
			// Send the new
			$.ajax({
				type: 'GET',
				url: ajaxurl,
				data: {
					action: 'stream_report_save_metabox_config',
					stream_reports_nonce : $('#stream_report_nonce').val(),
					chart_type : parent.$configureDiv.find('.chart-types .active').data('type'),
					data_type : parent.$configureDiv.find('.chart-dataset').select2('data').id,
					selector_type : parent.$configureDiv.find('.chart-selector').select2('data').id,
					section_id : $(this).data('id')
				},
				dataType: 'json',
				success : function(data) {
					console.log(data);
				}
			});
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
                    'show': true
                },

                'tooltip': {
                    'show': true
                },

                // y Axis information
                'yAxis': {
                    'show': true,
                    'label' : null,
                    'format' : ',r',
                    'reduceTicks': false
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
                        {data: data.donutRatio,        _function: data.chart.donutRatio},
                        {data: data.label.show,        _function: data.chart.showLabels},
                        {data: data.showValues,        _function: data.chart.showValues},
                        {data: data.label.threshold,   _function: data.chart.labelThreshold},
                        {data: data.label.type,        _function: data.chart.labelType},
                        {data: data.group.spacing,     _function: data.chart.groupSpacing},
                        {data: data.guidelines,        _function: data.chart.useInteractiveGuideline},
                        {data: data.animate,           _function: data.chart.transitionDuration},
                        {data: data.legend.show,       _function: data.chart.showLegend},
                        {data: data.yAxis.show,        _function: data.chart.showYAxis},
                        {data: data.yAxis.reduceTicks, _function: data.chart.reduceYTicks},
                        {data: data.xAxis.show,        _function: data.chart.showXAxis},
                        {data: data.xAxis.reduceTicks, _function: data.chart.reduceXTicks},
                        {data: data.controls,          _function: data.chart.showControls},
                        {data: data.margin,            _function: data.chart.margin},
                        {data: data.tooltip.show,      _function: data.chart.tooltips}
                    ];

                    _.map(mapValidation, function (value) {
                        if (null !== value.data && _.isFunction(value._function)) {
                            value._function(value.data);
                        }
                    });

                    mapValidation = [
                        {data: data.yAxis.label,  object: data.chart.yAxis, _function: 'data.chart.yAxis.axisLabel'},
                        {data: data.yAxis.format, object: data.chart.yAxis, _function: 'data.chart.yAxistickFormat', format: true},
                        {data: data.xAxis.label,  object: data.chart.xAxis, _function: 'data.chart.xAxis.axisLabel'},
                        {data: data.xAxis.format, object: data.chart.xAxis, _function: 'data.chart.xAxis.tickFormat', format: true}
                    ];

                    _.map(mapValidation, function (value) {
                        if (null !== value.data && _.isObject(value.object) && _.isFunction(value._function)) {
                            if (!_.isUndefined(value.format)) {
                                value._function(d3.format(value.data));
                            } else {
                                value._function(value.data);
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

    window.stream = $.extend(true, (!_.isObject(window.stream) ? {} : window.stream), { 'report': report });

    /**
     * Document Ready actions
     */
    $(document).ready(function () {
        stream.report.intervals.init(
			$('.reports-date-interval')
		);

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

}(window, jQuery.noConflict(), _.noConflict(), nv, d3, streamReportsLocal));
