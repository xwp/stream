jQuery(function($){

	$( '.toplevel_page_wp_stream .date-picker' ).datepicker({
		dateFormat: 'yy/mm/dd',
		maxDate: 0
	});

	$( '.toplevel_page_wp_stream .chosen-select' ).chosen({
		disable_search_threshold: 10,
		allow_single_deselect: true,
		width: '165px'
	});

	$(window).load(function() {
		$( '.toplevel_page_wp_stream [type=search]' ).off( 'mousedown' );
	});

});
