/**
 *
 * Common functions for gulp process
 *
 * @author Takuto Yanagida
 * @version 2021-07-08
 *
 */


'use strict';

const SASS_OUTPUT_STYLE = 'compressed';  // 'expanded' or 'compressed'

function pkgDir(name) {
	const path = require('path');
	return path.dirname(require.resolve(name + '/package.json'));
}

function verStr(devPostfix = ' [dev]') {
	const getBranchName = require('current-git-branch');

	const bn = getBranchName();
	const pkg = require('../package.json');
	return 'v' + pkg['version'] + ((bn === 'develop') ? devPostfix : '');
}

exports.pkgDir = pkgDir;
exports.verStr = verStr;


// -----------------------------------------------------------------------------


const gulp = require('gulp');
const $ = require('gulp-load-plugins')({ pattern: ['gulp-*'] });

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

function makeCssTask(src, dest = './dist', base = null) {
	const cssTask = () => gulp.src(src, { base: base, sourcemaps: true })
		.pipe($.plumber())
		.pipe($.autoprefixer({ remove: false }))
		.pipe($.cleanCss())
		.pipe($.rename({ extname: '.min.css' }))
		.pipe($.changed(dest, { hasChanged: $.changed.compareContents }))
		.pipe(gulp.dest(dest, { sourcemaps: '.' }));
	return cssTask;
}

function makeSassTask(src, dest = './dist', base = null) {
	const sassTask = () => gulp.src(src, { base: base, sourcemaps: true })
		.pipe($.plumber())
		.pipe($.dartSass({ outputStyle: SASS_OUTPUT_STYLE }))
		.pipe($.autoprefixer({ remove: false }))
		.pipe($.rename({ extname: '.min.css' }))
		.pipe($.changed(dest, { hasChanged: $.changed.compareContents }))
		.pipe(gulp.dest(dest, { sourcemaps: '.' }));
	return sassTask;
}

function makeCopyTask(src, dest = './dist', base = null) {
	const copyTask = () => gulp.src(src, { base: base })
		.pipe($.plumber())
		.pipe($.ignore.include({ isFile: true }))
		.pipe($.changed(dest, { hasChanged: $.changed.compareContents }))
		.pipe(gulp.dest(dest));
	return copyTask;
}

function makeTimestampTask(src, dest = './dist', base = null) {
	const moment = require('moment');
	const timestampTask = () => gulp.src(src, { base: base })
		.pipe($.plumber())
		.pipe($.ignore.include({ isFile: true }))
		.pipe($.replace(/v\d+t/g, `v${moment().format('HHmmss')}t`))
		.pipe($.changed(src, { hasChanged: $.changed.compareContents }))
		.pipe(gulp.dest(dest));
	return timestampTask;
}

exports.makeJsTask        = makeJsTask;
exports.makeCssTask       = makeCssTask;
exports.makeSassTask      = makeSassTask;
exports.makeCopyTask      = makeCopyTask;
exports.makeTimestampTask = makeTimestampTask;
