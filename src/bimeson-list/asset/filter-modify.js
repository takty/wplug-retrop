/**
 *
 * Filter Modification (JS)
 *
 * @author Takuto Yanagida @ Space-Time Inc.
 * @version 2020-05-28
 *
 */


(function () {

	const filterElement = document.querySelector(".bimeson-filter-key[data-key~='theme'] .bimeson-filter-cbs");
	if( filterElement === null ) return;
	const defaultInputElements = filterElement.querySelectorAll("input");

	const labelElements = Array(3);
	const inputElements = Array(3);

	for( let i = 0; i < 3; i++ ) {
		labelElements[i] = document.createElement("label");
		labelElements[i].style.display = 'inline-block';
		inputElements[i] = document.createElement("input");
		inputElements[i].setAttribute('type','checkbox');
		let text = '  A0' + (i + 1);
		let str = document.createTextNode(text);

		labelElements[i].appendChild(inputElements[i]);
		labelElements[i].appendChild(str);
		filterElement.appendChild(labelElements[i]);

		inputElements[i].addEventListener('click', function(){
			for( let j = 0; j < defaultInputElements.length; j++ ){
				if( defaultInputElements[j].value.indexOf('a0' + (i + 1) + '-') === 0 ){
					if (inputElements[i].checked) {
						defaultInputElements[j].checked = true;
					}else{
						defaultInputElements[j].checked = false;
					}
				}
			}
		})
	};


})();
