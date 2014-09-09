/* globals wp_stream_migrate, ajaxurl */
jQuery( function( $ ) {

	var chunk_size    = parseInt( wp_stream_migrate.chunk_size, 10 ),
	    record_count  = parseInt( wp_stream_migrate.record_count, 10 ),
	    progress_step = ( chunk_size < record_count ) ? ( chunk_size / record_count ) * 100 : 100,
	    progress_val  = 0;

	$( document ).on( 'click', '#stream-start-migrate', function( e ) {
		if ( ! window.confirm( wp_stream_migrate.i18n.confirm_start_migrate ) ) {
			e.preventDefault();
		} else {
			stream_migrate_action( 'migrate' );
		}
	});

	$( document ).on( 'click', '#stream-resume-migrate', function( e ) {
		e.preventDefault();
		stream_migrate_action( 'migrate' );
	});

	$( document ).on( 'click', '#stream-migrate-reminder', function( e ) {
		if ( ! window.confirm( wp_stream_migrate.i18n.confirm_migrate_reminder ) ) {
			e.preventDefault();
		} else {
			stream_migrate_action( 'delay' );
		}
	});

	$( document ).on( 'click', '#stream-delete-records', function( e ) {
		if ( ! window.confirm( wp_stream_migrate.i18n.confirm_delete_records ) ) {
			e.preventDefault();
		} else {
			stream_migrate_action( 'delete' );
		}
	});

	$( document ).on( 'click', '#stream-migrate-actions-close', function() {
		location.reload( true );
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
				stream_migrate_start( migrate_action );
			},
			success: function( response ) {
				if ( false === response.success ) {
					stream_migrate_end( response.data, true );
				} else {
					if ( 'migrate' === response.data || 'delete' === response.data ) {
						stream_migrate_progress_loop( response.data );
					} else {
						stream_migrate_end( response.data );
					}
				}
			},
			error: function() {
				stream_migrate_end( wp_stream_migrate.i18n.error_message, true );
			}
		});
	}

	function stream_migrate_progress_loop( migrate_action ) {
		progress_val = ( ( progress_step + progress_val ) < 100 ) ? progress_step + progress_val : 100;

		$( '#stream-migrate-progress progress' ).val( progress_val );
		$( '#stream-migrate-progress strong' ).text( Math.round( progress_val ) + '%' );

		stream_migrate_action( migrate_action );
	}

	function stream_migrate_start( migrate_action ) {
		$( '#stream-migrate-actions' ).hide();
		$( '#stream-migrate-progress' ).show();

		if ( 'migrate' === migrate_action ) {
			$( '#stream-migrate-title' ).text( wp_stream_migrate.i18n.migrate_process_title );
			$( '#stream-migrate-message' ).text( wp_stream_migrate.i18n.migrate_process_message );
		}

		if ( 'delete' === migrate_action ) {
			$( '#stream-migrate-title' ).text( wp_stream_migrate.i18n.delete_process_title );
			$( '#stream-migrate-message' ).text( wp_stream_migrate.i18n.delete_process_message );
		}

		if ( 'delay' !== migrate_action ) {
			$( '#stream-migrate-progress strong' ).show();
		}
	}

	function stream_migrate_end( message, is_error ) {
		is_error = 'undefined' !== typeof is_error ? is_error : false;

		$( '#stream-migrate-message' ).hide();
		$( '#stream-migrate-progress progress' ).hide();
		$( '#stream-migrate-progress strong' ).hide();
		$( '#stream-migrate-actions-close' ).show();

		if ( message ) {
			$( '#stream-migrate-progress em' ).html( message );

			if ( is_error ) {
				$( '#stream-migrate-progress em' ).css( 'color', '#a00' );
			}
		}
	}

});
