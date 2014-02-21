/* global Raphael, jQuery, _ */
(function( window, $, _, Raphael ){
	'use strict';


	var report = function(){
		// Grab all the opts and draw the chart on the screen
		this.draw = function () {

		}

		// Build all the opts to be drawn later
		this.build = function ( opts ) {
			if ( _.isArray( opts ) ){
				opts = _.extend( {}, this._.opts, { '$' : opts.pop() }, ( opts.length > 0 ? opts.pop() : {} ) );
			}

			if ( _.isObject( opts ) ){
				opts = _.extend( {}, this._.opts, opts );
			} else {
				return console.error(  );
			}

			opts.$.each(function( k, el ) {
				var $el = $(el),
					data = $el.data( 'report', _.extend( {}, opts, { 'id': _.uniqueId( '__stream-report-chart-' ) }, $el.data( 'report' ) ) ).data( 'report' );


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
			});

		}
		console.log( report._ );

		// Internals
		this._ = {
			// Default Options
			'opts': {
				// Store the jQuery elements we are using
				'$': null,

				// Width of the canvas
				'width': 'this',

				// Height of the Canvas
				'height': 'this',

				// Actual data that will be plotted
				'values': {},

				// Type of the Chart
				'type': false,

				// Miliseconds on the animation, or false to deactivate
				'animate': 200,

				// Check if a graph need to be draw
				'draw': null
			},
		};

		// After everything is defined start
		var args = [];
		Array.prototype.push.apply( args, arguments );

		// Options storage for this instance
		this.opts = {};

		if ( args[0] instanceof jQuery ){
			this.build( args );
		}

		return this;
	};



	window.stream = _.extend( ( ! _.isObject( window.stream ) ? {} : window.stream ), { 'report' : report } );


	// Just working on a good exemple
	$(document).ready(function(){
		var chart = new stream.report( $('.report-chart') );

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
