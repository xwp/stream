/**
 * Stream Extension Display page javascript
 *
 * global stream_extensions, jQuery
 */

(function($) {

	var Extensions = {

		init : function() {
			this.load();
		},

		load : function() {
			var template = $('.theme-wrap');
			var overlay  = $('.theme-overlay');
			$('.theme').on('click', function() {
				var $this = $(this);
				var extension = $this.data('extension');
				$.each(stream_extensions.extensions, function(index, value) {
					if (extension === index) {
						template.find('.theme-name').html(value.name);
						template.find('.screenshot').html('<img src="' + value.screen_shot + '" />');
						template.find('.theme-description').html(value.description);
						overlay.show();

						$('.right').on('click', function () {
							//Advance loop to next object item
						});

						$('.left').on('click', function () {
							//Reverse loop to previous object
						});

						$('.close').on('click', function () {
							overlay.hide();
						});
					}
				});
			});
		}
	};

	$(document).ready(function() {
		Extensions.init();
	});

}(jQuery));