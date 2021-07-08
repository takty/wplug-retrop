/**
 *
 * Gulpfile
 *
 * @author Takuto Yanagida
 * @version 2021-07-08
 *
 */


'use strict';

const gulp = require('gulp');

const { makeJsTask, makeCssTask, makeCopyTask } = require('./common');

const js_raw = makeJsTask(['src/**/*.js', '!src/**/*.min.js'], './dist', 'src');

const js_copy = makeCopyTask('src/**/*.min.js', './dist');

const js = gulp.parallel(js_raw, js_copy);

const css_raw = makeCssTask(['src/**/*.css', '!src/**/*.min.css'], './dist', 'src');

const css_copy = makeCopyTask('src/**/*.min.css', './dist');

const css = gulp.parallel(css_raw, css_copy);

const php = makeCopyTask('src/**/*.php', './dist');

const watch = (done) => {
	gulp.watch('src/**/*.js', js);
	gulp.watch('src/**/*.css', css);
	gulp.watch('src/**/*.php', php);
	done();
};

exports.build   = gulp.parallel(js, css, php);
exports.default = gulp.series(exports.build, watch);
