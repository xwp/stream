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

		var listSel = '#dashboard_stream_activity .inside ul';

		$(document).on( 'heartbeat-send.stream', function(e, data) {
			data['wp-stream-heartbeat'] = 'dashboard-update';
			var lastId = $( listSel + ' li:first').attr( 'data-id' );
			lastId = ( '' === lastId ) ? 1 : lastId;
			data['wp-stream-heartbeat-last-id'] = lastId;
		});


		// Listen for "heartbeat-tick" on $(document).
		$(document).on( 'heartbeat-tick.stream', function( e, data ) {

			// If this no rows return then we kill the script
			if ( ! data['wp-stream-heartbeat'] || data['wp-stream-heartbeat'].length == 0 ) {
				return;
			}

			if( ! $( '#dashboard_stream_activity a.prev-page' ).hasClass( 'disabled' ) ) {
				return;
			}

			// Get all new rows
			var $new_items = $(data['wp-stream-heartbeat']);

			// Remove all class to tr added by WP and add new row class
			$new_items.removeClass().addClass('new-row');

			//Check if first tr has the alternate class
			var has_class =  ( $( listSel + ' li:first').hasClass('alternate') );

			// Apply the good class to the list
			if ( $new_items.length === 1 && !has_class ) {
				$new_items.addClass('alternate');
			} else {
				var even_or_odd = ( $new_items.length%2 === 0 && !has_class ) ? 'even':'odd';
				// Add class to nth child because there is more than one element
				$new_items.filter(':nth-child('+even_or_odd+')').addClass('alternate');
			}

			// Add element to the dom
			$(listSel).prepend( $new_items );

			$( listSel + ' li:last-child').hide();

			// Remove background after a certain amount of time
			setTimeout( function() {
				$('.new-row').addClass( 'fadeout' );
				setTimeout( function() {
					$( listSel + ' li').removeClass('new-row fadeout');
				}, 500);
			}, 3000);

		});

	});

});
