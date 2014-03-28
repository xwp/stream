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
	} );

	$( document ).ready( function() {
	//Enable Live Update Checkbox Ajax
		$( '#enable_live_update' ).click( function() {
			var nonce   = $( '#enable_live_update_nonce' ).val();
			var user	= $( '#enable_live_update_user' ).val();
			var page	= $( this ).attr( 'data-page' );
			console.log( page );
			var checked = 'unchecked';
			if ( $('#enable_live_update' ).is( ':checked' ) ) {
				checked = 'checked';
			}

			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: { action: 'stream_enable_live_update', nonce : nonce, user : user, page : page, checked : checked },
				dataType: 'json',
				beforeSend : function() {
					$( '.stream-live-update-checkbox .spinner' ).show().css( { 'display' : 'inline-block' } );
				},
				success : function() {
					$( '.stream-live-update-checkbox .spinner' ).hide();
				}
			});
		});
	});

});
