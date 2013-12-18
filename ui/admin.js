/* globals confirm, wp_stream */
jQuery(function($){

	if ( jQuery.datepicker ) {
		$( '.toplevel_page_wp_stream .date-picker' ).datepicker({
			dateFormat: 'yy/mm/dd',
			maxDate: 0
		});
	}

	$( '.toplevel_page_wp_stream .chosen-select' ).chosen({
		disable_search_threshold: 10,
		allow_single_deselect: true,
		width: '165px'
	});

	$(window).load(function() {
		$( '.toplevel_page_wp_stream [type=search]' ).off( 'mousedown' );
	});

	// Confirmation on some important actions
	$('#wp_stream_general_delete_all_records').click(function(e){
		if ( ! confirm( wp_stream.i18n.confirm_purge ) ) {
			e.preventDefault();
		}
	});

	$('#wp_stream_uninstall').click(function(e){
		if ( ! confirm( wp_stream.i18n.confirm_uninstall ) ) {
			e.preventDefault();
		}
	});

});
