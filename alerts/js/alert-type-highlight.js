/* globals jQuery, _streamAlertTypeHighlightExports */
/* exported streamAlertTypeHighlight */
var streamAlertTypeHighlight = ( function( $ ) {
	var self = {
		ajaxUrl: '',
		removeAction: '',
		security: '',
	};

	if ( 'undefined' !== typeof _streamAlertTypeHighlightExports ) {
		$.extend( self, _streamAlertTypeHighlightExports );
	}

	/**
	 * The primary function for this file.
	 */
	self.init = function() {
		$( document ).ready(
			function() {
				/**
				 * Remove highlights on Record list screen.
				 */
				$( '.alert-highlight .action-link[href="#"]' ).each(
					function() {
						var actionLink = $( this );

						/**
						 * Ajax call to remove the highlight.
						 *
						 * @return void.
						 */
						actionLink.click(
							function( e ) {
								var recordId, data;
								e.preventDefault();
								recordId = actionLink.parents( '.alert-highlight' ).attr( 'class' ).match( /record\-id\-[\w-]*\b/ );
								recordId = recordId[0].replace( 'record-id-', '' );

								data = {
									action: self.removeAction,
									security: self.security,
									recordId: recordId,
								};

								$.post(
									self.ajaxUrl, data, function( response ) {
										if ( true === response.success ) {
											ajaxDone();
										}
									}
								);

								/**
								 * Fires when Ajax complete.
								 */
								function ajaxDone() {
									var row = actionLink.parents( '.alert-highlight' ),
										odd = $( '.striped > tbody > :nth-child( odd )' );
									if ( row.is( odd ) ) {
										row.animate(
											{ backgroundColor: '#f9f9f9' }, 300, function() {
												row.removeClass( 'alert-highlight' );
											}
										);
									} else {
										row.animate(
											{ backgroundColor: '' }, 300, function() {
												row.removeClass( 'alert-highlight' );
											}
										);
									}
									actionLink.remove();
								}
							}
						);
					}
				);
			}
		); // End document.ready().
	};

	return self;
}( jQuery ) );
