/* eslint-env node */

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

		// Build a deploy-able plugin
		copy: {
			build: {
				src: [
					'*.php',
					'alerts/**',
					'assets/**',
					'classes/**',
					'connectors/**',
					'exporters/**',
					'includes/**',
					'ui/**',
					'languages/*',
					'readme.txt',
					'readme.md',
					'contributing.md',
				],
				dest: 'build',
				expand: true,
				dot: true,
			},
		},

		compress: {
			release: {
				options: {
					archive: function () {
						if (process.env.TRAVIS_TAG) {
							return `stream-${process.env.TRAVIS_TAG}.zip`;
						}

						return 'stream.zip';
					},
				},
				cwd: 'build',
				dest: 'stream',
				src: [
					'**/*',
				],
			},
		},

		// Clean up the build
		clean: {
			build: {
				src: [ 'build' ],
			},
		},

		// Deploys a git Repo to the WordPress SVN repo
		wp_deploy: {
			deploy: {
				options: {
					plugin_slug: 'stream',
					plugin_main_file: 'stream.php',
					build_dir: 'build',
					assets_dir: 'assets',
				},
			},
		},
	} );

	// Load tasks
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-contrib-compress' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );
	grunt.loadNpmTasks( 'grunt-wp-deploy' );

	// Register tasks
	grunt.registerTask( 'default', [ 'uglify', 'cssmin' ] );
	grunt.registerTask( 'build', [ 'default', 'copy' ] );
	grunt.registerTask( 'release', [ 'build', 'compress' ] );
	grunt.registerTask( 'deploy', [ 'build', 'wp_deploy', 'clean' ] );
};
