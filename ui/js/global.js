/* globals jQuery, wp_stream_global */
/* exported wp_stream_regenerate_alt_rows */
jQuery(
	function( $ ) {
		// List table actions, ignores filtering
		$( '.actions :submit:not([name="filter_action"])' ).on(
			'click', function( e ) {
				if ( $( 'table.widefat tbody :checkbox:checked' ).length > wp_stream_global.bulk_actions.threshold ) {
					warning_message( e );
				}
			}
		);

		// Post type empty trash
		$( '#delete_all' ).on(
			'click', function( e ) {
				var trash_count = parseInt( $( 'ul.subsubsub li.trash .count' ).text().replace( /\D/g, '' ), 10 );

				if ( trash_count > wp_stream_global.bulk_actions.threshold ) {
					warning_message( e );
				}
			}
		);

		function warning_message( e ) {
			if ( ! window.confirm( wp_stream_global.bulk_actions.i18n.confirm_action ) ) {
				e.preventDefault();
			}
		}
	}
);

// Regenerate alternating row classes
var wp_stream_regenerate_alt_rows = function( $rows ) {
	if ( ! $rows.length ) {
		return false;
	}

	$rows.removeClass( 'alternate' );

	$rows.each(
		function( index ) {
			jQuery( this ).addClass( index % 2 ? '' : 'alternate' );
		}
	);
};
