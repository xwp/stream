/* dashboard pagination */
jQuery(function($){

	$( '#dashboard_stream_activity').on( 'click', '.pagination-links a', function(e){
		e.preventDefault();
		var data = {
			'action' : 'stream_activity_dashboard_update',
			'stream-paged' : $(this).data('page'),
		};

		$.post( window.ajaxurl, data, function( response ){
			$( '#dashboard_stream_activity .inside' ).html( response );
		} );
	});



	/**
	 * Dashboard Live Update
	 */
	$( document ).ready( function() {
		wp.heartbeat.interval( 'fast' );

		var widget_sel = '#dashboard_stream_activity';
		var list_sel = widget_sel + ' .inside ul';

		$(document).on( 'heartbeat-send.stream', function(e, data) {
			data['wp-stream-heartbeat'] = 'dashboard-update';
			var last_item = $( list_sel + ' li:first');
			var last_id = 1;
			if ( last_item.length !== 0 ) {
				last_id = ( '' === last_item.attr( 'data-id' ) ) ? 1 : last_item.attr( 'data-id' );
			}
			data['wp-stream-heartbeat-last-id'] = last_id;
		});


		// Listen for "heartbeat-tick" on $(document).
		$(document).on( 'heartbeat-tick.stream', function( e, data ) {

			// If this no rows return then we kill the script
			if ( ! data['wp-stream-heartbeat'] || data['wp-stream-heartbeat'].length === 0 ) {
				return;
			}

			//Prevent updates from being applied directly to pages that aren't the first in the list
			if ( ! $( '#dashboard_stream_activity a.prev-page' ).hasClass( 'disabled' ) ) {
				return;
			}

			// Get all current rows
			var $current_items = $( list_sel + ' li');

			// Get all new rows
			var $new_items = $(data['wp-stream-heartbeat'].replace(/(\r\n|\n|\r)/gm,''));

			// Remove all class to tr added by WP and add new row class
			$new_items.removeClass().addClass('new-row');

			//Check if first tr has the alternate class
			var has_class =  ( $current_items.first().hasClass('alternate') );

			// Apply the good class to the list
			if ( $new_items.length === 1 && !has_class ) {
				$new_items.addClass('alternate');
			} else {
				var even_or_odd = ( $new_items.length%2 === 0 && !has_class ) ? 'even':'odd';
				// Add class to nth child because there is more than one element
				$new_items.filter(':nth-child('+even_or_odd+')').addClass('alternate');
			}

			var item_array = [];
			$( $new_items ).each( function() {
				if ( typeof( $(this).html() ) === 'string' ) {
					item_array.push( $(this) );
				}
			});

			// Remove the number of element added to the end of the list table
			var show_on_screen = data.per_page || 5;
			var slice_items = show_on_screen - ( $new_items.length + $current_items.length );

			if ( slice_items < 0 ) {
				$( list_sel + ' li').slice(slice_items).remove();
			}

			// Remove the no items paragraph
			$( widget_sel + ' p.no-items').remove();

			//Use records per page here
			var added_items = item_array.slice( 0, data.per_page );

			// Add element to the dom
			$(list_sel).prepend( added_items );

			// Update pagination
			var total_items_i18n = data.total_items_i18n || '';
			if ( total_items_i18n ) {
				$( widget_sel + ' .total-pages').text( data.total_pages_i18n );
				$( widget_sel + ' .pagination-links').find('.next-page, .last-page').toggleClass('disabled', data.total_pages === $('.current-page').val());
				$( widget_sel + ' .pagination-links .last-page').attr('data-page', data.total_pages).attr('href', data.last_page_link);
			}

			// Remove background after a certain amount of time
			setTimeout( function() {
				$('.new-row').addClass( 'fadeout' );
				setTimeout( function() {
					$( list_sel + ' li').removeClass('new-row fadeout');
				}, 500);
			}, 3000);

		});

	});

});
