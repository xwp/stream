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
      'post_id'    : $('#post_ID').val(),
    };

    $.post(ajaxurl, data, function(response) {
      $alertSettingForm.html( response.data.html );
    });
  }

  $alertSettingSelect.change( function( el ) {
    loadAlertSettings( $(this).val() );
  } );

} );
