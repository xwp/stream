/* global ajaxurl, tb_remove, alert, stream_activation, tb_show, alert, console */
jQuery(function($){

	var
		i = $('<iframe>').appendTo('body'),
		spinner = $('h2 .spinner'),
		login_first = function() {
			var url = ( stream_activation.action === 'connect' ) ? stream_activation.api.connect : stream_activation.api.disconnect;
			url += '&site=' + window.location.host;
			tb_show( stream_activation.i18n.login_to_stream, url + '&modal=1#TB_iframe?height=400&amp;width=350&amp;inlineId=hiddenModalContent', null );
		},
		receive = function( message ) {
			if ( typeof message !== 'string' || ! message.match(/^stream:/) ) {
				return;
			}
			console && console.debug( message );
			var data = $.map(
				message
					.replace(/^(stream:)/, '')
					.split('&'),
				function( i ) {
					return i.split('=');
				}
				);
			data[1] = decodeURIComponent(
				( data[1] + '' )
				.replace( /\+/g, '%20' )
				);
			if ( data[0] === 'error' ) {
				alert( data[1] );
			} else if( data[0] === 'login' ) {
				login_first();
			} else if( data[0] === 'license' ) {
				got_license( data[1] );
			} else if( data[0] === 'disconnected' ) {
				disconnect();
			}
		},
		got_license = function( license ) {
			console && console.debug( 'Got license: ', license );
			$.ajax( {
				url: ajaxurl,
				type: 'post',
				data: { action: 'stream-license-check', license: license, nonce: stream_activation.nonce.license_check },
				dataType: 'json',
				success: function( r ) {
					tb_remove();
					console && console.debug( 'Got license verification results: ', r );
					spinner.hide();
					if ( r.success ) {
						alert( r.data.message );
						window.location.reload();
					} else {
						alert( r.data );
					}
				}
			} );
		},
		disconnect = function() {
			console && console.debug ( 'Disconnected from mothership, removing local license.' );
			$.ajax( {
				url: ajaxurl,
				type: 'post',
				data: { action: 'stream-license-remove', nonce: stream_activation.nonce.license_remove },
				dataType: 'json',
				success: function(r) {
					spinner.hide();
					if ( r.success ) {
						alert( r.data.message );
						console && console.debug( 'Removed license locally, refreshing page.' );
						window.location.reload();
					}
				}
			} );
		}
	;



	if ( typeof window.postMessage !== 'undefined' ) {
		if ( typeof window.boundMessageListner === 'undefined' ) {
			// Receive postMessage
			window.addEventListener( 'message', function(event) { receive( event.data ); }, true);
			window.boundMessageListner = true;
		}
	} else {
		// TODO: Fall back
	}

	$('a[data-stream-connect]').click( function(e) {
		e.preventDefault();
		spinner.css({ display: 'inline-block' });
		i.attr( 'src', stream_activation.api.connect );
	});

	$('a[data-stream-disconnect]').click( function(e) {
		e.preventDefault();
		spinner.css({ display: 'inline-block' });
		i.attr( 'src', stream_activation.api.disconnect );
	});

});
