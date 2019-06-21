/* eslint-env node */
/* jshint node:true */

module.exports = function( grunt ) {
	'use strict';

	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),

		// JavaScript linting with JSHint.
		jshint: {
			options: {
				jshintrc: '.jshintrc'
			},
			all: ['Gruntfile.js', 'ui/js/*.js', '!ui/js/*.min.js']
		},

		// Minify .js files.
		uglify: {
			options: {
				preserveComments: false
			},
			core: {
				files: [
					{
						expand: true,
						cwd: 'ui/js/',
						src: ['*.js', '!*.min.js'],
						dest: 'ui/js/',
						ext: '.min.js'
					}
				]
			},
			alerts: {
				files: [
					{
						expand: true,
						cwd: 'alerts/js/',
						src: ['*.js', '!*.min.js'],
						dest: 'alerts/js/',
						ext: '.min.js'
					}
				]
			}
		},

		// Minify .css files.
		cssmin: {
			core: {
				files: [
					{
						expand: true,
						cwd: 'ui/css/',
						src: ['*.css', '!*.min.css'],
						dest: 'ui/css/',
						ext: '.min.css'
					}
				]
			}
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
					'readme.txt'
				],
				dest: 'build',
				expand: true,
				dot: true
			}
		},

		// Clean up the build
		clean: {
			build: {
				src: ['build']
			}
		},

		// VVV (Varying Vagrant Vagrants) Paths
		vvv: {
			plugin: '/srv/www/wordpress-develop/public_html/src/wp-content/plugins/stream',
			coverage: '/srv/www/default/coverage/stream'
		},

		// Shell actions
		shell: {
			options: {
				stdout: true,
				stderr: true
			},
			phpunit: {
				command: 'vagrant ssh -c "cd <%= vvv.plugin %> && phpunit"'
			},
			phpunit_c: {
				command:
					'vagrant ssh -c "cd <%= vvv.plugin %> && phpunit --coverage-html <%= vvv.coverage %>"'
			}
		},

		// Deploys a git Repo to the WordPress SVN repo
		wp_deploy: {
			deploy: {
				options: {
					plugin_slug: 'stream',
					plugin_main_file: 'stream.php',
					build_dir: 'build',
					assets_dir: 'assets'
				}
			}
		}
	} );

	// Load tasks
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );
	grunt.loadNpmTasks( 'grunt-shell' );
	grunt.loadNpmTasks( 'grunt-wp-deploy' );

	// Register tasks
	grunt.registerTask( 'default', ['jshint', 'uglify', 'cssmin'] );
	grunt.registerTask( 'phpunit', ['shell:phpunit'] );
	grunt.registerTask( 'phpunit_c', ['shell:phpunit_c'] );
	grunt.registerTask( 'build', ['default', 'copy'] );
	grunt.registerTask( 'deploy', ['build', 'wp_deploy', 'clean'] );
};
