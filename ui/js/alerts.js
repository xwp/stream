/* globals jQuery, streamAlerts, JSON */
jQuery( function( $ ) {
	var loadAlertSettings, updateActions,
		$alertSettingSelect = $( '#wp_stream_alert_type' ),
		$alertSettingForm = $( '#wp_stream_alert_type_form' ),
		$alertTriggersSelect = $( '#wp_stream_alerts_triggers select.wp_stream_ajax_forward' );

	$( '.select2-select.connector_or_context' ).each( function( k, el ) {
		var parts;
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
				var term, child, i, match = $.extend( true, {}, data );

				if ( null == params.term || '' === $.trim( params.term ) ) {
					return match;
				}

				term = params.term.toLowerCase();

				match.id = match.id.replace( 'blogs', 'sites' );
				if ( match.id.toLowerCase().indexOf( term ) >= 0 ) {
					return match;
				}

				if ( match.children ) {
					for ( i = match.children.length - 1; i >= 0; i-- ) {
						child = match.children[i];

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
			var parts, value = $( this ).val();
			if ( value ) {
				parts = value.split( '-' );
				$( this ).siblings( '.connector' ).val( parts[0] );
				$( this ).siblings( '.context' ).val( parts[1] );
				$( this ).removeAttr( 'name' );
			}
		});

		parts = [
			$( el ).siblings( '.connector' ).val(),
			$( el ).siblings( '.context' ).val()
		];
		if ( '' === parts[1] ) {
			parts.splice( 1, 1 );
		}
		$( el ).val( parts.join( '-' ) ).trigger( 'change' );
	});

	$( '.select2-select:not(.connector_or_context)' ).each( function() {
		var elementIdSplit = $( this ).attr( 'id' ).split( '_' ),
			selectName = elementIdSplit[ elementIdSplit.length - 1 ].charAt( 0 ).toUpperCase() +
			elementIdSplit[ elementIdSplit.length - 1 ].slice( 1 );
		$( this ).select2( {
			allowClear: true,
			placeholder: streamAlerts.any + ' ' + selectName
		});
	});

	loadAlertSettings = function( alertType ) {
		var data = {
			'action': 'load_alerts_settings',
			'alert_type': alertType,
			'post_id': $( '#post_ID' ).val()
		};

		$( '#wp_stream_alerts_triggers input.wp_stream_ajax_forward' ).each( function() {
			data[ $( this ).attr( 'name' ) ] = $( this ).val();
		} );

		$.post( window.ajaxurl, data, function( response ) {
			$alertSettingForm.html( response.data.html );
		});
	};

	$alertTriggersSelect.change( function() {
		var connectorSplit, connector;
		if ( 'wp_stream_trigger_connector_or_context' === $( this ).attr( 'id' ) ) {
			connector = $( this ).val();
			if ( 0 < connector.indexOf( '-' ) ) {
				connectorSplit = connector.split( '-' );
				connector = connectorSplit[0];
			}
			updateActions( connector );
		}
	});

	updateActions = function( connector ) {
		var placeholder, data, triggerAction = $( '#wp_stream_trigger_action' );
		triggerAction.empty();
		triggerAction.prop( 'disabled', true );

		placeholder = $( '<option/>', { value: '', text: '' } );
		triggerAction.append( placeholder );

		data = {
			'action': 'update_actions',
			'security': streamAlerts.security,
			'connector': connector
		};

		$.post( window.ajaxurl, data, function( response ) {
			var jsonResponse = JSON.parse( response );
			$.each( jsonResponse, function( index, value ) {
				var option = $( '<option/>', { value: index, text: value } );
				triggerAction.append( option );
				triggerAction.select2( 'data', { id: index, text: value } );
			});
			triggerAction.prop( 'disabled', false );
		});
	};

	$alertSettingSelect.change( function() {
		loadAlertSettings( $( this ).val() );
	});
});
