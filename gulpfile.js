var gulp       = require('gulp');
var plumber    = require('gulp-plumber');
var changed    = require('gulp-changed');
var uglify     = require('gulp-uglify');
var rename     = require('gulp-rename');
var sourcemaps = require('gulp-sourcemaps');
var sass       = require('gulp-sass');
var cleanCSS   = require('gulp-clean-css');
var babel      = require('gulp-babel');

gulp.task('js', function() {
	gulp.src(['src/**/*.js', '!src/**/*.min.js', '!src/_backup/**/*'], {base: 'src'})
	.pipe(plumber())
	.pipe(babel({presets: ['es2015']}))
	.pipe(uglify())
	.pipe(rename({extname: '.min.js'}))
	.pipe(gulp.dest('dist'));

	gulp.src(['src/**/*.min.js'])
	.pipe(plumber())
	.pipe(gulp.dest('dist'));
});

gulp.task('sass', function () {
	gulp.src(['src/**/*.scss', '!src/_backup/**/*'])
	.pipe(plumber())
    .pipe(sourcemaps.init())
	.pipe(sass())
	.pipe(cleanCSS())
	.pipe(rename({extname: '.min.css'}))
	.pipe(sourcemaps.write('.'))
	.pipe(gulp.dest('dist'));
});

gulp.task('css', function () {
	gulp.src(['src/**/*.css', '!src/**/*.min.css', '!src/_backup/**/*'], {base: 'src'})
	.pipe(plumber())
    .pipe(sourcemaps.init())
	.pipe(cleanCSS())
	.pipe(rename({extname: '.min.css'}))
	.pipe(sourcemaps.write('.'))
	.pipe(gulp.dest('dist'));

	gulp.src(['src/**/*.min.css'])
	.pipe(plumber())
	.pipe(gulp.dest('dist'));
});

gulp.task('php', function() {
	gulp.src(['src/**/*.php', '!src/_backup/**/*'])
	.pipe(changed('dist'))
	.pipe(plumber())
	.pipe(gulp.dest('dist'));
});

gulp.task('img', function() {
	gulp.src(['src/**/*.png', 'src/**/*.jpg', 'src/**/*.jpeg', 'src/**/*.svg', '!src/_backup/**/*'], {base: 'src'})
	.pipe(changed('dist'))
	.pipe(plumber())
	.pipe(gulp.dest('dist'));
});

gulp.task('watch', function() {
	gulp.watch('src/**/*.js', ['js']);
	gulp.watch('src/**/*.scss', ['sass']);
	gulp.watch('src/**/*.css', ['css']);
	gulp.watch('src/**/*.php', ['php']);
	gulp.watch('src/**/*.img', ['img']);
});

gulp.task('build', ['js', 'sass', 'css', 'php', 'img']);
gulp.task('default', ['js', 'sass', 'css', 'php', 'img', 'watch']);
