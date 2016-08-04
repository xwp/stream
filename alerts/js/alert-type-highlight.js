/* globals jQuery, _streamAlertTypeHighlightExports */
/* exported streamAlertTypeHighlight */
var streamAlertTypeHighlight = ( function( $ ) {
	var self = {
		ajaxUrl: '',
		removeAction: '',
		security: ''
	};

	if ( 'undefined' !== typeof _streamAlertTypeHighlightExports ) {
		$.extend( self, _streamAlertTypeHighlightExports );
	}

	/**
	 * The primary function for this file.
	 *
	 * @returns void.
	 */
	self.init = function() {
		$( document ).ready( function() {

			/**
			 * Remove highlights on Record list screen.
			 *
			 * @returns void.
			 */
			$( '.alert-highlight .action-link[href="#"]' ).each( function() {
				var el = $( this );

				/**
				 * Ajax call to remove the highlight.
				 *
				 * @returns void.
				 */
				el.click( function( e ) {
					var recordId;
					e.preventDefault();
					recordId = el.parents( '.alert-highlight' ).attr( 'class' ).match( /record\-id\-[\w-]*\b/ );
					recordId = recordId[0].replace( 'record-id-', '' );

					$.post( self.ajaxUrl,
						{
							action: self.removeAction,
							security: self.security,
							recordId: recordId
						},
						function( response ) {
							if ( true === response.success ) {
								ajaxDone();
							}
					});

					/**
					 * Fires when Ajax complete.
					 */
					function ajaxDone() {
						el.parents( '.alert-highlight' ).removeClass( 'alert-highlight' );
						el.remove();
					}
				});
			});
		}); // End document.ready().
	};

	return self;

})( jQuery );
