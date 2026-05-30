(function () {
	'use strict';

	// Initial pass: set data-source on every field row so the CSS shows the right input.
	function syncRow(row) {
		var sel = row.querySelector('.schema-mapper-source-select');
		if (!sel) return;
		row.setAttribute('data-source', sel.value || '');
		var keySelect = row.querySelector('.schema-mapper-acf-key');
		if (keySelect) {
			Array.prototype.forEach.call(keySelect.options, function (opt) {
				if (!opt.dataset || !opt.dataset.source || !opt.value) return;
				opt.disabled = (sel.value && opt.dataset.source !== sel.value);
			});
			// If currently selected option is now disabled, reset to placeholder.
			if (keySelect.selectedOptions[0] && keySelect.selectedOptions[0].disabled) {
				keySelect.value = '';
			}
		}
	}

	function init() {
		var rows = document.querySelectorAll('.schema-mapper-field-row');
		Array.prototype.forEach.call(rows, function (row) {
			var sel = row.querySelector('.schema-mapper-source-select');
			if (!sel) return;
			sel.addEventListener('change', function () { syncRow(row); });
			syncRow(row);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
