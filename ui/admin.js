/* globals confirm, wp_stream, ajaxurl */
jQuery(function($){

	if ( jQuery.datepicker ) {
		$( '.toplevel_page_wp_stream .date-picker' ).datepicker({
			dateFormat: 'yy/mm/dd',
			maxDate: 0
		});
	}

	$( '.toplevel_page_wp_stream select.chosen-select' ).select2({
			minimumResultsForSearch: 10,
			allowClear: true,
			width: '165px'
		});

	$( '.toplevel_page_wp_stream input[type=hidden].chosen-select' ).select2({
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
		currentHash    = window.location.hash ? window.location.hash.match(/\d+/)[0] : defaultIndex,
		syncFormAction = function( index ) {
			var $optionsForm   = $('input[name="option_page"][value="wp_stream"]').parent('form');
			var currentAction  = $optionsForm.attr('action');

			$optionsForm.prop('action', currentAction.replace( /(^[^#]*).*$/, '$1#' + index ));
		};

	$tabs.on('click', 'a', function(e){
		e.preventDefault();
		var index = $tabs.find('a').index( $(this) );
		$panels.hide().eq(index).show();
		$tabs.find('a').removeClass('nav-tab-active').filter($(this)).addClass('nav-tab-active');
		window.location.hash = index;
		syncFormAction(index);
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
			data['wp-stream-heartbeat']         = 'live-update';
			var last_id = $( list_sel + ' tr:first .column-id').text();
			last_id = ( '' === last_id ) ? 1 : last_id;
			data['wp-stream-heartbeat-last-id'] = last_id;
			data['wp-stream-heartbeat-query'] = wp_stream.current_query;
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
			var user    = $( '#enable_live_update_user' ).val();
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


(function ( window, $, _ ) {
	$( document ).on({
		'ready' : function ( event ) {
			var $page = $( '.wp_stream_screen' );

			// Rather use each thingy as one scope, to avoid conflicts...
			(function( event, $, _, $page ){
				'use strict';
				if ( $page.lenght === 0 ){
					return;
				}

				var $fields = $page.find( '.user_n_role_select' );

				$fields.each(function( k, el ){
					var $input = $(el),
						$placeholder = $input.next( '.user_n_role_select_placeholder' ),
						roles = $input.data('roles'),
						l10n = $input.data( 'localization' );

					$input.select2({
						multiple: true,
						formatSelection: function (object, container){
							var template;
							if ( $.isNumeric( object.id ) ){
								var title_template = _.template('<%= email %> (ID: <%= id %>)');
								container.parents('.select2-search-choice').attr( 'title', title_template(object) );

								template = _.template('<%= display_name %><i class="icon16 icon-users"></i>');
							} else {
								template = _.template('<%= text %>');
							}
							return template(object);
						},
						formatResult: function (object, label){
							var template;
							if ( $.isNumeric( object.id ) ){
								var title_template = _.template('<%= email %> (ID: <%= id %>)');
								label.attr( 'title', title_template(object) );

								template = _.template('<%= display_name %>');
							} else {
								template = _.template('<%= text %>');
							}
							return template(object);
						},
						ajax: {
							type: 'POST',
							url: ajaxurl,
							dataType: 'json',
							quietMillis: 500,
							data: function (term, page) { // page is the one-based page number tracked by Select2
								return {
									'find': term, //search term
									'limit': 25, // page size
									'page': page, // page number
									'action': 'stream_find_user'
								};
							},
							results: function (data, page) {
								var answer = {
										results:[
											{
												text: l10n.roles,
												children: roles
											},
											{
												text: l10n.users,
												children: []
											}
										]
									};

								if ( data === 0 || data === '' || data.status !== true ){
									return answer;
								}

								$.each( data.users, function ( k, user ){
									if ( _.contains( roles, user.id ) ){
										user.disabled = true;
									}
								} );

								if ( page === 1 ){
									answer.results[1].children = data.users;
								} else {
									answer.results = [
										{
											text: '',
											children: data.users
										}
									];
								}

								if ( (page * 25) <= data.total && data.total !== 0 ){
									answer.more = true;
								} else {
									answer.more = false;
								}

								// notice we return the value of more so Select2 knows if more results can be loaded
								return answer;
							}
						},
						initSelection: function (item, callback) {
							callback( item.data( 'selected' ) );
						}
					}).on({
						'change' : function (e){
							$input.siblings( '.user_n_role_select_value' ).off().remove();

							if ( typeof e.val === 'undefined' ){
								e.val = $input.val().split( ',' );
							}

							_.each( e.val.reverse(), function( value ){
								if ( value === '__placeholder__' ){
									return;
								}
								$placeholder.after( $placeholder.clone( true ).attr( 'class', 'user_n_role_select_value' ).val( value ) );
							});
						}
					});

				});

			})( event, $, _, $page );
		}
	});
})( window, jQuery.noConflict(), _.noConflict() );