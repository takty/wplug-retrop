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

const { makeJsTask, makeCopyTask } = require('./common');

const js_raw = makeJsTask(['src/**/*.js', '!src/**/*.min.js'], './dist', 'src');

const js_copy = makeCopyTask('src/**/*.min.js', './dist');

const js = gulp.parallel(js_raw, js_copy);

const php = makeCopyTask('src/**/*.php', './dist');

const watch = (done) => {
	gulp.watch('src/**/*.js', js);
	gulp.watch('src/**/*.php', php);
	done();
};

exports.build   = gulp.parallel(js, php);
exports.default = gulp.series(exports.build, watch);
