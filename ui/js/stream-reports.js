/* global jQuery, ajaxurl */
(function( window, $ ){
	'use strict';

	// Delete Action
	$('.meta-box-sortables').hover(
		function() {
			$(this).find('.settings .delete').addClass( 'visible' );
		}, function() {
			$(this).find('.settings .delete').removeClass( 'visible' );
		}
	);

})( window, jQuery.noConflict() );
