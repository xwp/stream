jQuery(function($){

	$( '.toplevel_page_wp_stream .date-picker' ).datepicker({
		dateFormat: 'yy/mm/dd',
		maxDate: 0
	});

	$(window).load(function() {
		$( '.toplevel_page_wp_stream [type=search]' ).off( 'mousedown' );
	});

});