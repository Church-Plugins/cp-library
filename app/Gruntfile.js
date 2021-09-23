/* global require, process */
module.exports = function (grunt) {

	require( 'load-grunt-tasks' )( grunt );

	grunt.initConfig({
		reactTemplates: {
			dist: {
				src: ['templates/**/*.rt'], //glob patterns of files to be processed
				options: {
					modules: 'commonjs',  //possible values: (amd|commonjs|es6|typescript|none)
					format: 'stylish' //possible values: (stylish|json)
				}
			}
		},
		copy: {
			main: {
				expand: true,
				src: ['templates/**/*.rt.js'],
				dest: 'src/'
			},
			post: {
				expand: true,
				src: ['templates/**/*.rt.js'],
				dest: 'build/'
			}
		},
		clean: {
			pre: 	['src/templates/', 'templates/**/*.rt.js', 'build/templates'],
			post:	['templates/**/*.rt.js', 'src/templates/']
		}
	});

	grunt.loadNpmTasks( 'grunt-react-templates' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-clean' );

	grunt.registerTask( 'rt',					['react-templates:dist'] );
	grunt.registerTask( 'create-templates',		['clean:pre', 'rt', 'copy:main'] );
	grunt.registerTask( 'finish-templates',		['copy:post', 'clean:post'] );
	grunt.registerTask( 'default',				['create-templates'] );
};