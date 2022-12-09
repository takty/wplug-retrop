/**
 * Gulpfile
 *
 * @author Takuto Yanagida
 * @version 2022-12-09
 */

import gulp from 'gulp';

import { makeJsTask } from './gulp/task-js.mjs';
import { makeCopyTask } from './gulp/task-copy.mjs';

const js_raw  = makeJsTask(['src/**/*.js', '!src/**/*.min.js'], './dist', 'src');
const js_copy = makeCopyTask('src/**/*.min.js', './dist');
const js      = gulp.parallel(js_raw, js_copy);

const php = makeCopyTask('src/**/*.php', './dist');

const watch = done => {
	gulp.watch('src/**/*.js', js);
	gulp.watch('src/**/*.php', php);
	done();
};

export const build = gulp.parallel(js, php);
export default gulp.series(build, watch);
