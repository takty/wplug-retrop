/**
 *
 * Publication File Loader
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-03-08
 *
 */


document.addEventListener('DOMContentLoaded', function () {

	var ID_FILE_PICKER        = '_bimeson_media';
	// var SEL_KEY_ANCESTOR_JSON = '#_bimeson_pub_key_ancestor';
	// var SEL_KEY_ORDER_JSON    = '#_bimeson_pub_key_order';
	var SEL_PARAM_JSON        = '#_bimeson_json_params';
	var SEL_FILTER_KEY        = '.pub-list-filter-key';
	var SEL_FILTER_SWITCH     = '.pub-list-filter-switch';
	var SEL_FILTER_CHECKBOX   = 'input:not(.pub-list-filter-switch)';
	var SEL_FILTER_LANG       = '#_bimeson_pub_lang';
	var SEL_FILTER_BUTTON     = '.bimeson_list_filter_button';
	var SEL_LOADING_SPIN      = '.bimeson_list_filter_loading';
	var SEL_RESULT_HOLDER     = '#_bimeson_pub_items';
	// var SEL_FIRST_KEY_OMITTED = '#_bimeson_pub_first_key_omitted';

	var KEY_BODY       = '_body';
	// var KEY_ENGLISH    = '_english';
	// var KEY_JAPANESE   = '_japanese';
	//
	// var KEY_DOI        = '_doi';
	// var KEY_LINK_URL   = '_link_url';
	// var KEY_LINK_TITLE = '_link_title';

	// var KEY_ANCESTOR    = JSON.parse(document.querySelector(SEL_KEY_ANCESTOR_JSON).value);
	// var KEY_ORDER       = JSON.parse(document.querySelector(SEL_KEY_ORDER_JSON).value);
	// var KEY_ORDER_ROOT  = KEY_ORDER['__root__'];
	// var KEY_ORDER_DEPTH = KEY_ORDER['__depth__'];

	// var OPT_FIRST_KEY_OMITTED = document.querySelector(SEL_FIRST_KEY_OMITTED).checked;

	// console.dir(KEY_ORDER);
	// console.dir(KEY_ORDER_ROOT);
	// console.dir(KEY_ORDER_DEPTH);
	// console.dir(KEY_ANCESTOR);

	// var keyToSwAndCbs = {};
	// var fkElms = document.querySelectorAll(SEL_FILTER_KEY);
	// for (var i = 0; i < fkElms.length; i += 1) {
	// 	var elm = fkElms[i];
	// 	var sw = elm.querySelector(SEL_FILTER_SWITCH);
	// 	var cbs = elm.querySelectorAll(SEL_FILTER_CHECKBOX);
	// 	keyToSwAndCbs[elm.dataset.key] = [sw, cbs];
	// }
	//
	// var paramJson = document.querySelector(SEL_PARAM_JSON);
	// var keyToVals = (paramJson.value) ? JSON.parse(paramJson.value) : {};
	// function update() {
	// 	keyToVals = getKeyToVals(keyToSwAndCbs);
	// 	paramJson.value = JSON.stringify(keyToVals);
	// }
	//
	// for (var key in keyToSwAndCbs) {
	// 	var sw = keyToSwAndCbs[key][0];
	// 	var cbs = keyToSwAndCbs[key][1];
	//
	// 	for (var i = 0; i < cbs.length; i += 1) {
	// 		var v = cbs[i].dataset.val;
	// 		if (keyToVals[key] && keyToVals[key].indexOf(v) !== -1) {
	// 			cbs[i].checked = true;
	// 			sw.checked = true;
	// 		}
	// 	}
	// 	assignEventListener(sw, cbs, update);
	// }

	// var btn = document.querySelector(SEL_FILTER_BUTTON);
	// btn.addEventListener('click', function () {
	// 	var count = document.getElementById(ID_FILE_PICKER).value;
	// 	var urls = [];
	//
	// 	for (var i = 0; i < count; i += 1) {
	// 		var id = ID_FILE_PICKER + '_' + i;
	// 		var delElm = document.getElementById(id + '_delete');
	// 		if (delElm.checked) continue;
	// 		var url = document.getElementById(id + '_url').value;
	// 		if (url.length !== 0) urls.push(url);
	// 	}
	// 	// var lang = document.querySelector(SEL_FILTER_LANG).value;
	// 	// disableFilterButton();
	// 	loadFiles(urls, {}, '', SEL_RESULT_HOLDER, enableFilterButton);
	// });


	// -------------------------------------------------------------------------

	function disableFilterButton() {
		var spin = document.querySelector(SEL_LOADING_SPIN);
		spin.style.visibility = 'visible';

		var btn = document.querySelector(SEL_FILTER_BUTTON);
		btn.style.pointerEvents = 'none';
		btn.style.opacity = '0.5';
		btn.blur();
	}

	function enableFilterButton() {
		setTimeout(function () {
			var spin = document.querySelector(SEL_LOADING_SPIN);
			spin.style.visibility = '';

			var btn = document.querySelector(SEL_FILTER_BUTTON);
			btn.style.pointerEvents = '';
			btn.style.opacity = '';
		}, 400);
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

	function loadFiles(urls, keyToVals, lang, resSelector, onFinished) {
		var recCount = 0;
		var items = [];

		if (urls.length === 0) {
			var res = document.querySelector(resSelector);
			if (res) res.value = '';
			console.log('Complete filtering (No data)');
			onFinished();
			return;
		}

		urls.forEach(function (url) {
			console.log('Requesting file...');
			var req = new XMLHttpRequest();
			req.open('GET', url, true);
			req.responseType = 'arraybuffer';
			req.onload = makeListener(url, req);
			req.send();
		});

		function makeListener(url, req) {
			return function (e) {
				if (!req.response) {
					console.log('Did not receive file (' + url + ')');
					return;
				}
				console.log('Received file: ' + req.response.byteLength + ' bytes (' + url + ')');
				process(req.response);
				if (++recCount === urls.length) finished();
			};
		}

		function process(response) {
			var data = new Uint8Array(response);
			var arr = new Array();
			for (var i = 0, I = data.length; i < I; i += 1) arr[i] = String.fromCharCode(data[i]);
			var bstr = arr.join('');

			var book = XLSX.read(bstr, {type:'binary'});
			var sheetName = book.SheetNames[0];
			var sheet = book.Sheets[sheetName];
			if (sheet) processSheet(sheet, keyToVals, lang, items);
			console.log('Finish filtering file');
		}

		function finished() {
			// for (var i = 0; i < items.length; i += 1) items[i].__index__ = i;
			// items.sort(compareItem);
			// for (var i = 0; i < items.length; i += 1) delete items[i].__index__;
			var res = document.querySelector(resSelector);
			if (res) res.value = JSON.stringify(items);
			console.log('Complete filtering (' + items.length + ' items)');
			onFinished();
		}
	}


	// -------------------------------------------------------------------------

	function processSheet(sheet, fkeyToVals, lang, retItems) {
		var range = XLSX.utils.decode_range(sheet['!ref']);
		var x0 = range.s.c, x1 = Math.min(range.e.c, 40) + 1;
		var y0 = range.s.r, y1 = range.e.r + 1;

		var colCount = 0, colToKey = {};
		for (var x = x0; x < x1; x += 1) {
			var cell = sheet[XLSX.utils.encode_cell({c: x, r: y0})];
			if (!cell || cell.w === '') break;
			colCount += 1;
			colToKey[x] = normalizeKey(cell.w + '', true);
		}
		x1 = x0 + colCount;

		for (var y = y0 + 1; y < y1; y += 1) {  // skip header
			var item = {};
			for (var x = x0; x < x1; x += 1) {
				var cell = sheet[XLSX.utils.encode_cell({c: x, r: y})];
				var key = colToKey[x];
				if (key === KEY_BODY || key.indexOf(KEY_BODY + '_') === 0) {
					if (cell && cell.h && cell.h.length > 0) {
						var text = cell.h.replace(/<\/?span("[^"]*"|'[^']*'|[^'">])*>/g, '');  // remove automatically inserted 'span' tag.
						text = text.replace(/<br\/>/g, '<br />');
						text = text.replace(/&#x000d;&#x000a;/g, '<br />');
						item[key] = text;
					}
				} else if (key[0] === '_') {
					if (cell && cell.w && cell.w.length > 0) {
						item[key] = cell.w;
					}
				} else {
					if (cell && cell.w && cell.w.length > 0) {
						var vals = cell.w.split(/\s*,\s*/);
						item[key] = vals.map(function (x) {return normalizeKey(x, false);});
					}
				}
			}
			// if (isMatch(item, fkeyToVals)) {
				// sortItemVals(item);
				// makeHierarchicalSortKey(item);
				retItems.push(item);
			// }
		}
	}

	function normalizeKey(str, isKey) {
		str = str.replace(/[Ａ-Ｚａ-ｚ０-９]/g, function (s) {return String.fromCharCode(s.charCodeAt(0) - 0xFEE0);});
		str = str.replace(/[_＿]/g, '_');
		str = str.replace(/[\-‐―ー]/g, '-');
		str = str.replace(/[^A-Za-z0-9_\-]/g, '');
		str = str.toLowerCase();
		str = str.trim();
		if (0 < str.length) {
			if (!isKey && (str[0] === '_' || str[0] === '-')) str = str.replace(/^[_\-]+/, '');
			if (str[0] !== '_') str = str.replace('_', '-');
			if (str[0] === '_') str = str.replace('-', '_');
		}
		return str;
	}

	// function makeBody(item, lang) {
	// 	var en = item[KEY_ENGLISH];
	// 	var ja = item[KEY_JAPANESE];
	// 	delete item[KEY_ENGLISH];
	// 	delete item[KEY_JAPANESE];
	// 	item[KEY_BODY] = (lang === 'ja') ? (ja ? ja : en) : (en ? en : '');
	//
	// 	if (item[KEY_BODY] === undefined) {
	// 		console.log('Item without body is found:');
	// 		console.dir(item);
	// 	}
	// 	return item[KEY_BODY] && item[KEY_BODY].length > 0;
	// }

	// function isMatch(item, fkeyToVals) {
	// 	for (var key in fkeyToVals) {
	// 		var vals = item[key];
	// 		if (vals === undefined) return false;
	// 		var fvals = fkeyToVals[key];
	// 		var contains = false;
	//
	// 		for (var i = 0; i < fvals.length; i += 1) {
	// 			if (vals.indexOf(fvals[i]) !== -1) {
	// 				contains = true;
	// 				break;
	// 			}
	// 		}
	// 		if (!contains) return false;
	// 	}
	// 	return true;
	// }

	// function sortItemVals(item) {
	// 	for (var i = 0; i < KEY_ORDER_ROOT.length; i += 1) {
	// 		var key = KEY_ORDER_ROOT[i];
	// 		var order = KEY_ORDER[key];
	// 		var vals = item[key];
	// 		if (vals) vals.sort(function (a, b) {return compareWithOrder(a, b, order);});
	// 	}
	// }

	// function compareWithOrder(a, b, order) {
	// 	var ai = order[a] !== undefined ? order[a] : -1, bi = order[b] !== undefined ? order[b] : -1;
	// 	if (ai === -1 && bi === -1) {
	// 		if (a < b) return -1;
	// 		if (a > b) return 1;
	// 		return 0;
	// 	}
	// 	if (ai === -1) return 1;  // 'val' that does not exist in 'order' is put last.
	// 	if (bi === -1) return -1;
	//
	// 	if (ai < bi) return -1;
	// 	if (ai > bi) return 1;
	// 	return 0;
	// }

	// function compareItem(a, b) {
	// 	for (var i = OPT_FIRST_KEY_OMITTED ? 1 : 0, I = KEY_ORDER_ROOT.length; i < I; i += 1) {
	// 		var key = KEY_ORDER_ROOT[i];
	// 		var order = KEY_ORDER[key];
	//
	// 		var av = getOneOfOrderedTerms(a, key);
	// 		var bv = getOneOfOrderedTerms(b, key);
	// 		if (av === null && bv === null) continue;
	//
	// 		var ai = av !== null ? order[av] : -1;
	// 		var bi = bv !== null ? order[bv] : -1;
	//
	// 		if (ai === -1 && bi === -1) {
	// 			if (av < bv) return -1;
	// 			if (av > bv) return 1;
	// 			continue;
	// 		}
	// 		if (ai < bi) return -1;
	// 		if (ai > bi) return 1;
	// 	}
	// 	return a.__index__ < b.__index__ ? -1 : 1;
	// }

	// function makeHierarchicalSortKey(item) {
	// 	var hvs = [];
	// 	for (var i = 0, I = KEY_ORDER_ROOT.length; i < I; i += 1) {
	// 		var key = KEY_ORDER_ROOT[i];
	// 		var depth = KEY_ORDER_DEPTH[key];
	// 		if (!depth) continue;
	//
	// 		var v = getOneOfOrderedTerms(item, key);
	// 		if (v) {
	// 			var ks = KEY_ANCESTOR[v], hs;
	// 			if (ks) {
	// 				hs = ks.slice(0);  // make a clone
	// 				hs.reverse();
	// 				hs.push(v);
	// 			} else {
	// 				hs = [v];
	// 			}
	// 			var emptyNum = depth - hs.length;
	// 			for (var j = 0; j < hs.length; j += 1) hvs.push(hs[j]);
	// 			for (var j = 0; j < emptyNum; j += 1) hvs.push('');
	// 		} else {
	// 			for (var j = 0; j < depth; j += 1) hvs.push('');
	// 		}
	// 	}
	// 	item['__sortkey__'] = hvs.join(',');
	// }

	// function getOneOfOrderedTerms(item, key) {
	// 	var vs = item[key];
	// 	if (vs === undefined) return null;
	// 	var order = KEY_ORDER[key];
	// 	var vs_t = [];
	// 	for (var j = 0; j < vs.length; j += 1) {
	// 		if (order[vs[j]] !== undefined) vs_t.push(vs[j]);
	// 	}
	// 	if (vs_t.length === 0) return null;
	// 	return vs_t[0];
	// }

});
