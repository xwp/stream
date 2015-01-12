/*jslint nomen: true */
/*global jQuery, _, nv, d3, stream, wp_stream_reports, ajaxurl, document, window */
( function( window, $, _, nv, d3, wp_stream_reports ) {
	'use strict';

	var report = {};

	report.intervals = {
		init: function( $wrapper ) {
			this.wrapper = $wrapper;
			this.save_interval( this.wrapper.find( '.button-primary' ) );

			this.$ = this.wrapper.each( function() {
				var container   = $( _.last( arguments ) ),
					dateinputs  = container.find( '.date-inputs' ),
					from        = container.find( '.field-from' ),
					to          = container.find( '.field-to' ),
					to_remove   = to.prev( '.date-remove' ),
					from_remove = from.prev( '.date-remove' ),
					predefined  = container.children( '.field-predefined' ),
					datepickers = $( '' ).add( to ).add( from );

				if ( _.isFunction( $.fn.datepicker ) ) {

					// Apply a GMT offset due to Date() using the visitor's local time
					var siteGMTOffsetHours  = parseFloat( wp_stream_reports.gmt_offset ),
						localGMTOffsetHours = new Date().getTimezoneOffset() / 60 * -1,
						totalGMTOffsetHours = siteGMTOffsetHours - localGMTOffsetHours,
						localTime           = new Date(),
						siteTime            = new Date( localTime.getTime() + ( totalGMTOffsetHours * 60 * 60 * 1000 ) ),
						dayOffset           = '0';

					// Check if the site date is different from the local date, and set a day offset
					if ( localTime.getDate() !== siteTime.getDate() || localTime.getMonth() !== siteTime.getMonth() ) {
						if ( localTime.getTime() < siteTime.getTime() ) {
							dayOffset = '+1d';
						} else {
							dayOffset = '-1d';
						}
					}

					datepickers.datepicker({
						dateFormat: 'yy/mm/dd',
						maxDate: Number( dayOffset ),
						defaultDate: siteTime,
						beforeShow: function() {
							$( this ).prop( 'disabled', true );
						},
						onClose: function() {
							$( this ).prop( 'disabled', false );
						}
					});

					datepickers.datepicker( 'widget' ).addClass( 'stream-datepicker' );
				}

				if ( _.isFunction( $.fn.select2 ) ) {
					predefined.select2({
						allowClear: true
					});
				}

				if ( '' !== from.val() ) {
					from_remove.show();
				}

				if ( '' !== to.val() ) {
					to_remove.show();
				}

				predefined.on({
					'change': function() {
						var value    = $( this ).val(),
							option   = predefined.find( '[value="' + value + '"]' ),
							to_val   = option.data( 'to' ),
							from_val = option.data( 'from' );

						if ( 'custom' === value ) {
							dateinputs.show();
							return false;
						} else {
							dateinputs.hide();
							datepickers.datepicker( 'hide' );
						}

						from.val( from_val ).trigger( 'change', [true] );
						to.val( to_val ).trigger( 'change', [true] );

						if ( _.isFunction( $.fn.datepicker ) && datepickers.datepicker( 'widget' ).is( ':visible' ) ) {
							datepickers.datepicker( 'refresh' ).datepicker( 'hide' );
						}
					},
					'select2-removed': function() {
						predefined.val( '' ).trigger( 'change' );
					},
					'check_options': function() {
						if ( '' !== to.val() && '' !== from.val() ) {
							var option = predefined.find( 'option' ).filter( '[data-to="' + to.val() + '"]' ).filter( '[data-from="' + from.val() + '"]' );
							if ( 0 !== option.length ) {
								predefined.val( option.attr( 'value' ) ).trigger( 'change',[true] );
							} else {
								predefined.val( 'custom' ).trigger( 'change',[true] );
							}
						} else if ( '' === to.val() && '' === from.val() ) {
							predefined.val( '' ).trigger( 'change',[true] );
						} else {
							predefined.val( 'custom' ).trigger( 'change',[true] );
						}
					}
				});

				from.on({
					'change': function() {

						if ( '' !== from.val() ) {
							from_remove.show();
							to.datepicker( 'option', 'minDate', from.val() );
						} else {
							from_remove.hide();
						}

						if ( true === _.last( arguments ) ) {
							return false;
						}

						predefined.trigger( 'check_options' );
					}
				});

				to.on({
					'change': function() {
						if ( '' !== to.val() ) {
							to_remove.show();
							from.datepicker( 'option', 'maxDate', to.val() );
						} else {
							to_remove.hide();
						}

						if ( true === _.last( arguments ) ) {
							return false;
						}

						predefined.trigger( 'check_options' );
					}
				});

				// Trigger change on load
				predefined.trigger( 'change' );

				$( '' ).add( from_remove ).add( to_remove ).on({
					'click': function() {
						$( this ).next( 'input' ).val( '' ).trigger( 'change' );
					}
				});
			});
		},

		save_interval: function( $btn ) {
			var $wrapper = this.wrapper;
			$btn.click( function() {
				var data = {
					key: $wrapper.find( 'select.field-predefined' ).find( ':selected' ).val(),
					start: $wrapper.find( '.date-inputs .field-from' ).val(),
					end: $wrapper.find( '.date-inputs .field-to' ).val()
				};

				// Add params to URL
				$( this ).attr( 'href', $( this ).attr( 'href' ) + '&' + $.param( data ) );
			});
		}
	};

	/**
	 * Screen options
	 */
	report.screen = {
		init: function( chartHeightOption, applyBtn ) {
			this.$chartHeightOption = chartHeightOption;
			this.$applyBtn = applyBtn;

			this.configureOptions();
		},

		configureOptions: function() {
			var parent = this;

			this.$applyBtn.click( function( e ) {
				e.preventDefault();

				var $spinner = $( this ).siblings( '.spinner' );
				$spinner.show();

				$.ajax({
					type: 'GET',
					url: ajaxurl,
					data: {
						action: 'wp_stream_reports_save_chart_height',
						wp_stream_reports_nonce: $( '#wp_stream_reports_nonce' ).val(),
						chart_height: parent.$chartHeightOption.val()
					},
					dataType: 'json',
					success: function() {
						location.reload( true );
					}
				});

				return false;
			});
		}
	};

	/**
	 * Metabox logic logic
	 */
	report.metabox = {
		init: function( configureDiv, deleteBtn, configureBtn ) {
			// Variables
			this.$configureDiv = configureDiv;
			this.$deleteBtn	= deleteBtn;
			this.$configureBtn = configureBtn;

			// Let's configure event listener for all sections
			this.configureSection();
		},

		configureSection: function() {
			var parent = this;

			// Trigger select2js
			this.$configureDiv.find( 'select.chart-option' ).select2({
				minimumResultsForSearch: 8
			});

			// Change chart type toggle
			this.$configureDiv.find( '.chart-types .dashicons' ).click( function() {
				var $target = $( this );
				if ( ! $target.hasClass( 'active' ) ) {
					$target.siblings().removeClass( 'active' );
					$target.addClass( 'active' );
				}
			});

			// Bind handler to save button
			this.$btnSave = this.$configureDiv.find( '.button-primary' ).click( this.configureSave );

			// Confirmation of deletion
			this.$deleteBtn.click( function() {
				if ( ! window.confirm( wp_stream_reports.i18n.deletemsg ) ) {
					return false;
				}
			});

			this.$configureDiv.find( '.chart-dataset' ).on( 'change', function() {
				var selectors = parent.$configureDiv.find( '.chart-selector' );
				selectors.find( 'option' ).removeAttr( 'disabled' );

				var option = parent.$configureDiv.find( '.chart-dataset :selected' );
				if ( 'all' === $( option ).val() ) {
					return;
				}

				var disable = option.closest( 'optgroup' ).data( 'disable-selectors' );
				var disabled_selectors = disable.split( ',' );

				for ( var i = 0; i < disabled_selectors.length; i++ ) {
					option = selectors.find( 'option[value="' + disabled_selectors[i] + '"]');
					option.attr( 'disabled', 'disabled' );
					if ( option.is( ':selected' ) ) {
						option.removeAttr( 'selected' );
						selectors.trigger( 'change' );
					}
				}

			});

			this.$configureDiv.parents( '.postbox' ).on( 'keyup paste', '.title', function() {

				var $inputBox = $( this );

				if ( '' === $( this ).val() && $( this ).siblings( '.clear-title' ).length ) {
					$( this ).siblings( '.clear-title' ).remove();
				}

				if ( '' !== $( this ).val() && ! $( this ).siblings( '.clear-title' ).length ) {
					$( this ).after(
						$( '<i/>', {
							'class': 'clear-title dashicons',
							'click': function() {
								$inputBox.val( '' );
								$inputBox.trigger( 'keyup' );
							}
						})
					);
				}

			});

			// Configuration toggle
			this.$configureBtn.on( 'click.streamReports', function() {
				var $target = $( this );

				var $curPostbox = $target.parents( '.postbox' );

				var realTitle      = $curPostbox.find( '.chart-title' ).val();
				var generatedTitle = $curPostbox.find( '.chart-generated-title' ).val();
				var displayedTitle = realTitle;
				if ( '' === displayedTitle ) {
					displayedTitle = generatedTitle;
				}

				var $titleText, $inputBox;

				// Remove event handler added by core and add it back when user click cancel or save
				if ( $target.text() === wp_stream_reports.i18n.configure ) {
					$titleText = $curPostbox.find( '.hndle .title' );
					$inputBox  = $( '<input/>', {
						'type': 'text',
						'class': 'title',
						'value': realTitle,
						'placeholder': generatedTitle
					});
					$titleText.replaceWith( $inputBox );
					$inputBox.trigger( 'keyup' );

					// Add class to container
					$curPostbox.addClass( 'configure' );

					// Click function management
					parent.$clickFunction = $._data( $curPostbox.find( 'h3' ).get( 0 ) ).events.click[0].handler;
					$curPostbox.find( 'h3' ).off( 'click.postboxes' );

					// Switch configure button text
					$target.text( wp_stream_reports.i18n.cancel );
				} else {
					$inputBox  = $curPostbox.find( '.hndle .title' );
					$titleText = $( '<span/>', {
						'class': 'title',
						'text': displayedTitle
					});

					if ( '' === $titleText.text() ) {
						$titleText.text( $inputBox.attr( 'placeholder' ) );
					}

					$inputBox.replaceWith( $titleText );
					$titleText.siblings( '.clear-title' ).remove();

					// Remove class from container
					$curPostbox.removeClass( 'configure' );

					// Click function management
					$curPostbox.find( 'h3' ).on( 'click.postboxes', parent.$clickFunction );

					// Switch cancel button text
					$target.text( wp_stream_reports.i18n.configure );
				}

				// Always show the cancel button
				$target.toggleClass( 'edit-box' );

				// Show the delete button
				$target.parent().next().find( 'a' ).toggleClass( 'visible' );

				//Open the section if it's hidden
				$curPostbox.removeClass( 'closed' );

				// Show the configure div
				$curPostbox.find( '.inside .configure' ).toggleClass( 'visible' );
			});

			this.$configureDiv.filter( '.stream-reports-expand' ).closest( '.postbox' ).find( '.postbox-title-action a.open-box').click();
		},

		configureSave: function() {
			var parent = $( this ).parents( '.configure' ), $spinner, $postbox, $cancelBtn;

			// Postbox container
			$postbox = $( this ).parents( '.postbox' );

			// Show the spinner
			$spinner = $postbox.find( '.configure .spinner' );
			$spinner.show();

			// Cancel button
			$cancelBtn = $postbox.find( '.hndle .open-box' );

			var id        = $( this ).data( 'id' );
			var option    = parent.find( '.chart-dataset' ).select2( 'data' ).element[0].dataset;
			var connector = option.connector;
			var context   = option.context;
			var action    = parent.find( '.chart-action' ).select2( 'data' ).element[0].dataset.action;
			var selector  = parent.find( '.chart-selector' ).select2( 'data' ).id;

			// Send the new
			$.ajax({
				type: 'GET',
				url: ajaxurl,
				data: {
					action: 'wp_stream_reports_save_metabox_config',
					wp_stream_reports_nonce: $( '#wp_stream_reports_nonce' ).val(),
					section_id: id,
					title: $postbox.find( '.title' ).val(),
					chart_type: parent.find( '.chart-types .active' ).data( 'type' ),
					data_connector: connector,
					data_context: context,
					data_action: action,
					data_selector: selector
				},
				dataType: 'json',
				success: function( data ) {
					$spinner.hide(0,function() {
						$cancelBtn.trigger( 'click' );
					});

					if ( true === data.success ) {

						$.ajax({
							type: 'GET',
							url: ajaxurl,
							data: {
								action: 'wp_stream_reports_update_metabox_display',
								wp_stream_reports_nonce: $( '#wp_stream_reports_nonce' ).val(),
								section_id: id
							},
							dataType: 'json',
							success: stream.report.chart.loadSectionCallback( $postbox )
						});

					}


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
					'label': null,
					'format': ',r',
					'reduceTicks': false
				},

				// x Axis information
				'xAxis': {
					'show': true,
					'label': null,
					'format': ',r',
					'reduceTicks': true,
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
				'animate': 350,

				// Check if a graph need to be draw
				'draw': null,

				// Whether to stack the chart by default
				'stacked': false
			}
		},

		stateChangeCallback: function( section_id ) {
			var id = section_id;
			return function( e ) {
				var data = {
					'type': 'none'
				};

				if ( undefined !== e.stacked ) {
					data.type    = 'group';
					data.payload = e.stacked;
				} else if ( undefined !== e.disabled ) {
					data.type    = 'disable';
					data.payload = e.disabled;
				}

				if ( 'none' !== data.type ) {
					$.ajax({
						type: 'GET',
						url: ajaxurl,
						data: {
							'action': 'wp_stream_reports_save_chart_options',
							'wp_stream_reports_nonce': $( '#wp_stream_reports_nonce' ).val(),
							'section_id': id,
							'update_type': data.type,
							'update_payload': data.payload
						},
						dataType: 'json'
					});
				}
			};
		},

		// Build all the opts to be drawn later
		init: function( elements, $columns ) {
			this.elements = elements;
			this.$columns = $columns;
		},

		// Loads and redraws a section's chart and configuration options
		loadSection: function( $section ) {

			var section_id = $section.data( 'section-id' );
			var $chart     = $section.find( '.chart' );

			$chart.find( '.chart-loading' ).show();

			$.ajax({
				type: 'GET',
				url: ajaxurl,
				data: {
					action: 'wp_stream_reports_update_metabox_display',
					wp_stream_reports_nonce: $( '#wp_stream_reports_nonce' ).val(),
					section_id: section_id
				},
				dataType: 'json',
				success: stream.report.chart.refreshSectionCallback( $section, $chart )
			});

		},

		loadSectionCallback: function( $section ) {
			return function() {
				stream.report.chart.loadSection( $section );
			};
		},

		refreshSectionCallback: function( $section, $chart ) {
			return function( data ) {

				// Update chart data
				$chart.data( 'report', data.data.options );
				$chart.find( 'svg' ).html( '<svg></svg>' );
				var opts = $.extend( true, {}, report.chart._.opts, { '$': this.elements }, ( 'undefined' !== typeof opts ? opts : {} ) );
				stream.report.chart.drawChart( $section.find( '.section-id' ).val(), $chart, opts, stream.report.chart.$columns );

				// Update title values
				$section.find( '.chart-title' ).val( data.data.title );
				$section.find( '.chart-generated-title' ).val( data.data.generated_title );

				// Update Title Text
				var newTitle = data.data.title;
				if ( '' === newTitle || null === newTitle ) {
					newTitle = data.data.generated_title;
				}
				$section.find( '.hndle .title' ).text( newTitle );

				$chart.find( '.chart-loading' ).hide();

			};
		},

		// Grab all the opts and draw the chart on the screen
		drawChart: function( k, el, opts, $columns ) {
			var $el  = $( el ),
				data = $el.data( 'report', $.extend( true, {}, opts, { 'id': _.uniqueId( '__stream-report-chart-' ) }, $el.data( 'report' ) ) ).data( 'report' );

			if ( $( el ).parents( '.postbox.closed' ).length ) {
				return;
			}

			var section_id = $( el ).parents( '.postbox' ).find( '.section-id' ).val();

			if ( 'parent' === data.width || 'parent' === data._width ) {
				data.width = $el.parent().innerWidth();
				data._width = 'parent';
			} else if ( 'this' === data.width || 'this' === data._width ) {
				data.width = $el.innerWidth();
				data._width = 'this';
			}
				if ( 'parent' === data.height || 'parent' === data._height ) {
				data.height = $el.parent().innerHeight();
				data._height = 'parent';
			} else if ( 'this' === data.height || 'this' === data._height ) {
				data.height = $el.innerHeight();
				data._height = 'this';
			}

			// This is very important, if you build the SVG live it was bugging...
			data.svg = $el.find( 'svg' );
			data.d3  = d3.select( data.svg[0] );
				var dateFormat = function( d ) {
				var milliseconds = d * 1000;

				return d3.time.format( '%Y/%m/%d' )( new Date( milliseconds ) );
			};

			nv.addGraph( function() {
				switch ( data.type ) {
					case 'donut':
					case 'pie':
						data.chart = nv.models.pieChart();
						data.chart.valueFormat( d3.format( ',f' ) );
						data.chart.x( function( d ) { return d.key; } );
						data.chart.y( function( d ) { return d.value; } );
						if ( 'donut' === data.type ) {
							data.chart.donut( true );
						}
						break;
					case 'line':
						data.chart = nv.models.lineChart();
						data.chart.xAxis.tickFormat( dateFormat );
						break;
					case 'multibar':
						data.chart = nv.models.multiBarChart();
						data.chart.yAxis.tickFormat( d3.format( ',f' ) );
						data.chart.xAxis.tickFormat( dateFormat );
						break;

					case 'multibar-horizontal':
						data.chart = nv.models.multiBarHorizontalChart();
						data.chart.xAxis.tickFormat( dateFormat );
						break;

					default: // If we don't have a type of chart defined it gets out...
						return;
				}

				var mapValidation = [
					{ data: data.donutRatio,        _function: data.chart.donutRatio },
					{ data: data.label.show,        _function: data.chart.showLabels },
					{ data: data.showValues,        _function: data.chart.showValues },
					{ data: data.label.threshold,   _function: data.chart.labelThreshold },
					{ data: data.label.type,        _function: data.chart.labelType },
					{ data: data.group.spacing,     _function: data.chart.groupSpacing },
					{ data: data.guidelines,        _function: data.chart.useInteractiveGuideline },
					{ data: data.animate,           _function: data.chart.transitionDuration },
					{ data: data.legend.show,       _function: data.chart.showLegend },
					{ data: data.yAxis.show,        _function: data.chart.showYAxis },
					{ data: data.yAxis.reduceTicks, _function: data.chart.reduceYTicks },
					{ data: data.xAxis.show,        _function: data.chart.showXAxis },
					{ data: data.xAxis.reduceTicks, _function: data.chart.reduceXTicks },
					{ data: data.controls,          _function: data.chart.showControls },
					{ data: data.margin,            _function: data.chart.margin },
					{ data: data.tooltip.show,      _function: data.chart.tooltips },
					{ data: data.stacked,           _function: data.chart.stacked }
				];

				_.map( mapValidation, function( value ) {
					if ( null !== value.data && _.isFunction( value._function ) ) {
						value._function( value.data );
					}
				});

				mapValidation = [
					{data: data.yAxis.label,  object: data.chart.yAxis, _function: 'data.chart.yAxis.axisLabel'},
					{data: data.yAxis.format, object: data.chart.yAxis, _function: 'data.chart.yAxistickFormat', format: true},
					{data: data.xAxis.label,  object: data.chart.xAxis, _function: 'data.chart.xAxis.axisLabel'},
					{data: data.xAxis.format, object: data.chart.xAxis, _function: 'data.chart.xAxis.tickFormat', format: true}
				];

				_.map( mapValidation, function( value ) {
					if ( null !== value.data && _.isObject( value.object ) && _.isFunction( value._function ) ) {
						if ( ! _.isUndefined( value.format ) ) {
							value._function( d3.format( value.data ) );
						} else {
							value._function( value.data );
						}
					}
				});

				data.d3.datum( data.values ).call( data.chart );

				//Update the chart when window resizes.
				nv.utils.windowResize( data.chart.update );
				$columns.click( data.chart.update );

				data.chart.dispatch.on( 'stateChange', stream.report.chart.stateChangeCallback( section_id ) );

				return data.chart;
			});
		}

	};

	window.stream = $.extend( true, ( !_.isObject( window.stream ) ? {} : window.stream ), { 'report': report } );

	/**
	 * Document Ready actions
	 */
	$( document ).ready( function() {
		stream.report.intervals.init(
			$( '.date-interval' )
		);

		stream.report.screen.init(
			$( '#chart_height' ),
			$( '#chart_height_apply' )
		);

		$( '.stream_page_wp_stream_reports .postbox' ).each( function() {
			var id = $( this ).find( '.section-id' ).val();
			$( this ).data( 'section-id', id );
		});

		stream.report.chart.init(
			$( '.stream_page_wp_stream_reports .chart' ),
			$( '.columns-prefs input[type="radio"]' )
		);

		$( '.stream_page_wp_stream_reports .chart' ).each( function() {
			stream.report.chart.loadSection( $( this ).parents( '.postbox' ) );
		});

		$( '.postbox.closed' ).bind( 'click.initOpen', function() {
			stream.report.chart.loadSection( $( this ) );
			$( this ).unbind( 'click.initOpen' );
		});

		stream.report.metabox.init(
			$( '.postbox .inside .configure' ),
			$( '.postbox-delete-action a' ),
			$( '.postbox-title-action .edit-box' )
		);
	});

} ( window, jQuery.noConflict(), _.noConflict(), nv, d3, wp_stream_reports ) );
