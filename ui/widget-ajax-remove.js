(function($) {

/*
If the mouse is "unclicked" (mouseup) while it has the class "Deleting"
(Added by WP's droppable js), fire ajax to log the event.
*/
$(document).ready(function() {
	$('.widget').mouseup( function() {
		if ( $( this ).hasClass( 'deleting' ) ) {
			console.log( "Oh Yeah!" );
			var widgetId = $( this ).attr( 'id' );
			//send ajax
			$.ajax({
				type: 'post',
				dataType: 'json',
				url: streamWidgetRemove.ajaxurl,
				data: { action : 'remove_widget_via_droppable', widget_id : widgetId, },
				success: function( response ) {
					console.log( 'widget removal success');
				},
				complete: function() {
					console.log( 'widget removal complete');
				},
				error: function( j, t, e ) {
					console.log( j.responseText );
				}
			});
		}
	});
});

})(jQuery);
