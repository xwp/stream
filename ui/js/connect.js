jQuery( function( $ ) {
	var tooltipTimeout;

	$( '.wp-stream-tooltip-text' ).mouseover( function() {
		var tooltip = $( this ).next( '.wp-stream-tooltip' ),
		    left    = $( this ).position().left + ( $( this ).width() / 2 ) - ( tooltip.width() / 2 ), // align with center of tooltip text
		    top     = $( this ).position().top + $( this ).height(); // align with bottom of the tooltip text

		clearTimeout( tooltipTimeout );

		tooltip
			.show()
			.css( 'left', left )
			.css( 'top', top )
			.outerWidth( $( window ).width() - tooltip.offset().left - 10 );
	});

	$( '.wp-stream-tooltip-text' ).mouseout( function() {
		tooltipTimeout = setTimeout( function( el ) { el.hide(); }, 10, $( this ).next( '.wp-stream-tooltip' ) );
	});

	$( '.wp-stream-tooltip' ).mouseover( function() {
		clearTimeout( tooltipTimeout );
	});

	$( '.wp-stream-tooltip' ).mouseout( function() {
		tooltipTimeout = setTimeout( function( el ) { el.hide(); }, 10, $( this ) );
	});

});