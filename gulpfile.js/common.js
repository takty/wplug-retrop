/**
 *
 * Common functions for gulp
 *
 * @author Takuto Yanagida
 * @version 2021-10-14
 *
 */

'use strict';

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

