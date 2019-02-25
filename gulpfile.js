'use strict';

const gulp = require('gulp');
const $ = require('gulp-load-plugins')({pattern: ['gulp-*']});


gulp.task('js-raw', () => {
	return gulp.src(['src/**/*.js', '!src/**/*.min.js'], { base: 'src' })
		.pipe($.plumber())
		.pipe($.babel({ presets: [['@babel/env', { targets: { ie: 11 } }]] }))
		.pipe($.rename({ extname: '.min.js' }))
		.pipe($.uglify())
		.pipe(gulp.dest('dist'));
});

gulp.task('js-min', () => {
	return gulp.src(['src/**/*.min.js'])
		.pipe($.plumber())
		.pipe(gulp.dest('dist'));
});

gulp.task('js', gulp.parallel('js-raw', 'js-min'));

gulp.task('sass', () => {
	return gulp.src(['src/**/*.scss'])
		.pipe($.plumber())
		.pipe($.sourcemaps.init())
		.pipe($.sass({ outputStyle: 'compressed' }))
		.pipe($.autoprefixer({ browsers: ['ie >= 11'], remove: false }))
		.pipe($.rename({ extname: '.min.css' }))
		.pipe($.sourcemaps.write('.'))
		.pipe(gulp.dest('dist'));
});

gulp.task('css-raw', () => {
	return gulp.src(['src/**/*.css', '!src/**/*.min.css'], { base: 'src' })
		.pipe($.plumber())
		.pipe($.sourcemaps.init())
		.pipe($.cleanCss())
		.pipe($.rename({ extname: '.min.css' }))
		.pipe($.sourcemaps.write('.'))
		.pipe(gulp.dest('dist'));
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

gulp.task('img', () => {
	return gulp.src(['src/**/*.png', 'src/**/*.jpg', 'src/**/*.jpeg', 'src/**/*.svg'], { base: 'src' })
		.pipe($.plumber())
		.pipe($.changed('dist'))
		.pipe(gulp.dest('dist'));
});

gulp.task('watch', () => {
	gulp.watch('src/**/*.js', gulp.series('js'));
	gulp.watch('src/**/*.scss', gulp.series('sass'));
	gulp.watch('src/**/*.css', gulp.series('css'));
	gulp.watch('src/**/*.php', gulp.series('php'));
	gulp.watch('src/**/*.img', gulp.series('img'));
});

gulp.task('build', gulp.parallel('js', 'sass', 'css', 'php', 'img'));

gulp.task('default', gulp.series('build', 'watch'));
