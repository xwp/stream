/* globals jQuery, streamAlerts, JSON */
jQuery( function( $ ) {
	$( '.select2-select.connector_or_context' ).each( function( k, el ) {
		$( el ).select2({
			allowClear: true,
			placeholder: streamAlerts.anyContext,
			templateResult: function( item ) {
				if ( 'undefined' === typeof item.id ) {
					return item.text;
				}
				if ( -1 === item.id.indexOf( '-' ) ) {
					return $( '<span class="parent">' + item.text + '</span>' );
				} else {
					return $( '<span class="child">' + item.text + '</span>' );
				}
			},
			matcher: function( params, data ) {
				var match = $.extend( true, {}, data );

				if ( null == params.term || '' === $.trim( params.term ) ) {
					return match;
				}

				var term = params.term.toLowerCase();

				match.id = match.id.replace( 'blogs', 'sites' );
				if ( match.id.toLowerCase().indexOf( term ) >= 0 ) {
					return match;
				}

				if ( match.children ) {
					for ( var i = match.children.length - 1; i >= 0; i-- ) {
						var child = match.children[i];

						// Remove term from results if it doesn't match.
						if ( -1 === child.id.toLowerCase().indexOf( term ) ) {
							match.children.splice( i, 1 );
						}
					}

					if ( match.children.length > 0 ) {
						return match;
					}
				}

				return null;
			}
		}).change( function() {
			var value = $( this ).val();
			if ( value ) {
				var parts = value.split( '-' );
				$( this ).siblings( '.connector' ).val( parts[0] );
				$( this ).siblings( '.context' ).val( parts[1] );
				$( this ).removeAttr( 'name' );
			}
		});

		var parts = [
			$( el ).siblings( '.connector' ).val(),
			$( el ).siblings( '.context' ).val()
		];
		if ( '' === parts[1] ) {
			parts.splice( 1, 1 );
		}
		$( el ).val( parts.join( '-' ) ).trigger( 'change' );
	});

	$( '.select2-select:not(.connector_or_context)' ).each( function() {
		var element_id_split = $( this ).attr( 'id').split( '_' );
		var select_name = element_id_split[ element_id_split.length - 1 ].charAt(0).toUpperCase() +
			element_id_split[ element_id_split.length - 1 ].slice(1);
		$( this ).select2( {
			allowClear: true,
			placeholder: streamAlerts.any + ' ' + select_name
		});
	});

	var $alertSettingSelect = $( '#wp_stream_alert_type' ),
		$alertSettingForm   = $( '#wp_stream_alert_type_form' );

	var loadAlertSettings = function( alert_type ) {
		var data = {
			'action'     : 'load_alerts_settings',
			'alert_type' : alert_type,
			'post_id'    : $( '#post_ID' ).val()
		};

		$( '#wp_stream_alerts_triggers input.wp_stream_ajax_forward' ).each( function() {
			data[ $( this ).attr( 'name' ) ] = $( this ).val();
		} );

		$.post( window.ajaxurl, data, function( response ) {
			$alertSettingForm.html( response.data.html );
		});
	};

	var $alertTriggersSelect = $( '#wp_stream_alerts_triggers select.wp_stream_ajax_forward' ),
		$alertPreviewTable   = $( '#wp_stream_alerts_preview .inside' );

	$alertTriggersSelect.change( function() {
		loadAlertPreview();
		if ( 'wp_stream_trigger_connector_or_context' === $(this).attr('id') ) {
			var connector = $(this).val();
			if ( 0 < connector.indexOf('-') ) {
				var connector_split = connector.split('-');
				connector = connector_split[0];
			}
			updateActions( connector );
		}
	});

	var loadAlertPreview = function() {
		var data = {
			'action' : 'load_alert_preview',
			'post_id': $( '#post_ID' ).val()
		};

		$( '#wp_stream_alerts_triggers input.wp_stream_ajax_forward' ).each( function() {
			data[ $( this ).attr( 'name' ) ] = $( this ).val();
		});

		$.post( window.ajaxurl, data, function( response ) {
			$alertPreviewTable.html( response.data.html );
		});
	};
	var updateActions = function( connector ) {
		var trigger_action = $('#wp_stream_trigger_action');
		trigger_action.empty();
		trigger_action.prop('disabled', true);

		var placeholder = $('<option/>', {value: '', text: ''});
		trigger_action.append( placeholder );

		var data = {
			'action'    : 'update_actions',
			'connector' : connector
		};

		$.post( window.ajaxurl, data, function( response ) {
			var json_response = JSON.parse( response );
			$.each( json_response, function( index, value ) {
				var option = $('<option/>', { value: index, text: value } );
				trigger_action.append( option );
				trigger_action.select2( 'data', { id: index, text: value } );
			});
			trigger_action.prop('disabled', false);
		});
	};

	$alertSettingSelect.change( function() {
		loadAlertSettings( $( this ).val() );
	});
});
