/**
 * Stream Extension Display page javascript
 *
 * global stream_extensions, jQuery
 */

(function($) {

	var Extensions = {

		load : function() {
			var template = $('.theme-wrap');
			var overlay  = $('.theme-overlay');
			var ext_obj  = stream_extensions.extensions;
			$('.theme').on('click', function() {
				var $this = $(this);
				var extension = $this.data('extension');
				$.each(ext_obj, function(index, value) {
					if (extension === index) {
						template.find('.theme-name').html(value.name + '<span class="theme-version"></span>');
						template.find('.theme-version').text('Version: ' + value.version);
						if (value.video) {
							template.find('.screenshot').html('<div class="video-container"><iframe class="youtube-player" type="text/html" width="640" height="385" src="http://www.youtube.com/embed/' + value.video + '" frameborder="0"></iframe ></div>');
						}
						template.find('.theme-description').html(value.content);
						if (!value.installed) {
							template.find('.theme-actions').html('<a href="' + value.actions.install + '" class="button button-primary">' + value.install18n + '</a>');

						} else if (value.installed && !value.active) {
							template.find('.theme-actions').html('<a href="' + value.actions.activate + '" class="button button-primary">' + value.activate18n + '</a>');

						} else if (value.installed && value.active) {
							template.find( '.theme-actions').html(value.active18n);
						}
						overlay.show();
					}
				});

				$('.right').on('click', function () {
					extension = $this.next().data('extension');
					//Advance loop to next indexed object
					$.each(ext_obj, function(index, value) {
						if (extension === index) {
							overlay.hide();
							template.find('.theme-name').html(value.name + '<span class="theme-version"></span>');
							template.find('.theme-version').text('Version: ' + value.version);
							if (value.video) {
								template.find('.screenshot').html('<div class="video-container"><iframe class="youtube-player" type="text/html" width="640" height="385" src="http://www.youtube.com/embed/' + value.video + '" frameborder="0"></iframe ></div>');
							}
							template.find('.theme-description').html(value.content);
							if (!value.installed) {
								template.find('.theme-actions').html('<a href="' + value.actions.install + '" class="button button-primary">' + value.install18n + '</a>');

							} else if (value.installed && !value.active) {
								template.find('.theme-actions').html('<a href="' + value.actions.activate + '" class="button button-primary">' + value.activate18n + '</a>');

							} else if (value.installed && value.active) {
								template.find('.theme-actions').html(value.active18n);
							}
							overlay.show();
						}
					});
				});

				$('.left').on('click', function () {
					extension = $this.prev().data('extension');
					//Advance loop to next indexed object
					$.each(ext_obj, function (index, value) {
						if (extension === index) {
							overlay.hide();
							template.find('.theme-name').html(value.name + '<span class="theme-version"></span>');
							template.find('.theme-version').text('Version: ' + value.version);
							if (value.video) {
								template.find('.screenshot').html('<div class="video-container"><iframe class="youtube-player" type="text/html" width="640" height="385" src="http://www.youtube.com/embed/' + value.video + '" frameborder="0"></iframe ></div>');
							}
							template.find('.theme-description').html(value.content);
							if (!value.installed) {
								template.find('.theme-actions').html('<a href="' + value.actions.install + '" class="button button-primary">' + value.install18n + '</a>');

							} else if (value.installed && !value.active) {
								template.find('.theme-actions').html('<a href="' + value.actions.activate + '" class="button button-primary">' + value.activate18n + '</a>');

							} else if (value.installed && value.active) {
								template.find('.theme-actions').html(value.active18n);
							}
							overlay.show();
						}
					});
				});

				$('.close').on('click', function () {
					overlay.hide();
				});
			});
		}
	};

	$(document).ready(function() {
		Extensions.load();
	});

}(jQuery));