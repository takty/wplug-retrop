/**
 *
 * Publication List Filter (JS)
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-02-12
 *
 */


document.addEventListener('DOMContentLoaded', function () {

	var SEL_ITEM_ALL        = "*[data-bm='on']";
	var SEL_FILTER_KEY      = '.bm-list-filter-cat';
	var SEL_FILTER_SWITCH   = '.bm-list-filter-switch';
	var SEL_FILTER_CHECKBOX = 'input:not(.bm-list-filter-switch)';

	var keyToSwAndCbs = {};
	var fkElms = document.querySelectorAll(SEL_FILTER_KEY);
	for (var i = 0; i < fkElms.length; i += 1) {
		var elm = fkElms[i];
		var sw = elm.querySelector(SEL_FILTER_SWITCH);
		var cbs = elm.querySelectorAll(SEL_FILTER_CHECKBOX);
		keyToSwAndCbs[elm.dataset.key] = [sw, cbs];
	}

	for (var key in keyToSwAndCbs) {
		var sw = keyToSwAndCbs[key][0];
		var cbs = keyToSwAndCbs[key][1];
		assignEventListener(sw, cbs, update);
	}

	var allElms = document.querySelectorAll(SEL_ITEM_ALL);
	update();

	function update() {
		var keyToVals = getKeyToVals(keyToSwAndCbs);
		filterLists(allElms, keyToVals);
		// countUpItems(allElms);
		setUrlParams(keyToSwAndCbs);
	}


	// -------------------------------------------------------------------------

	function setUrlParams(keyToSwAndCbs) {
		var ps = [];
		for (var key in keyToSwAndCbs) {
			var sw = keyToSwAndCbs[key][0];
			var cbs = keyToSwAndCbs[key][1];
			if (sw.checked) ps.push('bm_cat_' + key + '=' + concatCheckedQvals(cbs));
		}
		if (ps.length > 0) {
			var ret = '?' + ps.join('&');
			history.replaceState('', '', ret);
		} else {
			history.replaceState('', '', document.location.origin + document.location.pathname);
		}
	}

	function concatCheckedQvals(cbs) {
		var vs = [];
		for (var i = 0; i < cbs.length; i += 1) {
			if (cbs[i].checked) vs.push(cbs[i].dataset.val);
		}
		return vs.join(',');
	}


	// -------------------------------------------------------------------------

	function assignEventListener(sw, cbs, update) {
		sw.addEventListener('click', function () {
			if (sw.checked && !isCheckedAtLeastOne(cbs)) {
				for (var i = 0; i < cbs.length; i += 1) cbs[i].checked = true;
			}
			update();
		});
		for (var i = 0; i < cbs.length; i += 1) {
			cbs[i].addEventListener('click', function () {
				sw.checked = isCheckedAtLeastOne(cbs);
				update();
			});
		}
	}

	function isCheckedAtLeastOne(cbs) {
		for (var i = 0; i < cbs.length; i += 1) {
			if (cbs[i].checked) return true;
		}
		return false;
	}

	function getKeyToVals(keyToSwAndCbs) {
		var kvs = {};
		for (var key in keyToSwAndCbs) {
			var fs = keyToSwAndCbs[key][0];
			var cbs = keyToSwAndCbs[key][1];
			if (fs.checked) kvs[key] = getCheckedVals(cbs);
		}
		return kvs;
	}

	function getCheckedVals(cbs) {
		var vs = [];
		for (var i = 0; i < cbs.length; i += 1) {
			if (cbs[i].checked) vs.push(cbs[i].dataset.val);
		}
		return vs;
	}


	// -------------------------------------------------------------------------

	function filterLists(elms, fkeyToVals) {
		for (var j = 0, J = elms.length; j < J; j += 1) {
			var elm = elms[j];
			if (elm.tagName !== 'OL' && elm.tagName !== 'UL') continue;
			var lis = elm.getElementsByTagName('li');
			var showCount = 0;
			for (var i = 0, I = lis.length; i < I; i += 1) {
				var li = lis[i];
				var show = isMatch(li, fkeyToVals);
				// li.style.display = show ? '' : 'none';
				li.classList.remove('bm-filtered');
				if (!show) li.classList.add('bm-filtered');
				if (show) showCount += 1;
			}
			elm.dataset['count'] = showCount;
		}
	}

	function isMatch(itemElm, fkeyToVals) {
		for (var key in fkeyToVals) {
			var fvals = fkeyToVals[key];
			var contains = false;

			for (var i = 0; i < fvals.length; i += 1) {
				var cls = 'bm-cat-' + key + '-' + fvals[i];
				cls = cls.replace('_', '-');
				if (itemElm.classList.contains(cls)) {
					contains = true;
					break;
				}
			}
			if (!contains) return false;
		}
		return true;
	}


	// -------------------------------------------------------------------------

	function countUpItems(elms) {
		// var headingToDepthCount = {};
		// for (var i = 0, I = elms.length; i < I; i += 1) {
		// 	var elm = elms[i];
		// 	if (elm.dataset['depth']) elm.dataset['count'] = 0;  // 'elm' is heading
		// }
		// var headers = [];
		// for (var i = 0, I = elms.length; i < I; i += 1) {
		// 	var elm = elms[i];

			// if (elm.dataset['depth']) {  // 'elm' is heading
			// 	var hi = parseInt(elm.dataset.depth);
			// 	while (headers.length > 0) {
			// 		var l = headers[headers.length - 1];
			// 		if (hi > parseInt(l.dataset.depth)) break;
			// 		headers.length -= 1;
			// 	}
			// 	headers.push(elm);
			// } else {  // 'elm' is list
			// 	var itemCount = parseInt(elm.dataset['count']);
			// 	for (var j = 0; j < headers.length; j += 1) {
			// 		var h = headers[j];
			// 		h.dataset['count'] = parseInt(h.dataset['count']) + itemCount;
			// 	}
			// }
		// }
		// for (var i = 0, I = elms.length; i < I; i += 1) {
		// 	var elm = elms[i];
		// 	if (elm.dataset['depth']) {  // 'elm' is heading
		// 		var itemCount = parseInt(elm.dataset['count']);
		// 		elm.style.display = itemCount > 0 ? '' : 'none';
		// 	}
		// }
	}

});
