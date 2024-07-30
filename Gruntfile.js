/* eslint-env node, es6 */

module.exports = function( grunt ) {
	'use strict';

	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),

		// Minify .js files.
		uglify: {
			options: {
				preserveComments: false,
			},
			core: {
				files: [
					{
						expand: true,
						cwd: 'ui/js/',
						src: [ '*.js', '!*.min.js' ],
						dest: 'ui/js/',
						ext: '.min.js',
					},
				],
			},
			alerts: {
				files: [
					{
						expand: true,
						cwd: 'alerts/js/',
						src: [ '*.js', '!*.min.js' ],
						dest: 'alerts/js/',
						ext: '.min.js',
					},
				],
			},
		},

		// Minify .css files.
		cssmin: {
			core: {
				files: [
					{
						expand: true,
						cwd: 'ui/css/',
						src: [ '*.css', '!*.min.css' ],
						dest: 'ui/css/',
						ext: '.min.css',
					},
				],
			},
		},

		// Clean up the build
		clean: {
			build: {
				src: [ 'build' ],
			},
		},
	} );

	// Load tasks
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );

	// Register tasks
	grunt.registerTask( 'default', [ 'clean', 'uglify', 'cssmin' ] );
};
