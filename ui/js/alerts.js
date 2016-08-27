/* globals jQuery, streamAlerts, JSON */
jQuery( function( $ ) {
	'use strict';
	var setupSelectTwo = function setupSelectTwo() {
		var the_list = $('#the-list');
		the_list.find('.select2-select.connector_or_context').each(function (k, el) {
			$(el).select2({
				allowClear: true,
				placeholder: streamAlerts.anyContext,
				templateResult: function (item) {
					if ('undefined' === typeof item.id) {
						return item.text;
					}
					if (-1 === item.id.indexOf('-')) {
						return $('<span class="parent">' + item.text + '</span>');
					} else {
						return $('<span class="child">' + item.text + '</span>');
					}
				},
				matcher: function (params, data) {
					var match = $.extend(true, {}, data);

					if (null === params.term || '' === $.trim(params.term)) {
						return match;
					}

					var term = params.term.toLowerCase();

					match.id = match.id.replace('blogs', 'sites');
					if (match.id.toLowerCase().indexOf(term) >= 0) {
						return match;
					}

					if (match.children) {
						for (var i = match.children.length - 1; i >= 0; i--) {
							var child = match.children[i];

							// Remove term from results if it doesn't match.
							if (-1 === child.id.toLowerCase().indexOf(term)) {
								match.children.splice(i, 1);
							}
						}

						if (match.children.length > 0) {
							return match;
						}
					}

					return null;
				}
			}).change(function () {
				var value = $(this).val();
				if (value) {
					var parts = value.split('-');
					$(this).siblings('.connector').val(parts[0]);
					$(this).siblings('.context').val(parts[1]);
					$(this).removeAttr('name');
				}
			});

			var parts = [
				$(el).siblings('.connector').val(),
				$(el).siblings('.context').val()
			];
			if ('' === parts[1]) {
				parts.splice(1, 1);
			}
			$(el).val(parts.join('-')).trigger('change');
		});

		the_list.find('.select2-select:not(.connector_or_context)').each(function () {
			var element_id_split = $(this).attr('id').split('_');
			var select_name = element_id_split[element_id_split.length - 1].charAt(0).toUpperCase() +
				element_id_split[element_id_split.length - 1].slice(1);
			$(this).select2({
				allowClear: true,
				placeholder: streamAlerts.any + ' ' + select_name
			});
		});
	};
	setupSelectTwo();
	var $alertSettingSelect = $( '#wp_stream_alert_type' );

	var loadAlertSettings = function( alert_type ) {
		var data = {
			'action'     : 'load_alerts_settings',
			'alert_type' : alert_type
		};

		$( '#wp_stream_alerts_triggers' ).find('input.wp_stream_ajax_forward').each( function() {
			data[ $( this ).attr( 'name' ) ] = $( this ).val();
		} );
		$.post( window.ajaxurl, data, function( response ) {
			$( '#wp_stream_alert_type_form' ).html( response.data.html );
		});
	};

	$( '#the-list' ).on('change', '#wp_stream_trigger_connector_or_context', function() {
		if ( 'wp_stream_trigger_connector_or_context' === $(this).attr('id') ) {
			var connector = $(this).val();
			if ( 0 < connector.indexOf('-') ) {
				var connector_split = connector.split('-');
				connector = connector_split[0];
			}
			updateActions( connector );
		}
	});

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
	var alert_form_html = '';
	var data = {
		'action':          'get_new_alert_triggers_notifications'
	};
	$.post( window.ajaxurl, data, function( response ) {
		if ( true === response.success ) {
			alert_form_html = response.data.html;
			$( 'tbody#the-list' ).prepend( '<tr id="add-new-alert" class="inline-edit-row inline-edit-row-page inline-edit-page quick-edit-row quick-edit-row-page inline-edit-page inline-editor" style=""><td colspan="5" class="colspanchange">' + alert_form_html + '<p class="submit inline-edit-save"> <button type="button" class="button-secondary cancel alignleft">Cancel</button> <input type="hidden" id="_inline_edit" name="_inline_edit" value="3550d271fe"> <button type="button" class="button-primary save alignright">Update</button> <span class="spinner"></span><span class="error" style="display:none"></span> <br class="clear"></p></td></tr>');
			$('#add-new-alert').hide();
			setupSelectTwo();
		}
	});

	$( '#wpbody-content' ).on( 'click', 'a.page-title-action', function( e ) {
		var add_new_alert = $( '#add-new-alert' );
		e.preventDefault();
		if ( ! add_new_alert.is( ':visible' ) ) {

			add_new_alert.show();
			var current_bg_color = add_new_alert.css( 'background-color' );

			// Color taken from /wp-admin/css/forms.css
			// #pass-strength-result.strong
			add_new_alert.css( 'background-color', '#C1E1B9' );
			setTimeout(function () {
				add_new_alert.css( 'background-color', current_bg_color );
			}, 250);

			$( '#wp_stream_alert_type' ).change( function () {
				loadAlertSettings( $( this ).val() );
			});
			add_new_alert.on( 'click', '.button-secondary.cancel', function () {
				$( '#add-new-alert' ).hide();
			});
			add_new_alert.on( 'click', '.button-primary.save', save_new_alert );
		}
	});
	var save_new_alert = function save_new_alert( e ) {
		e.preventDefault();
		$( '#add-new-alert' ).find('p.submit.inline-edit-save span.spinner').css( 'visibility', 'visible' );
		var data = {
			'action':          'save_new_alert',
			'wp_stream_trigger_author':  $('#wp_stream_trigger_author').val(),
			'wp_stream_trigger_context': $('#wp_stream_trigger_connector_or_context').val(),
			'wp_stream_trigger_action':  $('#wp_stream_trigger_action').val(),
			'wp_stream_alert_type':      $('#wp_stream_alert_type').val()
		};
		$('#wp_stream_alert_type_form').find(':input').each( function(){
			var alert_type_data_id = $(this).attr('id');
			if ( $(this).val() ) {
				data[alert_type_data_id] = $(this).val();
			}
		});

		$.post( window.ajaxurl, data, function( response ) {
			if ( true === response.success ) {
				$( '#add-new-alert' ).find('p.submit.inline-edit-save span.spinner').css('visibility', 'hidden');
				location.reload();
			}
		});
	};
});
