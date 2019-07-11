/* globals jQuery */
jQuery(
	function( $ ) {
		var highlight, input, tab;

		if ( window.location.hash.substr( 'stream-highlight-' ) ) {
			highlight = window.location.hash.replace( 'stream-highlight-', '' );
			input = $( ':input' + highlight );

			window.location.hash = '';

			if ( input.length ) {
				if ( $( '#wpseo-tabs' ).length ) {
					tab = input.parents( '.wpseotab' ).first().attr( 'id' );
					window.location.hash = '#top#' + tab;
				}

				jQuery( document ).ready(
					function() {
						setTimeout(
							function() {
								$( 'body,html' ).animate(
									{ scrollTop: input.offset().top - 50 },
									'slow',
									function() {
										input.animate( { backgroundColor: 'yellow' }, 'slow' );
									}
								);
							}, 500
						);
					}
				);
			}
		}
	}
);
