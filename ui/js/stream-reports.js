/* global jQuery, ajaxurl */
(function( window, $ ){
	'use strict';

	var Reports = {
		init: function() {
			// Let's configure event listener for all sections
			this.configureSection();
		},
		configureSection: function() {
			// Trigger select2js
			$('.postbox .inside .configure .chart-options').select2();

			// Configuration toggle
			$('.postbox-title-action .edit-box').click(function(){
				// Change value of button
				$(this).text( $(this).text() === 'Configure' ? 'Cancel' : 'Configure' );

				// Always show the cancel button
				$(this).toggleClass('edit-box');

				// Show the delete button
				$(this).parent().next().find('a').toggleClass('visible');

				// Show the configure div
				$(this).parents('.postbox').find('.inside .configure').toggleClass('visible');
			});
		}
	};

	// Document ready function
	$(document).ready(function(){
		Reports.init();
	});

})( window, jQuery.noConflict() );
