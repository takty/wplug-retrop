'use strict';

const gulp = require('gulp');
const $ = require('gulp-load-plugins')({ pattern: ['gulp-*'] });


gulp.task('js-raw', () => {
	return gulp.src(['src/**/*.js', '!src/**/*.min.js'], { base: 'src', sourcemaps: true })
		.pipe($.plumber())
		.pipe($.babel())
		.pipe($.terser())
		.pipe($.rename({ extname: '.min.js' }))
		.pipe(gulp.dest('dist', { sourcemaps: '.' }));
});

gulp.task('js-min', () => {
	return gulp.src(['src/**/*.min.js'])
		.pipe($.plumber())
		.pipe(gulp.dest('dist'));
});

gulp.task('js', gulp.parallel('js-raw', 'js-min'));

gulp.task('css-raw', () => {
	return gulp.src(['src/**/*.css', '!src/**/*.min.css'], { base: 'src', sourcemaps: true })
		.pipe($.plumber())
		.pipe($.cleanCss())
		.pipe($.rename({ extname: '.min.css' }))
		.pipe(gulp.dest('dist', { sourcemaps: '.' }));
});

gulp.task('css-min', () => {
	return gulp.src(['src/**/*.min.css'])
		.pipe($.plumber())
		.pipe(gulp.dest('dist'));
});

gulp.task('css', gulp.parallel('css-raw', 'css-min'));

gulp.task('php', () => {
	return gulp.src(['src/**/*.php'])
		.pipe($.plumber())
		.pipe($.changed('dist'))
		.pipe(gulp.dest('dist'));
});

gulp.task('watch', () => {
	gulp.watch('src/**/*.js', gulp.series('js'));
	gulp.watch('src/**/*.css', gulp.series('css'));
	gulp.watch('src/**/*.php', gulp.series('php'));
});

gulp.task('build', gulp.parallel('js', 'css', 'php'));

gulp.task('default', gulp.series('build', 'watch'));
