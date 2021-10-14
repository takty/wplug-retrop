/**
 *
 * Function for gulp (JS)
 *
 * @author Takuto Yanagida
 * @version 2021-09-02
 *
 */

'use strict';

const gulp = require('gulp');
const $    = require('gulp-load-plugins')({ pattern: ['gulp-*'] });

function makeJsTask(src, dest = './dist', base = null) {
	const jsTask = () => gulp.src(src, { base: base, sourcemaps: true })
		.pipe($.plumber())
		.pipe($.preprocess())
		.pipe($.babel())
		.pipe($.terser())
		.pipe($.rename({ extname: '.min.js' }))
		.pipe($.changed(dest, { hasChanged: $.changed.compareContents }))
		.pipe(gulp.dest(dest, { sourcemaps: '.' }));
	return jsTask;
}

exports.makeJsTask = makeJsTask;
