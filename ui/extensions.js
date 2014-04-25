/**
 * Stream Extension Display page javascript
 *
 */
/* global stream_extensions */

(function( $ ) {

	var Extensions = {

		load : function() {
			var template = $( '.theme-wrap' );
			var overlay  = $( '.theme-overlay' );
			var ext_obj  = stream_extensions.extensions;

			$( '.theme .more-details, .theme .theme-screenshot' ).on( 'click', function() {
				var extension = $( this ).parent().addClass( 'open' ).data( 'extension' );
				var ext_data;

				if ( ext_obj[ extension ] === null ) {
					return;
				} else {
					ext_data  = ext_obj[ extension ];
				}

				template.find( '.theme-name' ).html( ext_data.name + '<span class="theme-version"></span>' );
				template.find( '.theme-version' ).text( 'Version: ' + ext_data.version );
				template.find( '.theme-description' ).html( ext_data.content );

				if ( ext_data.video ) {
					template.find( '.screenshot' ).html( '<div class="video-container"><iframe class="youtube-player" type="text/html" width="640" height="385" src="http://www.youtube.com/embed/' + ext_data.video + '" frameborder="0"></iframe ></div>' );
				} else if ( ext_data.remote_img ) {
					template.find( '.screenshot' ).html( '<div class="video-container"><img src="' + ext_data.remote_img + '" /></div>' );
				} else {
					template.find( '.screenshot' ).html( '' );
				}

				if ( ! ext_data.installed ) {
					template.find( '.theme-actions' ).html( '<a href="' + ext_data.actions.install + '" class="button button-primary">' + ext_data.install18n + '</a>' );
				} else if ( ext_data.installed && ! ext_data.active ) {
					template.find( '.theme-actions' ).html( '<a href="' + ext_data.actions.activate + '" class="button button-primary">' + ext_data.activate18n + '</a>' );
				} else if ( ext_data.installed && ext_data.active ) {
					template.find( '.theme-actions' ).html( '<a href="#" class="button button-disabled">' + ext_data.active18n + '</a>' );
				} else {
					template.find( '.theme-actions' ).html( '' );
				}

				overlay.show();
			});

			$( '.theme-overlay .theme-header .right' ).on( 'click', function() {
				var nextExtension = $( '.themes .theme.open' ).next();
				if ( 0 === nextExtension.length ) {
					nextExtension = $( '.themes .theme' ).first();
				}
				overlay.hide();
				$( '.themes .theme.open' ).removeClass( 'open' );
				nextExtension.find( '.more-details' ).trigger( 'click' );
			});

			$( '.theme-overlay .theme-header .left' ).on( 'click', function() {
				var prevExtension = $( '.themes .theme.open' ).prev();
				if ( 0 === prevExtension.length ) {
					prevExtension = $( '.themes .theme' ).last();
				}
				overlay.hide();
				$( '.themes .theme.open' ).removeClass( 'open' );
				prevExtension.find( '.more-details' ).trigger( 'click' );
			});

			$( '.theme-overlay .theme-header .close' ).on( 'click', function() {
				overlay.hide();
			});
		}
	};

	$( document ).ready( function() {
		Extensions.load();
	});

} ( jQuery ) );