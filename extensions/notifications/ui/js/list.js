/* globals stream_notifications_options, _ */
jQuery( function( $ ) {

	if ( stream_notifications_options.bulkActions ) {
		var $bulkSelect = $( '.bulkactions select' ),
			opts        = '';

		_.each( stream_notifications_options.bulkActions, function( el, i ) {
			opts += '<option value="%">%</option>'.replace( '%', i ).replace( '%', el );
		});

		$bulkSelect.find( 'option:first' ).after( opts );
	}
});
