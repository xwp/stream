/* globals ajaxurl, wp_stream_bulk_actions */
jQuery(function( $ ) {

	var threshold = 100;

	// List table actions, ignores filtering
	$( '.actions :submit:not([name="filter_action"])' ).on( 'click', function( e ) {
		if ( $( '.check-column :checkbox:checked' ).length > threshold ) {
			warning_message( e );
		}
	});

	// Post type empty trash
	$( '#delete_all' ).on( 'click', function( e ) {
		var trash_count = $( 'ul.subsubsub li.trash .count' ).text().replace( /\D/g, '' );

		if ( trash_count > threshold ) {
			warning_message( e );
		}
	});

	function warning_message( e ) {
		if ( ! window.confirm( wp_stream_bulk_actions.i18n.confirm_bulk_action ) ) {
			e.preventDefault();
		}
	}

});
