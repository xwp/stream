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
			if ( ! data['wp-stream-heartbeat'] || data['wp-stream-heartbeat'].length === 0 ) {
				return;
			}

			//Prevent updates from being applied directly to pages that aren't the first in the list
			if ( ! $( '#dashboard_stream_activity a.prev-page' ).hasClass( 'disabled' ) ) {
				return;
			}

			// Get all new rows
			var $new_items = $(data['wp-stream-heartbeat']);

			var itemArray = [];
			$( $new_items ).each( function() {
				if ( typeof( $(this).html() ) === 'string' ) {
					itemArray.push( '<li class="new-row">' + $(this).html() + '</li>' );
				}
			});

			//Use records per page here
			var addedItems = itemArray.slice( 0, data.per_page );

			// Add element to the dom
			$(listSel).prepend( addedItems );

			//hide last visible element in list (will appear on next page)
			// Remove the number of element added to the end of the list table

			$( listSel + ' li').slice(-itemArray.length).hide();

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
