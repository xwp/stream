/* global jQuery, ajaxurl */
(function( window, $ ){
	'use strict';

	var Reports = {
		init: function() {
			// Variables
			this.$configureDiv = $('.postbox .inside .configure');

			// Let's configure event listener for all sections
			this.configureSection();
		},
		configureSection: function() {
			var t = this;

			// Trigger select2js
			t.$configureDiv.find('.chart-options').select2();

			// Change chart type toggle
			this.$configureDiv.find('.chart-types .dashicons').click(function() {
				var $target = $(this);
				if(!$target.hasClass('active')){
					$target.siblings().removeClass('active');
					$target.addClass('active');
					t.configureEnableSaveButton($target);
				}
			});

			// Confirmation of deletion
			$('.postbox-delete-action a').click(function(){
				if (!window.confirm('Do you really want to delete this section?\rThis cannot be undone.')) {
					return false;
				}
			});

			// Configuration toggle
			$('.postbox-title-action .edit-box').click(function(){
				var $target = $(this);
				// Change value of button
				$target.text( $target.text() === 'Configure' ? 'Cancel' : 'Configure' );

				// Always show the cancel button
				$target.toggleClass('edit-box');

				// Show the delete button
				$target.parent().next().find('a').toggleClass('visible');

				// Hold parent container
				var $curPostbox = $target.parents('.postbox');

				//Open the section if it's hidden
				$curPostbox.removeClass('closed');

				// Show the configure div
				$curPostbox.find('.inside .configure').toggleClass('visible');
			});
		},
		configureEnableSaveButton : function($target){
			var $submit = $target.parents('.configure').find('.configure-submit');
			if( $submit.hasClass('disabled') ) {
				$submit.removeClass('disabled');
			}
		}
	};

	// Document ready function
	$(document).ready(function(){
		Reports.init();
	});

})( window, jQuery.noConflict() );
