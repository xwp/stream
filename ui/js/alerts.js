jQuery( function( $ ) {
  $( '.chosen-select' ).each( function() {
    var $input = $( this );
    $input.select2( {
      data: $input.data( 'values' ),
      placeholder: $input.data( 'placeholder' )
    } );
  } );
} );
