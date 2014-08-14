/* globals wp_stream_sync, ajaxurl */
jQuery( function( $ ) {

	$( '#stream-start-sync' ).on( 'click', function( e ) {
		if ( ! window.confirm( wp_stream_sync.i18n.confirm_start_sync ) ) {
			e.preventDefault();
		} else {
			stream_sync_action( 'sync' );
		}
	});

	$( '#stream-sync-reminder' ).on( 'click', function( e ) {
		if ( ! window.confirm( wp_stream_sync.i18n.confirm_sync_reminder ) ) {
			e.preventDefault();
		} else {
			stream_sync_action( 'delay' );
		}
	});

	$( '#stream-delete-records' ).on( 'click', function( e ) {
		if ( ! window.confirm( wp_stream_sync.i18n.confirm_delete_records ) ) {
			e.preventDefault();
		} else {
			stream_sync_action( 'delete' );
		}
	});

	function stream_sync_action( sync_action ) {
		var data = {
			'action': 'wp_stream_sync_action',
			'sync_action': sync_action,
			'nonce': wp_stream_sync.nonce
		};

		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: data,
			dataType: 'json',
			beforeSend: function() {
				if ( 'delay' !== sync_action ) {
					$( '#stream-sync-progress strong' ).show();
				}

				$( '#stream-sync-actions' ).hide();
				$( '#stream-sync-progress' ).show();
			},
			success: function( response ) {
				$( '#stream-sync-progress .spinner' ).hide();
				$( '#stream-sync-progress strong' ).hide();
				$( '#stream-sync-actions-close' ).show();

				if ( false === response.success ) {
					$( '#stream-sync-progress em' ).text( response.data ).css( 'color', '#a00' );
				} else {
					$( '#stream-sync-progress em' ).text( response.data );
				}

				$( '#stream-sync-actions-close' ).on( 'click', function() {
					location.reload( true );
				});
			}
		});
	}

});
