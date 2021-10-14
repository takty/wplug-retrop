/**
 *
 * Function for gulp (Copy)
 *
 * @author Takuto Yanagida
 * @version 2021-09-02
 *
 */

'use strict';

const gulp = require('gulp');
const $    = require('gulp-load-plugins')({ pattern: ['gulp-*'] });

function makeCopyTask(src, dest = './dist', base = null) {
	const copyTask = () => gulp.src(src, { base: base })
		.pipe($.plumber())
		.pipe($.ignore.include({ isFile: true }))
		.pipe($.changed(dest, { hasChanged: $.changed.compareContents }))
		.pipe(gulp.dest(dest));
	return copyTask;
}

exports.makeCopyTask = makeCopyTask;
