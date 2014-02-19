/* global Raphael, jQuery, _ */
(function( window, $, _, Raphael ){
	'use strict';
	$.fn.streamReportChart = function (){
		if ( typeof arguments[0] !== 'undefined' && typeof arguments[0] !== 'object' ){
			return console.error( 'Error: %s (%s)', 'bad type', typeof arguments[0] );
		}

		var default_args = _.extend(
				// Default Arguments that will be passed for each element found in `this`
				// then this will be replace if the functions finds a data attr
				{
					width: 'this',
					height: 'this',
					animate: true,
					gutter: 5,
					type: null,
					values: [],

					// Check if a graph need to be draw
					draw: true,

					// Ref to Chart instance
					chart: null,

					// Ref to Raphael instance
					paper: null
				},
				arguments[0]
			),
			_base_ueid = '__stream-report-chart-';

		return this.each(function ( key, el ){
			var	$el = $(el).on({
				'build.report': function ( e, data ){
					if ( _.isObject( $el.data( 'stream-report' ) ) && _.isString( $el.data( 'stream-report' ).id ) ){
						data = $el.data( 'stream-report' );
					} else {
						// Merge the default data, attributes and the param for the init
						// This is only to give us full control...
						data = $el.data( 'stream-report', _.extend( {}, default_args, $el.data( 'stream-report' ), data, { 'id': _.uniqueId(_base_ueid) } ) ).data( 'stream-report' );
						$el.attr( 'id', data.id );
					}

					if ( 'parent' === data.width || 'parent' === data._width ) {
						data.width = $el.parent().innerWidth();
						data._width = 'parent';
					} else if ( 'this' === data.width || 'this' === data._width ) {
						data.width = $el.innerWidth();
						data._width = 'this';
					}

					if ( 'parent' === data.height || 'parent' === data._height ) {
						data.height = $el.parent().innerHeight();
						data._height = 'parent';
					} else if ( 'this' === data.height || 'this' === data._height ) {
						data.height = $el.innerHeight();
						data._height = 'this';
					}


					if ( null === data.paper ){
						data.paper = Raphael( $el[0], data.width, data.height );
					} else {
						if ( data.paper.width !== data.width || data.paper.height !== data.height ){
							data.paper.setSize( data.width, data.height );
							data.draw = true;
						}
					}

					if ( 'pie' === data.type ) {
						data.x = data.width/2;
						data.y = data.height/2;
						data.radius = Math.ceil( ( _.min( [ data.width, data.height ] ) - data.gutter )/2 );
					}

					$el.data( 'stream-report', data );

					if ( true === data.draw ){
						$el.trigger('draw.report');
					}
				},
				'draw.report': function ( e ) {
					if ( ! _.isObject( $el.data( 'stream-report' ) ) || ! _.isString( $el.data( 'stream-report' ).id ) ){
						return console.error( 'Error: %s', 'not built yet' );
					}
					var data = $el.data( 'stream-report' );

					if ( true === data.draw ){
						data.paper.clear();
					}

					// To avoid problems with re-drawing the chart, dont use a global variable
					// each re-draw build a new values variable
					var values = [];
					_.each(data.values, function ( item, key, list ){
						if ( _.isObject( item ) ){
							values.push(item.value);
						} else {
							values.push(item);
						}
					});

					if ( 'pie' === data.type ) {
						if ( true === data.draw ) {
							data.chart = data.paper.piechart( data.x, data.y, data.radius, values );
							data.draw = false;
						}

						// Animate the chart when it inits
						if ( true === data.animate && null !== data.chart ) {
							data.chart.each(function(){
								this.sector.scale(0, 0, this.cx, this.cy);
								this.sector.animate({ transform: 's1 1 ' + this.cx + ' ' + this.cy }, 300, "easeInOut");
							});
						}
					} else if ( 'line' = data.type ){

					} else if ( 'vertical-bar' = data.type ){

					} else if ( 'horizontal-bar' = data.type ){

					} else if ( 'dot' = data.type ){

					}

					if ( null !== data.chart ){
						data.chart.hover(function (){
							// MouseIn Effect of each item of the chart should go here.
						}, function (){
							// MouseOut Effect of each item of the chart should go here.
						});

						data.chart.click(function() {
							// On Click event for each one of the things on the chart
						});
					}

					$el.data( 'stream-report', data );
				}
			});

			$el.trigger('build.report');
		});
	};


	// Just working on a good exemple
	$(document).ready(function(){
		$('.report-chart').streamReportChart();

		// Delete Action
		$('.postbox').hover(
				function() {
					$(this).find('.settings .delete').addClass( 'visible' );
				}, function() {
					$(this).find('.settings .delete').removeClass( 'visible' );
				}
		);
	});

})( window, jQuery.noConflict(), _.noConflict(), Raphael);
