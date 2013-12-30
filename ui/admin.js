/* globals confirm, wp_stream */
jQuery(function($){

	if ( jQuery.datepicker ) {
		$( '.toplevel_page_wp_stream .date-picker' ).datepicker({
			dateFormat: 'yy/mm/dd',
			maxDate: 0
		});
	}

	$( '.toplevel_page_wp_stream .chosen-select' ).select2({
		minimumResultsForSearch: 10,
		allowClear: true,
		width: '165px'
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


	$(document).ready( function() {

		// Heartbeat for Live Updates

		// Set initial beat to fast.  WP is designed to slow this to 15 seconds after 2.5 minutes.
		wp.heartbeat.interval( 'fast' );

		$(document).on( 'heartbeat-send.stream', function(e, data) {
			console.log( 'sent' );
			data['wp-stream-heartbeat'] = 'live-update';
		});

		// Listen for "heartbeat-tick" on $(document).
		$(document).on( 'heartbeat-tick.stream', function( e, data, textStatus, jqXHR ) {

			if ( typeof console != 'undefined' ) {
				// Show debug info
				wp.heartbeat.debug = true;
			}

			if ( ! data['rows'] ) {
				console.log( data['log'] );
				return;
			}

			$( '.toplevel_page_wp_stream #the-list' ).prepend( data['rows'] );

			setTimeout( function() {
				$('.new-row').addClass( 'fadeout' );
			}, 4000);

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
				type: "POST",
				url: wp_stream.ajaxurl,
				data: { action: "stream_enable_live_update", nonce : nonce, user : user, checked : checked },
				dataType: "json",
				beforeSend : function() {
					$( '.stream-live-update-checkbox .spinner' ).show().css( { 'display' : 'inline-block' } );
				},
				success : function( response ) {
					console.log( response.data );
					$( '.stream-live-update-checkbox .spinner' ).hide();
				}
				//error : function( j, t, e ) {
				//	console.log( j.responseText );
				//}
			});
		});




	});

});
