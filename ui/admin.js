/* globals confirm, wp_stream, ajaxurl */
jQuery(function($){

	if ( jQuery.datepicker ) {
		$( '.toplevel_page_wp_stream .date-picker' ).datepicker({
			dateFormat: 'yy/mm/dd',
			maxDate: 0,
			beforeShow: function() {
				$(this).prop( 'disabled', true );
			},
			onClose: function() {
				$(this).prop( 'disabled', false );
			}
		});

		var $date_from = $('.toplevel_page_wp_stream #date_from'),
			$date_to   = $('.toplevel_page_wp_stream #date_to'),
			currentDateFrom,
			currentDateTo;

		$date_from.change( function() {
			currentDateFrom = $(this).datepicker( 'getDate' );
			$date_to.datepicker( 'option', 'minDate', currentDateFrom );
		});

		$date_to.change( function() {
			currentDateTo = $(this).datepicker( 'getDate' );
			$date_from.datepicker( 'option', 'maxDate', currentDateTo );
		});
	}

	$( '.toplevel_page_wp_stream select.chosen-select' ).select2({
			minimumResultsForSearch: 10,
			formatResult: function (record) {
				var result = '';

				if ( undefined !== $(record.element).attr('data-icon') ) {
					result += '<img src="' + $(record.element).attr('data-icon') + '" class="wp-stream-select2-icon">';
				}

				result += record.text;

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
		var $ip_regex = /^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/;
		$input.select2({
			tags:$input.data('selected'),
			width:350,
			query: function (query){
				var data = {results: []};
				if(typeof (query.term) !== 'undefined' && query.term.match($ip_regex) != null ){
					data.results.push({id: query.term, text: query.term });
				}
				query.callback(data);
			},
			initSelection: function (item, callback) {
				callback( item.data( 'selected' ) );
			},
			formatNoMatches : function(){
				return '';
			}
		}).on('change',function(e){
			stream_select2_change_handler( e , $input );
		}).trigger('change');
	});
	var $input_user;
	$('.stream_page_wp_stream_settings input[type=hidden].select2-select.authors_and_roles').each(function (k, el) {
		$input_user = $(el);
		var $roles = $input_user.data('values');
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
						'find': term,
						'limit': 10,
						'pager': page,
						'action': 'stream_get_users',
						'nonce' : $input_user.data('nonce')
					};
				},
				results: function (response) {
					var answer = {
						results: [
							{
								text: 'Roles',
								children: $roles
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
						if ($.contains($roles, user.id)){
							user.disabled = true;
						}
					});
					answer.results[1].children = response.data.users;
					// notice we return the value of more so Select2 knows if more results can be loaded
					return answer;
				}
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
			if ( ! data['wp-stream-heartbeat'] ) {
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
			var user	= $( '#enable_live_update_user' ).val();
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

});
