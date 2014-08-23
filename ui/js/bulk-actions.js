/* globals wp_stream_bulk_actions */
jQuery( function( $ ) {

	// List table actions, ignores filtering
	$( '.actions :submit:not([name="filter_action"])' ).on( 'click', function( e ) {
		if ( $( 'table.widefat tbody :checkbox:checked' ).length > wp_stream_bulk_actions.threshold ) {
			warning_message( e );
		}
	});

	// Post type empty trash
	$( '#delete_all' ).on( 'click', function( e ) {
		var trash_count = parseInt( $( 'ul.subsubsub li.trash .count' ).text().replace( /\D/g, '' ), 10 );

		if ( trash_count > wp_stream_bulk_actions.threshold ) {
			warning_message( e );
		}
	});

	function warning_message( e ) {
		if ( ! window.confirm( wp_stream_bulk_actions.i18n.confirm_action ) ) {
			e.preventDefault();
		}
	}

	// Content import
	$( '#import-upload-form :submit' ).on( 'click', function( e ) {
		e.preventDefault();
		window.alert( wp_stream_bulk_actions.i18n.confirm_import );
		window.location.href = wp_stream_bulk_actions.plugins_screen_url;
	});

});
