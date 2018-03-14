/**
 *
 * Bimeson List Page Admin
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2018-03-14
 *
 */


document.addEventListener('DOMContentLoaded', function () {

	var SEL_FILTER_KEY      = '.bimeson-filter-key';
	var SEL_FILTER_SWITCH   = '.bimeson-filter-switch';
	var SEL_FILTER_CHECKBOX = 'input:not(.bimeson-filter-switch)';

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
		assignEventListener(sw, cbs);
	}


	// -------------------------------------------------------------------------

	function assignEventListener(sw, cbs) {
		sw.addEventListener('click', function () {
			if (sw.checked && !isCheckedAtLeastOne(cbs)) {
				for (var i = 0; i < cbs.length; i += 1) cbs[i].checked = true;
			}
		});
		for (var i = 0; i < cbs.length; i += 1) {
			cbs[i].addEventListener('click', function () {
				sw.checked = isCheckedAtLeastOne(cbs);
			});
		}
	}

	function isCheckedAtLeastOne(cbs) {
		for (var i = 0; i < cbs.length; i += 1) {
			if (cbs[i].checked) return true;
		}
		return false;
	}

});
