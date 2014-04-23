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
			var ext_obj  = stream_extensions.extensions;
			$('.theme').on('click', function() {
				var $this = $(this);
				var extension = $this.data('extension');
				$.each(ext_obj, function(index, value) {
					if (extension === index) {
						template.find('.theme-name').html(value.name);
						if (value.video) {
							template.find('.screenshot').html('<div class="video-container"><iframe class="youtube-player" type="text/html" width="640" height="385" src="http://www.youtube.com/embed/' + value.video + '" frameborder="0"></iframe ></div>');
						}
						template.find('.theme-description').html(value.description);
						overlay.show();
					}

					$('.right').on('click', function () {
						extension = $this.next().data('extension');
						var obj = ext_obj.extension;
						console.log(obj);
						//Advance loop to next indexed object
						$.each(ext_obj, function(index, value) {
							if (extension === index) {
								template.find('.theme-name').html(value.name);
								template.find('.screenshot').html('<img src="' + value.screen_shot + '" />');
								template.find('.theme-description').html(value.description);
							}
						});
					});

					$('.left').on('click', function () {
						//Reverse loop to previous object
					});

					$('.close').on('click', function () {
						overlay.hide();
					});
				});
			});
		}
	};

	$(document).ready(function() {
		Extensions.init();
	});

}(jQuery));