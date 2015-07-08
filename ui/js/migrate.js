/* globals wp_stream_migrate, ajaxurl */
jQuery( function( $ ) {

	var chunks        = parseInt( wp_stream_migrate.chunks, 10 ),
	    progress_step = ( chunks > 1 ) ? 100 / chunks : 100,
	    progress_val  = 0;

	$( document ).on( 'click', '#stream-start-migrate', function( e ) {
		if ( ! window.confirm( wp_stream_migrate.i18n.confirm_start_migrate ) ) {
			e.preventDefault();
		} else {
			stream_migrate_action( 'migrate' );
		}
	});

	$( document ).on( 'click', '#stream-migrate-reminder', function( e ) {
		if ( ! window.confirm( wp_stream_migrate.i18n.confirm_migrate_reminder ) ) {
			e.preventDefault();
		} else {
			stream_migrate_action( 'delay' );
		}
	});

	$( document ).on( 'click', '#stream-ignore-migrate', function( e ) {
		if ( ! window.confirm( wp_stream_migrate.i18n.confirm_ignore_migrate ) ) {
			e.preventDefault();
		} else {
			stream_migrate_action( 'ignore' );
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
					if ( 'migrate' === response.data || 'continue' === response.data ) {
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
		$( '#stream-migrate-blog-link' ).hide();
		$( '#stream-migrate-progress' ).show();

		if ( 'migrate' !== migrate_action && 'continue' !== migrate_action ) {
			$( '#stream-migrate-title' ).text( wp_stream_migrate.i18n.ignore_migrate_title );
			$( '#stream-migrate-message' ).hide();
			$( '#stream-migrate-progress progress' ).hide();
			$( '#stream-migrate-progress strong' ).hide();
		}

		if ( 'migrate' === migrate_action || 'continue' === migrate_action ) {
			$( '#stream-migrate-title' ).text( wp_stream_migrate.i18n.migrate_process_title );
			$( '#stream-migrate-message' ).text( wp_stream_migrate.i18n.migrate_process_message );
			$( '#stream-migrate-progress progress' ).show();
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
