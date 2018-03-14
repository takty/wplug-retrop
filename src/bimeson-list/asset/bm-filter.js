/**
 *
 * Bimeson List Filter
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-03-14
 *
 */


document.addEventListener('DOMContentLoaded', function () {

	const SEL_ITEM_ALL        = '.bimeson-content > *';
	const SEL_FILTER_KEY      = '.bimeson-filter-key';
	const SEL_FILTER_SWITCH   = '.bimeson-filter-switch';
	const SEL_FILTER_CHECKBOX = 'input:not(.bimeson-filter-switch)';

	const SEL_FILTER_SELECT = '.bimeson-filter-select';
	const KEY_YEAR          = '_year';
	const VAL_YEAR_ALL      = 'all';
	const QVAR_YEAR         = 'bm-year';
	const DS_KEY_YEAR       = 'year';

	var keyToSwAndCbs = {}, yearSelect = null;
	var fkElms = document.querySelectorAll(SEL_FILTER_KEY);
	for (var i = 0; i < fkElms.length; i += 1) {
		var elm = fkElms[i];
		var sw = elm.querySelector(SEL_FILTER_SWITCH);
		var cbs = elm.querySelectorAll(SEL_FILTER_CHECKBOX);
		if (sw && cbs) keyToSwAndCbs[elm.dataset.key] = [sw, cbs];
		if (elm.dataset.key === KEY_YEAR) {
			yearSelect = elm.querySelector(SEL_FILTER_SELECT);
		}
	}

	for (var key in keyToSwAndCbs) {
		var sw = keyToSwAndCbs[key][0];
		var cbs = keyToSwAndCbs[key][1];
		assignEventListener(sw, cbs, update);
	}
	if (yearSelect) assignEventListenerSelect(yearSelect);

	var allElms = document.querySelectorAll(SEL_ITEM_ALL);
	update();

	function update() {
		var keyToVals = getKeyToVals(keyToSwAndCbs);
		var year = (yearSelect && yearSelect.value !== VAL_YEAR_ALL) ? yearSelect.value : false;
		filterLists(allElms, keyToVals, year);
		countUpItems(allElms);
		setUrlParams(keyToSwAndCbs, yearSelect);
	}


	// -------------------------------------------------------------------------

	function setUrlParams(keyToSwAndCbs, yearSelect) {
		var ps = [];
		for (var key in keyToSwAndCbs) {
			var sw = keyToSwAndCbs[key][0];
			var cbs = keyToSwAndCbs[key][1];
			if (sw.checked) ps.push('bm-cat-' + key + '=' + concatCheckedQvals(cbs));
		}
		if (yearSelect && yearSelect.value !== VAL_YEAR_ALL) ps.push('bm-year=' + yearSelect.value);
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
			if (cbs[i].checked) vs.push(cbs[i].value);
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

	function assignEventListenerSelect(yearSelect) {
		yearSelect.addEventListener('change', function() {
			update();
		});
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
			if (cbs[i].checked) vs.push(cbs[i].value);
		}
		return vs;
	}


	// -------------------------------------------------------------------------

	function filterLists(elms, fkeyToVals, year) {
		for (var j = 0, J = elms.length; j < J; j += 1) {
			var elm = elms[j];
			if (elm.tagName !== 'OL' && elm.tagName !== 'UL') continue;
			var lis = elm.getElementsByTagName('li');
			var showCount = 0;
			for (var i = 0, I = lis.length; i < I; i += 1) {
				var li = lis[i];
				var show = isMatch(li, fkeyToVals, year);
				li.style.display = show ? '' : 'none';
				if (show) showCount += 1;
			}
			elm.dataset['count'] = showCount;
		}
	}

	function isMatch(itemElm, fkeyToVals, year) {
		if (year !== false) {
			if (itemElm.dataset[DS_KEY_YEAR] !== year) return false;
		}
		for (var key in fkeyToVals) {
			var fvals = fkeyToVals[key];
			var contains = false;

			for (var i = 0; i < fvals.length; i += 1) {
				if (itemElm.classList.contains('bm-cat-' + key + '-' + fvals[i])) {
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
		var headingToDepthCount = {};
		for (var i = 0, I = elms.length; i < I; i += 1) {
			var elm = elms[i];
			if (elm.dataset['depth']) elm.dataset['count'] = 0;  // 'elm' is heading
		}
		var headers = [];
		for (var i = 0, I = elms.length; i < I; i += 1) {
			var elm = elms[i];
			if (elm.dataset['depth']) {  // 'elm' is heading
				var hi = parseInt(elm.dataset.depth);
				while (headers.length > 0) {
					var l = headers[headers.length - 1];
					if (hi > parseInt(l.dataset.depth)) break;
					headers.length -= 1;
				}
				headers.push(elm);
			} else {  // 'elm' is list
				var itemCount = parseInt(elm.dataset['count']);
				for (var j = 0; j < headers.length; j += 1) {
					var h = headers[j];
					h.dataset['count'] = parseInt(h.dataset['count']) + itemCount;
				}
			}
		}
		for (var i = 0, I = elms.length; i < I; i += 1) {
			var elm = elms[i];
			if (elm.dataset['depth']) {  // 'elm' is heading
				var itemCount = parseInt(elm.dataset['count']);
				elm.style.display = itemCount > 0 ? '' : 'none';
			}
		}
	}

});
