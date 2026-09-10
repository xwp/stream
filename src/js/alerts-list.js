/**
 * External dependencies
 */
import $ from 'jquery';

/**
 * Internal dependencies
 */
import '../css/alerts-list.scss';

$( '.inline-edit-col-left, .inline-edit-col-right, #major-publishing-actions', '.edit-php.post-type-wp_stream_alerts' ).each(
	function() {
		$( this ).remove();
	},
);
