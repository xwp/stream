/* globals wp_stream_migrate, ajaxurl */
jQuery( function( $ ) {

	$( '#stream-start-migrate' ).on( 'click', function( e ) {
		if ( ! window.confirm( wp_stream_migrate.i18n.confirm_start_migrate ) ) {
			e.preventDefault();
		} else {
			stream_migrate_action( 'migrate' );
		}
	});

	$( '#stream-migrate-reminder' ).on( 'click', function( e ) {
		if ( ! window.confirm( wp_stream_migrate.i18n.confirm_migrate_reminder ) ) {
			e.preventDefault();
		} else {
			stream_migrate_action( 'delay' );
		}
	});

	$( '#stream-delete-records' ).on( 'click', function( e ) {
		if ( ! window.confirm( wp_stream_migrate.i18n.confirm_delete_records ) ) {
			e.preventDefault();
		} else {
			stream_migrate_action( 'delete' );
		}
	});

	function stream_migrate_action( migrate_action ) {
		var data = {
			'action': 'wp_stream_migrate_action',
			'migrate_action': migrate_action,
			'nonce': wp_stream_migrate.nonce
		};

		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: data,
			dataType: 'json',
			beforeSend: function() {
				if ( 'delay' !== migrate_action ) {
					$( '#stream-migrate-progress strong' ).show();
				}

				$( '#stream-migrate-actions' ).hide();
				$( '#stream-migrate-progress' ).show();
			},
			success: function( response ) {
				$( '#stream-migrate-progress .spinner' ).hide();
				$( '#stream-migrate-progress strong' ).hide();
				$( '#stream-migrate-actions-close' ).show();

				if ( false === response.success ) {
					$( '#stream-migrate-progress em' ).text( response.data ).css( 'color', '#a00' );
				} else {
					$( '#stream-migrate-progress em' ).text( response.data );
				}

				$( '#stream-migrate-actions-close' ).on( 'click', function() {
					location.reload( true );
				});
			}
		});
	}

});
