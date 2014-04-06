/* globals confirm, wp_stream, ajaxurl */
jQuery(function($){

	$( '.toplevel_page_wp_stream select.chosen-select' ).select2({
			minimumResultsForSearch: 10,
			formatResult: function (record, container) {
				var result = '', $elem = $(record.element);

				if ( undefined !== $elem.attr('data-icon') ) {
					result += '<img src="' + $elem.attr('data-icon') + '" class="wp-stream-select2-icon">';
				}

				result += record.text;

				// Add more info to the container
				container.attr('title', $elem.attr('title'));

				return result;
			},
			allowClear: true,
			width: '165px'
		});

	$( '.toplevel_page_wp_stream input[type=hidden].select2-select' ).select2({
			minimumInputLength: 1,
			allowClear: true,
			width: '165px',
			ajax: {
				url: ajaxurl,
				datatype: 'json',
				data: function (term) {
					return {
						action: 'wp_stream_filters',
						filter: $(this).attr('name'),
						q: term
					};
				},
				results: function (data) {
					return {results: data};
				}
			},
			initSelection: function (element, callback) {
				var id = $(element).val();

				if(id !== '') {
					$.post(
						ajaxurl,
						{
							action: 'wp_stream_get_author_name_by_id',
							id:     id
						},
						function (response) {
							callback({
								id:   id,
								text: response
							});
						},
						'json'
					);
				}
			}
		});
	var stream_select2_change_handler = function (e, input) {
		var $placeholder_class = input.data('select-placeholder');
		var $placeholder_child_class = $placeholder_class + '-child';
		var $placeholder = input.siblings('.' + $placeholder_class);
		jQuery('.' + $placeholder_child_class).off().remove();
		if (typeof e.val === 'undefined') {
			e.val = input.val().split(',');
		}
		$.each(e.val.reverse(), function (value, key) {
			if ( key === null || key === '__placeholder__' || key === '' ) {
				return true;
			}
			$placeholder.after($placeholder.clone(true).attr('class', $placeholder_child_class).val(key));
		});
	};
	$('.stream_page_wp_stream_settings input[type=hidden].select2-select.with-source').each(function (k, el) {
		var $input = $(el);
		$input.select2({
			multiple: true,
			width: 350,
			data: $input.data('values'),
			query: function (query) {
				var data = {results: []};
				if (typeof (query.term) !== 'undefined') {
					$.each($input.data('values'), function () {
						if ( query.term.length === 0 || this.text.toUpperCase().indexOf(query.term.toUpperCase()) >= 0) {
							data.results.push({id: this.id, text: this.text });
						}
					});
				}
				query.callback(data);
			},
			initSelection: function (item, callback) {
				callback( item.data( 'selected' ) );
			}
		}).on('change',function (e) {
			stream_select2_change_handler( e , $input );
		}).trigger('change');
	});
	$( '.stream_page_wp_stream_settings input[type=hidden].select2-select.ip-addresses').each(function( k, el ){
		var $input = $(el);

		$input.select2({
			tags:$input.data('selected'),
			width:350,
			ajax: {
				type: 'POST',
				url: ajaxurl,
				dataType: 'json',
				quietMillis: 500,
				data: function (term) {
					return {
						find:   term,
						limit:  10,
						action: 'stream_get_ips',
						nonce:  $input.data('nonce')
					};
				},
				results: function (response) {
					var answer = {
						results: []
					};

					if (response.success !== true || response.data === undefined ) {
						return answer;
					}

					$.each(response.data, function (key, ip ) {
						answer.results.push({
							id:   ip,
							text: ip
						});
					});

					return answer;
				}
			},
			initSelection: function (item, callback) {
				callback( item.data( 'selected' ) );
			},
			formatNoMatches : function(){
				return '';
			},
			createSearchChoice: function(term) {
				var ip_chunks = [];

				ip_chunks = term.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);

				if (ip_chunks === null) {
					return;
				}

				// remove whole match
				ip_chunks.shift();

				ip_chunks = $.grep(
					ip_chunks,
					function(chunk) {
						return (chunk.charAt(0) !== '0' && parseInt(chunk, 10) <= 255);
					}
				);

				if (ip_chunks.length < 4) {
					return;
				}

				return {
					id:   term,
					text: term
				};
			}
		}).on('change',function(e){
			stream_select2_change_handler( e , $input );
		}).trigger('change');
	});
	var $input_user;
	$('.stream_page_wp_stream_settings input[type=hidden].select2-select.authors_and_roles').each(function (k, el) {
		$input_user = $(el);

		$input_user.select2({
			multiple: true,
			width: 350,
			ajax: {
				type: 'POST',
				url: ajaxurl,
				dataType: 'json',
				quietMillis: 500,
				data: function (term, page) {
					return {
						find:   term,
						limit:  10,
						pager:  page,
						action: 'stream_get_users',
						nonce:  $input_user.data('nonce')
					};
				},
				results: function (response) {
					var roles  = [],
						answer = [];

					roles = $.grep(
						$input_user.data('values'),
						function(role) {
							return role.text.toLowerCase().indexOf($input_user.data('select2').search.val().toLowerCase()) >= 0;
						}
					);

					answer = {
						results: [
							{
								text: 'Roles',
								children: roles
							},
							{
								text: 'Users',
								children: []
							}
						]
					};

					if (response.success !== true || response.data === undefined || response.data.status !== true ) {
						return answer;
					}
					$.each(response.data.users, function (k, user) {
						if ($.contains(roles, user.id)){
							user.disabled = true;
						}
					});
					answer.results[1].children = response.data.users;
					// notice we return the value of more so Select2 knows if more results can be loaded
					return answer;
				}
			},
			formatResult: function (object, container) {
				var result = object.text;

				if ('undefined' !== typeof object.icon) {
					result = '<img src="' + object.icon + '" class="wp-stream-select2-icon">' + result;
				}
				// Add more info to the container
				if ( 'undefined' !== typeof object.tooltip ) {
					container.attr( 'title', object.tooltip );
				} else if ( 'undefined' !== typeof object.user_count ) {
					container.attr( 'title', object.user_count );
				}
				return result;
			},
			formatSelection: function (object){
				if ( $.isNumeric( object.id ) && object.text.indexOf('icon-users') < 0 ){
					object.text += '<i class="icon16 icon-users"></i>';
				}

				return object.text;
			},
			initSelection: function (item, callback) {
				callback(item.data('selected'));
			}
		});
	}).on('change',function (e) {
		stream_select2_change_handler(e, $input_user);
	}).trigger('change');

	$(window).load(function() {
		$( '.toplevel_page_wp_stream [type=search]' ).off( 'mousedown' );
	});

	// Confirmation on some important actions
	$('#wp_stream_general_delete_all_records').click(function(e){
		if ( ! confirm( wp_stream.i18n.confirm_purge ) ) {
			e.preventDefault();
		}
	});

	$('#wp_stream_uninstall').click(function(e){
		if ( ! confirm( wp_stream.i18n.confirm_uninstall ) ) {
			e.preventDefault();
		}
	});

	// Admin page tabs
	var $tabs          = $('.nav-tab-wrapper'),
		$panels        = $('table.form-table'),
		$activeTab     = $tabs.find('.nav-tab-active'),
		defaultIndex   = $activeTab.length > 0 ? $tabs.find('a').index( $activeTab ) : 0,
		hashIndex      = window.location.hash.match(/^#(\d+)$/),
		currentHash    = ( hashIndex !== null ? hashIndex[1] : defaultIndex ),
		syncFormAction = function( index ) {
			var $optionsForm   = $('input[name="option_page"][value="wp_stream"]').parent('form');
			var currentAction  = $optionsForm.attr('action');

			$optionsForm.prop('action', currentAction.replace( /(^[^#]*).*$/, '$1#' + index ));
		};

	$tabs.on('click', 'a', function(){
		var index     = $tabs.find('a').index( $(this) ),
			hashIndex = window.location.hash.match(/^#(\d+)$/);

		$panels.hide().eq(index).show();
		$tabs.find('a').removeClass('nav-tab-active').filter($(this)).addClass('nav-tab-active');

		if ( '' === window.location.hash || null !== hashIndex ) {
			window.location.hash = index;
		}

		syncFormAction(index);
		return false;
	});
	$tabs.children().eq( currentHash ).trigger('click');

	// Heartbeat for Live Updates
	// runs only on stream page (not settings)
	$(document).ready( function() {

		// Only run on page 1 when the order is desc and on page wp_stream
		if(
			'toplevel_page_wp_stream' !== wp_stream.current_screen ||
			'1' !== wp_stream.current_page ||
			'asc' === wp_stream.current_order
		) {
			return;
		}

		var list_sel = '.toplevel_page_wp_stream #the-list';

		// Set initial beat to fast. WP is designed to slow this to 15 seconds after 2.5 minutes.
		wp.heartbeat.interval( 'fast' );

		$(document).on( 'heartbeat-send.stream', function(e, data) {
			data['wp-stream-heartbeat'] = 'live-update';
			var last_id = $( list_sel + ' tr:first .column-id').text();
			last_id = ( '' === last_id ) ? 1 : last_id;
			data['wp-stream-heartbeat-last-id'] = last_id;
			data['wp-stream-heartbeat-query']   = wp_stream.current_query;
		});

		// Listen for "heartbeat-tick" on $(document).
		$(document).on( 'heartbeat-tick.stream', function( e, data ) {

			// If this no rows return then we kill the script
			if ( ! data['wp-stream-heartbeat'] || data['wp-stream-heartbeat'].length === 0  ) {
				return;
			}

			// Get all new rows
			var $new_items = $(data['wp-stream-heartbeat']);

			// Remove all class to tr added by WP and add new row class
			$new_items.removeClass().addClass('new-row');

			//Check if first tr has the alternate class
			var has_class =  ( $( list_sel + ' tr:first').hasClass('alternate') );

			// Apply the good class to the list
			if ( $new_items.length === 1 && !has_class ) {
				$new_items.addClass('alternate');
			} else {
				var even_or_odd = ( $new_items.length%2 === 0 && !has_class ) ? 'even':'odd';
				// Add class to nth child because there is more than one element
				$new_items.filter(':nth-child('+even_or_odd+')').addClass('alternate');
			}

			// Add element to the dom
			$(list_sel).prepend( $new_items );

			$( '.metabox-prefs input' ).each( function() {
				if( $( this ).prop( 'checked' ) !== true ) {
					var label = $( this ).val();
					$( 'td.column-' + label ).hide();
				}
			});

			// Remove the number of element added to the end of the list table
			$( list_sel + ' tr').slice(-$new_items.length).remove();

			// Allow others to hook in, ie: timeago
			$( list_sel ).parent().trigger( 'updated' );

			// Remove background after a certain amount of time
			setTimeout( function() {
				$('.new-row').addClass( 'fadeout' );
				setTimeout( function() {
					$( list_sel + ' tr').removeClass('new-row fadeout');
				}, 500);
			}, 3000);

		});

		//Enable Live Update Checkbox Ajax
		$( '#enable_live_update' ).click( function() {
			var nonce   = $( '#enable_live_update_nonce' ).val();
			var user = $( '#enable_live_update_user' ).val();
			var checked = 'unchecked';
			if ( $('#enable_live_update' ).is( ':checked' ) ) {
				checked = 'checked';
			}

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: { action: 'stream_enable_live_update', nonce : nonce, user : user, checked : checked },
				dataType: 'json',
				beforeSend : function() {
					$( '.stream-live-update-checkbox .spinner' ).show().css( { 'display' : 'inline-block' } );
				},
				success : function() {
					$( '.stream-live-update-checkbox .spinner' ).hide();
				}
			});
		});

		function toggle_filter_submit() {
			var all_hidden = true;
			// If all filters are hidden, hide the button
			if ( ! $( 'div.date-interval' ).is( ':hidden' ) ) {
				all_hidden = false;
			}
			var divs = $( 'div.alignleft.actions div.select2-container' );
			divs.each( function() {
				if ( ! $(this).is( ':hidden' ) ) {
					all_hidden = false;
					return false;
				}
			});
			if ( all_hidden ) {
				$( 'input#record-query-submit' ).hide();
				$( 'span.filter_info' ).show();
			} else {
				$( 'input#record-query-submit' ).show();
				$( 'span.filter_info' ).hide();
			}
		}

		if ( $( 'div.stream-toggle-filters [id="date_range"]' ).is( ':checked' ) ) {
			$( 'div.date-interval' ).show();
		} else {
			$( 'div.date-interval' ).hide();
		}

		var filters = [ 'date_range', 'author', 'connector', 'context', 'action' ];
		for( var i=0; i < filters.length; i++ ) {
			if ( $( 'div.stream-toggle-filters [id="' + filters[i] + '"]'  ).is( ':checked' ) ) {
				$( '[name="' + filters[i] + '"]' ).prev( '.select2-container' ).show();
			} else {
				$( '[name="' + filters[i] + '"]' ).prev( '.select2-container' ).hide();
			}
		}

		toggle_filter_submit();

		//Enable Filter Toggle Checkbox Ajax
		$( 'div.stream-toggle-filters input[type=checkbox]' ).click( function() {

			// Disable other checkboxes for duration of request to avoid "clickjacking"
			var siblings = $(this).closest('div').find('input:checkbox');
			siblings.attr( 'disabled', true );
			var nonce = $( '#toggle_filters_nonce' ).val();
			var user = $( '#toggle_filters_user' ).val();
			var checked = 'unchecked';
			var checkbox = $(this).attr('id');
			if ( $(this).is( ':checked' ) ) {
				checked = 'checked';
			}

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: { action: 'stream_toggle_filters', nonce : nonce, user : user, checked : checked, checkbox: checkbox },
				dataType: 'json',
				beforeSend : function() {
					$( checkbox + ' .spinner' ).show().css( { 'display' : 'inline-block' } );

				},
				success : function( data ) {

					var date_interval_div = $( 'div.date-interval' );
					// toggle visibility of input whose name attr matches checkbox ID
					if ( data.control === 'date_range' ) {
						date_interval_div.toggle();
					} else {
						var control = $( '[name="' + data.control + '"]');
						if ( control.is( 'select' ) ) {
							$( control ).prev( '.select2-container' ).toggle();
						}
					}

					toggle_filter_submit();

				}
			});
			siblings.attr( 'disabled', false );
		});


		$( '#ui-datepicker-div' ).addClass( 'stream-datepicker' );

	});

	// Relative time
	$( 'table.wp-list-table' ).on( 'updated', function() {
		var timeObjects = $(this).find( 'time.relative-time' );
		timeObjects.each( function( i, el ) {
			var thiz = $(el);
			thiz.removeClass( 'relative-time' );
			$( '<strong><time datetime="' + thiz.attr( 'datetime' ) + '" class="timeago"/></time></strong><br/>' )
				.prependTo( thiz.parent().parent() )
				.find( 'time.timeago' )
				.timeago();
		});
	}).trigger( 'updated' );

	var intervals = {
		init: function ($wrapper) {
			this.wrapper = $wrapper;
			this.save_interval(this.wrapper.find('.button-primary'), this.wrapper);

			this.$ = this.wrapper.each(function (i, val) {
				var container = $(val),
					dateinputs = container.find('.date-inputs'),
					from = container.find('.field-from'),
					to = container.find('.field-to'),
					to_remove = to.prev('.date-remove'),
					from_remove = from.prev('.date-remove'),
					predefined = container.children('.field-predefined'),
					datepickers = $('').add(to).add(from);

				if ( jQuery.datepicker ) {

					// Apply a GMT offset due to Date() using the visitor's local time
					var siteGMTOffsetHours  = parseFloat( wp_stream.gmt_offset );
					var localGMTOffsetHours = new Date().getTimezoneOffset() / 60 * -1;
					var totalGMTOffsetHours = siteGMTOffsetHours - localGMTOffsetHours;

					var localTime = new Date();
					var siteTime = new Date( localTime.getTime() + ( totalGMTOffsetHours * 60 * 60 * 1000 ) );
					var dayOffset = '0';

					// check if the site date is different from the local date, and set a day offset
					if ( localTime.getDate() !== siteTime.getDate() || localTime.getMonth() !== siteTime.getMonth() ) {
						if ( localTime.getTime() < siteTime.getTime() ) {
							dayOffset = '+1d';
						} else {
							dayOffset = '-1d';
						}
					}

					datepickers.datepicker({
						dateFormat: 'yy/mm/dd',
						maxDate: dayOffset,
						defaultDate: siteTime,
						beforeShow: function() {
							$(this).prop( 'disabled', true );
						},
						onClose: function() {
							$(this).prop( 'disabled', false );
						}
					});

					datepickers.datepicker('widget').addClass('stream-datepicker');

				}

				predefined.select2({
					'allowClear': true
				});

				predefined.on({
					'change': function () {
						var value = $(this).val(),
							option = predefined.find('[value="' + value + '"]'),
							to_val = option.data('to'),
							from_val = option.data('from');

						if ('custom' === value) {
							dateinputs.show();
							return false;
						}

						from.val(from_val).trigger('change', [true]);
						to.val(to_val).trigger('change', [true]);

						if ( jQuery.datepicker && datepickers.datepicker('widget').is(':visible')) {
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
								predefined.val(option.attr('value')).trigger('change',[true]);
							} else {
								predefined.val('custom').trigger('change',[true]);
							}
						} else if ('' === to.val() && '' === from.val()) {
							predefined.val('').trigger('change',[true]);
						} else {
							predefined.val('custom').trigger('change',[true]);
						}
					}
				});

				from.on({
					'change': function () {

						if ('' !== from.val()) {
							from_remove.show();
							to.datepicker('option', 'minDate', from.val());
						} else {
							from_remove.hide();
						}

						if (arguments[arguments.length-1] === true) {
							return false;
						}

						predefined.trigger('check_options');
					}
				});

				to.on({
					'change': function () {
						if ('' !== to.val()) {
							to_remove.show();
							from.datepicker('option', 'maxDate', to.val());
						} else {
							to_remove.hide();
						}

						if (arguments[arguments.length-1] === true) {
							return false;
						}

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
					start: $wrapper.find('.date-inputs .field-from').val(),
					end: $wrapper.find('.date-inputs .field-to').val()
				};

				// Add params to URL
				$(this).attr('href', $(this).attr('href') + '&' + $.param(data));
			});
		}
	};

	$(document).ready( function() {
		intervals.init( $('.date-interval') );
	});
});
