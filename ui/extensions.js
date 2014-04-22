/**
 * global jQuery, ajaxurl, tb_remove, alert, stream_activation, tb_show, alert, console
 */

(function($) {

	var Extensions = {

		init : function() {
			this.load();
		},

		load : function() {
			var template = $('.theme-wrap');
			$('.theme').on('click', function() {
				var $this = $(this);
				var extension = $this.data('extension');
				$.each(stream_extensions.extensions, function(index, value) {
					if (extension === index) {
						console.log(value);
						template.find('.screenshot').html('<img src="' + value.screen_shot + '" />');
					}
				});
			});
		}
	};

	$(document).ready(function() {
		Extensions.init();
	});

})(jQuery);