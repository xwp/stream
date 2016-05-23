jQuery( function( $ ) {

  $( '.chosen-select' ).each( function() {
    var $input = $( this );
    $input.select2( {
      data: $input.data( 'values' ),
      allowClear: true,
      placeholder: $input.data( 'placeholder' )
    } );
  } );

  var $alertSettingSelect = $('#wp_stream_alert_type');
  var $alertSettingForm = $('#wp_stream_alert_type_form');
  var loadAlertSettings = function( alert_type ) {
    var data = {
      'action'     : 'load_alerts_settings',
      'alert_type' : alert_type,
      'post_id'    : $('#post_ID').val()
    };

    $('#wp_stream_alerts_triggers input.wp_stream_ajax_forward').each( function() {
      data[ $(this).attr('name') ] = $(this).val();
    } );

    $.post( window.ajaxurl, data, function(response) {
      $alertSettingForm.html( response.data.html );
    });
  };

  var $alertTriggersSelect = $('#wp_stream_alerts_triggers input.wp_stream_ajax_forward');
  var $alertPreviewTable = $('#wp_stream_alerts_preview .inside');
  $alertTriggersSelect.change( function() {
    loadAlertPreview();
  } );

  var loadAlertPreview = function() {
    var data = {
      'action'     : 'load_alert_preview',
      'post_id'    : $('#post_ID').val()
    };

    $('#wp_stream_alerts_triggers input.wp_stream_ajax_forward').each( function() {
      data[ $(this).attr('name') ] = $(this).val();
    } );

    $.post( window.ajaxurl, data, function( response ) {
      $alertPreviewTable.html( response.data.html );
    });
  };

  $alertSettingSelect.change( function() {
    loadAlertSettings( $(this).val() );
  } );

} );
