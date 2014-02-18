/* global jQuery, ajaxurl */
(function( window, $ ){
	'use strict';

	$(document).ready(function(){
		$('#stream-add-section').click(function(){
			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: {
					action: 'stream_reports_add_metabox',
					stream_report_nonce : $('#stream_report_nonce').val()
				},
				dataType: 'json',
				beforeSend : function() {
					//@todo
				},
				success : function( response ) {
					if(response.success){
						location.reload();
					}
				}
			});
		});
	});

})( window, jQuery.noConflict() );
