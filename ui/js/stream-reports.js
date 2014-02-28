/*jslint nomen: true */
/*global jQuery, _, window, document */
(function (window, $, _) {
    'use strict';

    var intervals = {},
        reports = {};

    reports.screenCheck = function () {
        return $('body').hasClass('stream_page_wp_stream_reports');
    };

    intervals.init = function () {
        if (!window.stream.reports.screenCheck()) {
            return;
        }
        var elements = $('.reports-date-interval');

        intervals.$ = elements.each(function () {
            var container = $(_.last(arguments)),
                from = container.find('.field-from'),
                to = container.find('.field-to'),
                to_remove = to.prev('.date-remove'),
                from_remove = from.prev('.date-remove'),
                predefined = container.children('.field-predefined'),
                datepickers = $('').add(to).add(from);

            if (_.isFunction($.fn.datepicker)) {
                to.datepicker({
                    dateFormat: 'yy/mm/dd'
                });

                from.datepicker({
                    dateFormat: 'yy/mm/dd'
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

            $('').add(from_remove).add(to_remove).on({
                'click': function () {
                    $(this).next('input').val('').trigger('change');
                }
            });
        });
    };

    reports = $.extend(true, reports, { 'intervals': intervals });

    window.stream = $.extend(true, (!_.isObject(window.stream) ? {} : window.stream), { 'reports': reports });

    // Delete Action
    $('.postbox').hover(
        function () {
            $(this).find('.settings .delete').addClass('visible');
        },
        function () {
            $(this).find('.settings .delete').removeClass('visible');
        }
    );

    $(document).ready(function () {
        window.stream.reports.intervals.init();
    });

}(window, jQuery.noConflict(), _.noConflict()));
