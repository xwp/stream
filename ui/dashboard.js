/* dashboard pagination */
jQuery(function($){ 

	var dashboardBind = function(){
		$( '#dashboard_stream_activity .pagination-links a').click(function(e){
			e.preventDefault();
			var data = {
				'action' : 'stream_dashboard_update',
				'stream-paged' : $(this).data('page'),
			};
	
			$.post( ajaxurl, data, function( response ){
				$( '#dashboard_stream_activity .inside' ).html( response );
				dashboardBind();
			} );
		} );
	};

	dashboardBind();

});
