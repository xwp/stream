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

});
